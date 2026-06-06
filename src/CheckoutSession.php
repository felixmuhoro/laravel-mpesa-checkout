<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCheckout;

use DateTimeImmutable;
use JsonSerializable;

final class CheckoutSession implements JsonSerializable
{
    public function __construct(
        public readonly string            $sessionId,
        public readonly int|float         $amount,
        public readonly string            $phone,
        public readonly string            $reference,
        public readonly string            $description,
        public readonly string            $callbackUrl,
        public readonly CheckoutStatus    $status,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $expiresAt,
        public readonly ?string           $checkoutRequestId = null,
        public readonly ?string           $merchantRequestId = null,
        public readonly ?string           $mpesaReceiptNumber = null,
        public readonly ?string           $failureReason = null,
    ) {}

    public static function make(
        int|float $amount,
        string    $phone,
        string    $reference,
        string    $description,
        string    $callbackUrl,
        int       $ttlSeconds = 300,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            sessionId:   self::generateSessionId(),
            amount:      $amount,
            phone:       $phone,
            reference:   $reference,
            description: $description,
            callbackUrl: $callbackUrl,
            status:      CheckoutStatus::Pending,
            createdAt:   $now,
            expiresAt:   $now->modify('+' . $ttlSeconds . ' seconds'),
        );
    }

    public function withStatus(CheckoutStatus $status): self
    {
        return new self(
            sessionId:          $this->sessionId,
            amount:             $this->amount,
            phone:              $this->phone,
            reference:          $this->reference,
            description:        $this->description,
            callbackUrl:        $this->callbackUrl,
            status:             $status,
            createdAt:          $this->createdAt,
            expiresAt:          $this->expiresAt,
            checkoutRequestId:  $this->checkoutRequestId,
            merchantRequestId:  $this->merchantRequestId,
            mpesaReceiptNumber: $this->mpesaReceiptNumber,
            failureReason:      $this->failureReason,
        );
    }

    public function withStkIds(string $checkoutRequestId, string $merchantRequestId): self
    {
        return new self(
            sessionId:          $this->sessionId,
            amount:             $this->amount,
            phone:              $this->phone,
            reference:          $this->reference,
            description:        $this->description,
            callbackUrl:        $this->callbackUrl,
            status:             $this->status,
            createdAt:          $this->createdAt,
            expiresAt:          $this->expiresAt,
            checkoutRequestId:  $checkoutRequestId,
            merchantRequestId:  $merchantRequestId,
            mpesaReceiptNumber: $this->mpesaReceiptNumber,
            failureReason:      $this->failureReason,
        );
    }

    public function withResult(string $receiptNumber): self
    {
        return new self(
            sessionId:          $this->sessionId,
            amount:             $this->amount,
            phone:              $this->phone,
            reference:          $this->reference,
            description:        $this->description,
            callbackUrl:        $this->callbackUrl,
            status:             CheckoutStatus::Completed,
            createdAt:          $this->createdAt,
            expiresAt:          $this->expiresAt,
            checkoutRequestId:  $this->checkoutRequestId,
            merchantRequestId:  $this->merchantRequestId,
            mpesaReceiptNumber: $receiptNumber,
            failureReason:      null,
        );
    }

    public function withFailure(string $reason): self
    {
        return new self(
            sessionId:          $this->sessionId,
            amount:             $this->amount,
            phone:              $this->phone,
            reference:          $this->reference,
            description:        $this->description,
            callbackUrl:        $this->callbackUrl,
            status:             CheckoutStatus::Failed,
            createdAt:          $this->createdAt,
            expiresAt:          $this->expiresAt,
            checkoutRequestId:  $this->checkoutRequestId,
            merchantRequestId:  $this->merchantRequestId,
            mpesaReceiptNumber: null,
            failureReason:      $reason,
        );
    }

    public function isExpired(): bool
    {
        return new DateTimeImmutable() > $this->expiresAt;
    }

    public function jsonSerialize(): array
    {
        return [
            'session_id'          => $this->sessionId,
            'amount'              => $this->amount,
            'phone'               => $this->phone,
            'reference'           => $this->reference,
            'description'         => $this->description,
            'callback_url'        => $this->callbackUrl,
            'status'              => $this->status->value,
            'created_at'          => $this->createdAt->format(DateTimeImmutable::ATOM),
            'expires_at'          => $this->expiresAt->format(DateTimeImmutable::ATOM),
            'checkout_request_id' => $this->checkoutRequestId,
            'merchant_request_id' => $this->merchantRequestId,
            'mpesa_receipt'       => $this->mpesaReceiptNumber,
            'failure_reason'      => $this->failureReason,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sessionId:          $data['session_id'],
            amount:             $data['amount'],
            phone:              $data['phone'],
            reference:          $data['reference'],
            description:        $data['description'],
            callbackUrl:        $data['callback_url'],
            status:             CheckoutStatus::from($data['status']),
            createdAt:          new DateTimeImmutable($data['created_at']),
            expiresAt:          new DateTimeImmutable($data['expires_at']),
            checkoutRequestId:  $data['checkout_request_id'] ?? null,
            merchantRequestId:  $data['merchant_request_id'] ?? null,
            mpesaReceiptNumber: $data['mpesa_receipt'] ?? null,
            failureReason:      $data['failure_reason'] ?? null,
        );
    }

    private static function generateSessionId(): string
    {
        return 'mpc_' . bin2hex(random_bytes(16));
    }
}
