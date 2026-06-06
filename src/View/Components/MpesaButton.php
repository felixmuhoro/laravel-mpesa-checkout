<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCheckout\View\Components;

use Illuminate\View\Component;

class MpesaButton extends Component
{
    public string $modalId;

    public function __construct(
        public readonly int|float $amount,
        public readonly string    $reference,
        public readonly string    $description = '',
        public readonly string    $phone       = '',
        public readonly string    $label       = 'Pay with M-Pesa',
        public readonly string    $onSuccess   = '',
        public readonly string    $onFail      = '',
        public readonly string    $class       = '',
        public readonly string    $size        = 'md',   // sm | md | lg
    ) {
        $this->modalId = 'mpesa-modal-' . uniqid();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('mpesa-checkout::components.mpesa-button');
    }

    public function formattedAmount(): string
    {
        return 'KES ' . number_format((float) $this->amount, 2);
    }
}
