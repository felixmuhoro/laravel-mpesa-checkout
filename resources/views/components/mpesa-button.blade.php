{{-- resources/views/components/mpesa-button.blade.php --}}
@php
    $sizeClasses = match($size) {
        'sm'    => 'px-4 py-2 text-sm',
        'lg'    => 'px-8 py-4 text-lg',
        default => 'px-6 py-3 text-base',
    };
@endphp

<button
    type="button"
    class="mpesa-btn {{ $sizeClasses }} {{ $class }}"
    data-modal-target="{{ $modalId }}"
    onclick="MpesaCheckout.openModal('{{ $modalId }}')"
    aria-haspopup="dialog"
>
    <span class="mpesa-btn__logo" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="20" cy="20" r="20" fill="#ffffff" fill-opacity="0.2"/>
            <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle"
                  font-family="Arial,sans-serif" font-weight="900" font-size="16" fill="#ffffff">M</text>
        </svg>
    </span>
    <span class="mpesa-btn__text">{{ $label }}</span>
    @if($amount)
        <span class="mpesa-btn__amount">{{ $formattedAmount() }}</span>
    @endif
</button>

<x-mpesa-checkout-modal
    :modal-id="$modalId"
    :amount="$amount"
    :reference="$reference"
    :description="$description"
    :phone="$phone"
    :on-success="$onSuccess"
    :on-fail="$onFail"
/>

@once
    @push('styles')
    <style>
        .mpesa-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #00a651 0%, #007a3d 100%);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            box-shadow: 0 2px 8px rgba(0, 166, 81, 0.35);
            letter-spacing: 0.01em;
            user-select: none;
        }
        .mpesa-btn:hover {
            background: linear-gradient(135deg, #00b85a 0%, #008a44 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(0, 166, 81, 0.45);
        }
        .mpesa-btn:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(0, 166, 81, 0.3); }
        .mpesa-btn:focus-visible { outline: 3px solid rgba(0, 166, 81, 0.5); outline-offset: 2px; }
        .mpesa-btn__logo { flex-shrink: 0; }
        .mpesa-btn__amount {
            font-size: 0.85em;
            background: rgba(255,255,255,0.2);
            border-radius: 100px;
            padding: 0.1em 0.6em;
            margin-left: 0.25rem;
        }
    </style>
    @endpush
@endonce
