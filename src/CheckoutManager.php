<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCheckout;

use FelixMuhoro\Mpesa\Mpesa;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CheckoutManager
{
    private const CACHE_PREFIX = 'mpesa_checkout:';

    public function __construct(
        private readonly Mpesa  $mpesa,
        private readonly Cache  $cache,
        private readonly array  $config,
    ) {}

    /**
     * Initiate a new STK Push and return a CheckoutSession.
     */
    public function initiate(
        int|float $amount,
        string    $phone,
        string    $reference,
        string    $description,
        ?string   $callbackUrl = null,
    ): CheckoutSession {
        $callbackUrl = $callbackUrl ?? $this->defaultCallbackUrl();
        $ttl         = (int) ($this->config['session_ttl'] ?? 300);

        $session = CheckoutSession::make(
            amount:      $amount,
            phone:       $phone,
            reference:   $reference,
            description: $description,
            callbackUrl: $callbackUrl,
            ttlSeconds:  $ttl,
        );

        // Persist before STK so we can record the IDs on callback even if
        // the response arrives before we save.
        $this->persist($session);

        try {
            $response = $this->mpesa->stkPush(
                amount:      (int) $amount,
                phone:       $this->normalisePhone($phone),
                reference:   $reference,
                description: $description,
                callbackUrl: $callbackUrl,
            );

            $checkoutRequestId = $response['CheckoutRequestID']
                ?? $response['checkout_request_id']
                ?? throw new RuntimeException('Missing CheckoutRequestID from STK response');

            $merchantRequestId = $response['MerchantRequestID']
                ?? $response['merchant_request_id']
                ?? '';

            $session = $session
                ->withStkIds($checkoutRequestId, $merchantRequestId)
                ->withStatus(CheckoutStatus::Processing);

            $this->persist($session);
        } catch (\Throwable $e) {
            Log::error('[MpesaCheckout] STK Push failed', [
                'session_id' => $session->sessionId,
                'error'      => $e->getMessage(),
            ]);

            $session = $session->withFailure($e->getMessage());
            $this->persist($session);
        }

        return $session;
    }

    /**
     * Poll the current status of a checkout session.
     */
    public function poll(string $sessionId): CheckoutStatus
    {
        $session = $this->find($sessionId);

        if ($session === null) {
            return CheckoutStatus::Expired;
        }

        if ($session->isExpired() && ! $session->status->isTerminal()) {
            $session = $session->withStatus(CheckoutStatus::Expired);
            $this->persist($session);
        }

        // If still pending/processing and we have a CheckoutRequestID, query Safaricom
        if (
            ! $session->status->isTerminal()
            && $session->checkoutRequestId !== null
        ) {
            $session = $this->querySafaricom($session);
        }

        return $session->status;
    }

    /**
     * Record a successful M-Pesa callback.
     */
    public function recordSuccess(string $checkoutRequestId, string $receiptNumber): void
    {
        $session = $this->findByCheckoutRequestId($checkoutRequestId);

        if ($session !== null) {
            $this->persist($session->withResult($receiptNumber));
        }
    }

    /**
     * Record a failed M-Pesa callback.
     */
    public function recordFailure(string $checkoutRequestId, string $reason): void
    {
        $session = $this->findByCheckoutRequestId($checkoutRequestId);

        if ($session !== null) {
            $this->persist($session->withFailure($reason));
        }
    }

    /**
     * Cancel a pending session.
     */
    public function cancel(string $sessionId): void
    {
        $session = $this->find($sessionId);

        if ($session !== null && ! $session->status->isTerminal()) {
            $this->persist($session->withStatus(CheckoutStatus::Cancelled));
        }
    }

    /**
     * Retrieve a session from cache.
     */
    public function find(string $sessionId): ?CheckoutSession
    {
        $data = $this->cache->get(self::CACHE_PREFIX . $sessionId);

        if (!$data) {
            return null;
        }

        return CheckoutSession::fromArray($data);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function querySafaricom(CheckoutSession $session): CheckoutSession
    {
        try {
            $result = $this->mpesa->stkQuery($session->checkoutRequestId);

            $code = $result['ResultCode'] ?? $result['result_code'] ?? null;

            if ($code === null) {
                // Query still pending
                return $session;
            }

            if ((int) $code === 0) {
                $receipt = $result['MpesaReceiptNumber']
                    ?? $result['mpesa_receipt']
                    ?? 'CONFIRMED';

                $session = $session->withResult($receipt);
            } else {
                $reason = $result['ResultDesc']
                    ?? $result['result_desc']
                    ?? 'Payment declined';

                $session = $session->withFailure($reason);
            }

            $this->persist($session);
        } catch (\Throwable $e) {
            // Query failed — keep polling
            Log::warning('[MpesaCheckout] STK query error', [
                'session_id' => $session->sessionId,
                'error'      => $e->getMessage(),
            ]);
        }

        return $session;
    }

    private function findByCheckoutRequestId(string $checkoutRequestId): ?CheckoutSession
    {
        // We store a secondary index: checkoutRequestId -> sessionId
        $sessionId = $this->cache->get(self::CACHE_PREFIX . 'req:' . $checkoutRequestId);

        if (!$sessionId) {
            return null;
        }

        return $this->find($sessionId);
    }

    private function persist(CheckoutSession $session): void
    {
        $ttl = max(60, $session->expiresAt->getTimestamp() - time() + 60);

        $this->cache->put(
            self::CACHE_PREFIX . $session->sessionId,
            $session->jsonSerialize(),
            $ttl,
        );

        // Secondary index for webhook resolution
        if ($session->checkoutRequestId !== null) {
            $this->cache->put(
                self::CACHE_PREFIX . 'req:' . $session->checkoutRequestId,
                $session->sessionId,
                $ttl,
            );
        }
    }

    private function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // 07xxxxxxxx -> 2547xxxxxxxx
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '254' . substr($digits, 1);
        }

        // +254 -> 254
        if (str_starts_with($digits, '+')) {
            $digits = ltrim($digits, '+');
        }

        return $digits;
    }

    private function defaultCallbackUrl(): string
    {
        $prefix = $this->config['route_prefix'] ?? 'mpesa-checkout';

        return url($prefix . '/webhook');
    }
}
