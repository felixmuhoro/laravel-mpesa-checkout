<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCheckout\View\Components;

use Illuminate\View\Component;

class MpesaCheckoutModal extends Component
{
    public function __construct(
        public readonly string    $modalId,
        public readonly int|float $amount,
        public readonly string    $reference,
        public readonly string    $description = '',
        public readonly string    $phone       = '',
        public readonly string    $onSuccess   = '',
        public readonly string    $onFail      = '',
    ) {}

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('mpesa-checkout::components.mpesa-checkout-modal');
    }

    public function formattedAmount(): string
    {
        return 'KES ' . number_format((float) $this->amount, 2);
    }

    public function initiateUrl(): string
    {
        return route('mpesa-checkout.initiate');
    }

    public function pollUrl(): string
    {
        return route('mpesa-checkout.poll', ['sessionId' => '__SESSION_ID__']);
    }

    public function cancelUrl(): string
    {
        return route('mpesa-checkout.cancel', ['sessionId' => '__SESSION_ID__']);
    }
}
