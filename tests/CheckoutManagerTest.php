<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCheckout\Tests;

use FelixMuhoro\Mpesa\Mpesa;
use FelixMuhoro\MpesaCheckout\CheckoutManager;
use FelixMuhoro\MpesaCheckout\CheckoutSession;
use FelixMuhoro\MpesaCheckout\CheckoutStatus;
use FelixMuhoro\MpesaCheckout\MpesaCheckoutServiceProvider;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;

class CheckoutManagerTest extends TestCase
{
    private MockInterface $mpesa;
    private Repository $cache;
    private CheckoutManager $manager;

    protected function getPackageProviders($app): array
    {
        return [MpesaCheckoutServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mpesa   = Mockery::mock(Mpesa::class);
        $this->cache   = new Repository(new ArrayStore());
        $this->manager = new CheckoutManager(
            mpesa:  $this->mpesa,
            cache:  $this->cache,
            config: [
                'session_ttl'  => 300,
                'route_prefix' => 'mpesa-checkout',
                'cache_store'  => null,
            ],
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // CheckoutSession value object
    // -------------------------------------------------------------------------

    public function test_session_make_generates_unique_id(): void
    {
        $a = CheckoutSession::make(100, '0712345678', 'REF001', 'Test', 'https://example.com/cb');
        $b = CheckoutSession::make(100, '0712345678', 'REF001', 'Test', 'https://example.com/cb');

        $this->assertNotSame($a->sessionId, $b->sessionId);
        $this->assertStringStartsWith('mpc_', $a->sessionId);
    }

    public function test_session_serialises_and_deserialises(): void
    {
        $session  = CheckoutSession::make(500, '0712345678', 'INV-001', 'Test payment', 'https://example.com/cb');
        $restored = CheckoutSession::fromArray($session->jsonSerialize());

        $this->assertSame($session->sessionId, $restored->sessionId);
        $this->assertSame($session->amount, $restored->amount);
        $this->assertSame($session->phone, $restored->phone);
        $this->assertSame($session->status->value, $restored->status->value);
    }

    public function test_session_with_result_marks_completed(): void
    {
        $session = CheckoutSession::make(100, '0712345678', 'REF', 'Desc', 'https://cb.example.com');
        $done    = $session->withResult('PHG3OPQ4XY');

        $this->assertSame(CheckoutStatus::Completed, $done->status);
        $this->assertSame('PHG3OPQ4XY', $done->mpesaReceiptNumber);
    }

    public function test_session_with_failure_marks_failed(): void
    {
        $session = CheckoutSession::make(100, '0712345678', 'REF', 'Desc', 'https://cb.example.com');
        $failed  = $session->withFailure('Request cancelled by user');

        $this->assertSame(CheckoutStatus::Failed, $failed->status);
        $this->assertSame('Request cancelled by user', $failed->failureReason);
    }

    public function test_session_is_expired_returns_false_when_fresh(): void
    {
        $session = CheckoutSession::make(100, '0712345678', 'REF', 'Desc', 'https://cb.example.com');

        $this->assertFalse($session->isExpired());
    }

    // -------------------------------------------------------------------------
    // CheckoutStatus enum
    // -------------------------------------------------------------------------

    public function test_terminal_statuses(): void
    {
        $this->assertTrue(CheckoutStatus::Completed->isTerminal());
        $this->assertTrue(CheckoutStatus::Failed->isTerminal());
        $this->assertTrue(CheckoutStatus::Expired->isTerminal());
        $this->assertTrue(CheckoutStatus::Cancelled->isTerminal());
        $this->assertFalse(CheckoutStatus::Pending->isTerminal());
        $this->assertFalse(CheckoutStatus::Processing->isTerminal());
    }

    public function test_only_completed_is_success(): void
    {
        $this->assertTrue(CheckoutStatus::Completed->isSuccess());
        $this->assertFalse(CheckoutStatus::Failed->isSuccess());
        $this->assertFalse(CheckoutStatus::Pending->isSuccess());
    }

    // -------------------------------------------------------------------------
    // CheckoutManager - initiate
    // -------------------------------------------------------------------------

    public function test_initiate_sends_stk_push_and_returns_session(): void
    {
        $this->mpesa
            ->shouldReceive('stkPush')
            ->once()
            ->andReturn([
                'CheckoutRequestID'   => 'ws_CO_123456',
                'MerchantRequestID'   => 'mr_7890',
                'ResponseCode'        => '0',
                'ResponseDescription' => 'Success',
            ]);

        $session = $this->manager->initiate(
            amount:      500,
            phone:       '0712345678',
            reference:   'INV-001',
            description: 'Test payment',
        );

        $this->assertSame(CheckoutStatus::Processing, $session->status);
        $this->assertSame('ws_CO_123456', $session->checkoutRequestId);
        $this->assertSame(500, $session->amount);
    }

    public function test_initiate_marks_failed_when_stk_throws(): void
    {
        $this->mpesa
            ->shouldReceive('stkPush')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $session = $this->manager->initiate(500, '0712345678', 'INV', 'Test');

        $this->assertSame(CheckoutStatus::Failed, $session->status);
        $this->assertStringContainsString('Connection refused', $session->failureReason);
    }

    // -------------------------------------------------------------------------
    // CheckoutManager - poll
    // -------------------------------------------------------------------------

    public function test_poll_returns_expired_for_unknown_session(): void
    {
        $this->assertSame(CheckoutStatus::Expired, $this->manager->poll('mpc_nonexistent'));
    }

    public function test_poll_queries_safaricom_when_processing(): void
    {
        $this->mpesa
            ->shouldReceive('stkPush')
            ->once()
            ->andReturn(['CheckoutRequestID' => 'ws_CO_POLL_TEST', 'MerchantRequestID' => 'mr_001']);

        $this->mpesa
            ->shouldReceive('stkQuery')
            ->once()
            ->with('ws_CO_POLL_TEST')
            ->andReturn(['ResultCode' => 0, 'ResultDesc' => 'Success.', 'MpesaReceiptNumber' => 'QFG4ABCD12']);

        $session = $this->manager->initiate(200, '0712345678', 'POLL', 'Poll test');
        $status  = $this->manager->poll($session->sessionId);

        $this->assertSame(CheckoutStatus::Completed, $status);
    }

    public function test_poll_marks_failed_when_safaricom_declines(): void
    {
        $this->mpesa
            ->shouldReceive('stkPush')
            ->once()
            ->andReturn(['CheckoutRequestID' => 'ws_CO_FAIL', 'MerchantRequestID' => 'mr_002']);

        $this->mpesa
            ->shouldReceive('stkQuery')
            ->once()
            ->andReturn(['ResultCode' => 1032, 'ResultDesc' => 'Request cancelled by user']);

        $session = $this->manager->initiate(200, '0712345678', 'FAIL', 'Fail test');
        $status  = $this->manager->poll($session->sessionId);

        $this->assertSame(CheckoutStatus::Failed, $status);
    }

    // -------------------------------------------------------------------------
    // CheckoutManager - webhook recording
    // -------------------------------------------------------------------------

    public function test_record_success_updates_session(): void
    {
        $this->mpesa
            ->shouldReceive('stkPush')
            ->once()
            ->andReturn(['CheckoutRequestID' => 'ws_CO_WH_OK', 'MerchantRequestID' => 'mr_003']);

        $this->mpesa->shouldNotReceive('stkQuery');

        $session = $this->manager->initiate(300, '0711111111', 'WH', 'Webhook test');
        $this->manager->recordSuccess('ws_CO_WH_OK', 'RCPT_ABCDEF');

        $refreshed = $this->manager->find($session->sessionId);

        $this->assertNotNull($refreshed);
        $this->assertSame(CheckoutStatus::Completed, $refreshed->status);
        $this->assertSame('RCPT_ABCDEF', $refreshed->mpesaReceiptNumber);
    }

    public function test_record_failure_updates_session(): void
    {
        $this->mpesa
            ->shouldReceive('stkPush')
            ->once()
            ->andReturn(['CheckoutRequestID' => 'ws_CO_WH_FAIL', 'MerchantRequestID' => 'mr_004']);

        $session = $this->manager->initiate(300, '0711111111', 'WH2', 'Fail webhook');
        $this->manager->recordFailure('ws_CO_WH_FAIL', 'Insufficient balance');

        $refreshed = $this->manager->find($session->sessionId);

        $this->assertNotNull($refreshed);
        $this->assertSame(CheckoutStatus::Failed, $refreshed->status);
        $this->assertSame('Insufficient balance', $refreshed->failureReason);
    }

    // -------------------------------------------------------------------------
    // CheckoutManager - cancel
    // -------------------------------------------------------------------------

    public function test_cancel_marks_session_cancelled(): void
    {
        $this->mpesa
            ->shouldReceive('stkPush')
            ->once()
            ->andReturn(['CheckoutRequestID' => 'ws_CO_CANCEL', 'MerchantRequestID' => 'mr_005']);

        $session = $this->manager->initiate(150, '0722222222', 'CAN', 'Cancel test');
        $this->manager->cancel($session->sessionId);

        $refreshed = $this->manager->find($session->sessionId);
        $this->assertSame(CheckoutStatus::Cancelled, $refreshed->status);
    }

    public function test_cancel_noop_on_terminal_session(): void
    {
        $this->mpesa
            ->shouldReceive('stkPush')
            ->once()
            ->andReturn(['CheckoutRequestID' => 'ws_CO_CANCEL2', 'MerchantRequestID' => 'mr_006']);

        $session = $this->manager->initiate(150, '0722222222', 'CAN2', 'Already done');
        $this->manager->recordSuccess('ws_CO_CANCEL2', 'RCPT123');
        $this->manager->cancel($session->sessionId);

        $refreshed = $this->manager->find($session->sessionId);
        $this->assertSame(CheckoutStatus::Completed, $refreshed->status);
    }
}
