<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCheckout;

enum CheckoutStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Failed     = 'failed';
    case Expired    = 'expired';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match () {
            self::Pending    => 'Waiting for payment prompt…',
            self::Processing => 'Check your phone and enter your PIN',
            self::Completed  => 'Payment successful!',
            self::Failed     => 'Payment failed',
            self::Expired    => 'Session expired',
            self::Cancelled  => 'Payment cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return match () {
            self::Completed,
            self::Failed,
            self::Expired,
            self::Cancelled => true,
            default          => false,
        };
    }

    public function isSuccess(): bool
    {
        return  === self::Completed;
    }
}
