{{-- resources/views/components/mpesa-checkout-modal.blade.php --}}
<div
    id="{{ $modalId }}"
    class="mpesa-overlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $modalId }}-title"
    aria-hidden="true"
    data-amount="{{ $amount }}"
    data-reference="{{ $reference }}"
    data-description="{{ $description }}"
    data-phone="{{ $phone }}"
    data-initiate-url="{{ route('mpesa-checkout.initiate') }}"
    data-poll-url="{{ route('mpesa-checkout.poll', ['sessionId' => '__SESSION_ID__']) }}"
    data-cancel-url="{{ route('mpesa-checkout.cancel', ['sessionId' => '__SESSION_ID__']) }}"
    data-on-success="{{ $onSuccess }}"
    data-on-fail="{{ $onFail }}"
    data-poll-interval="{{ config('mpesa-checkout.poll_interval_ms', 3000) }}"
    data-poll-timeout="{{ config('mpesa-checkout.poll_timeout_ms', 120000) }}"
>
    <div class="mpesa-modal" role="document">

        {{-- Header --}}
        <header class="mpesa-modal__header">
            <div class="mpesa-modal__brand">
                @if(config('mpesa-checkout.brand_logo'))
                    <img src="{{ config('mpesa-checkout.brand_logo') }}" alt="{{ config('mpesa-checkout.brand_name') }}" class="mpesa-modal__logo">
                @endif
                <span class="mpesa-modal__brand-name">{{ config('mpesa-checkout.brand_name') }}</span>
            </div>
            <button
                type="button"
                class="mpesa-modal__close"
                aria-label="Close"
                onclick="MpesaCheckout.closeModal('{{ $modalId }}')"
            >
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M1 1l16 16M17 1L1 17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </header>

        {{-- M-Pesa secure badge --}}
        <div class="mpesa-modal__mpesa-badge">
            <div class="mpesa-badge">
                <svg class="mpesa-badge__icon" viewBox="0 0 32 32" fill="none">
                    <circle cx="16" cy="16" r="16" fill="#00a651"/>
                    <text x="50%" y="57%" dominant-baseline="middle" text-anchor="middle"
                          font-family="Arial Black,Arial,sans-serif" font-weight="900" font-size="10" fill="#fff">M-Pesa</text>
                </svg>
                <span class="mpesa-badge__label">Secure M-Pesa Payment</span>
                <svg class="mpesa-badge__lock" viewBox="0 0 16 16" fill="none">
                    <rect x="3" y="7" width="10" height="7" rx="1.5" fill="#00a651"/>
                    <path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke="#00a651" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
        </div>

        {{-- Amount display --}}
        <div class="mpesa-modal__amount">
            <span class="mpesa-modal__amount-currency">KES</span>
            <span class="mpesa-modal__amount-value">{{ number_format((float)$amount, 2) }}</span>
        </div>
        <p class="mpesa-modal__description">{{ $description ?: $reference }}</p>

        {{-- STATE: phone-input --}}
        <div class="mpesa-state" data-state="phone-input">
            <div class="mpesa-form">
                <label class="mpesa-label" for="{{ $modalId }}-phone">M-Pesa Phone Number</label>
                <div class="mpesa-phone-wrap">
                    <span class="mpesa-phone-flag" aria-hidden="true">🇰🇪 +254</span>
                    <input
                        id="{{ $modalId }}-phone"
                        type="tel"
                        class="mpesa-input"
                        placeholder="712 345 678"
                        value="{{ $phone ? preg_replace('/^(\+?254|0)/', '', $phone) : '' }}"
                        maxlength="9"
                        autocomplete="tel-national"
                        inputmode="numeric"
                    >
                </div>
                <p class="mpesa-hint">You will receive an STK Push prompt on this number.</p>
                <div class="mpesa-error" style="display:none;" aria-live="polite"></div>
            </div>

            <button type="button" class="mpesa-submit-btn" onclick="MpesaCheckout.initiatePayment('{{ $modalId }}')">
                <span class="mpesa-submit-btn__text">Send Payment Request</span>
                <svg class="mpesa-submit-btn__arrow" viewBox="0 0 20 20" fill="none">
                    <path d="M4 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        {{-- STATE: processing --}}
        <div class="mpesa-state" data-state="processing" style="display:none;">
            <div class="mpesa-processing">
                <div class="mpesa-spinner" aria-hidden="true">
                    <svg viewBox="0 0 50 50">
                        <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="#e8f5ee"/>
                        <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="#00a651"
                                stroke-dasharray="80 126" stroke-linecap="round">
                            <animateTransform attributeName="transform" type="rotate"
                                              dur="1s" from="0 25 25" to="360 25 25" repeatCount="indefinite"/>
                        </circle>
                    </svg>
                </div>
                <h3 class="mpesa-processing__title">Check your phone</h3>
                <p class="mpesa-processing__sub">Enter your M-Pesa PIN on the prompt sent to</p>
                <p class="mpesa-processing__phone" data-bind="display-phone"></p>
                <div class="mpesa-timer">
                    <div class="mpesa-timer__bar-wrap">
                        <div class="mpesa-timer__bar" data-bind="timer-bar"></div>
                    </div>
                    <span class="mpesa-timer__label" data-bind="timer-label">2:00</span>
                </div>
                <button type="button" class="mpesa-cancel-link" onclick="MpesaCheckout.cancelPayment('{{ $modalId }}')">
                    Cancel
                </button>
            </div>
        </div>

        {{-- STATE: success --}}
        <div class="mpesa-state" data-state="success" style="display:none;">
            <div class="mpesa-result mpesa-result--success">
                <div class="mpesa-result__icon">
                    <svg viewBox="0 0 52 52" fill="none">
                        <circle cx="26" cy="26" r="25" fill="#00a651"/>
                        <path d="M14 27l9 9 16-16" stroke="#fff" stroke-width="3.5"
                              stroke-linecap="round" stroke-linejoin="round"
                              stroke-dasharray="40" stroke-dashoffset="40">
                            <animate attributeName="stroke-dashoffset" from="40" to="0" dur="0.4s" fill="freeze" begin="0.1s"/>
                        </path>
                    </svg>
                </div>
                <h3 class="mpesa-result__title">Payment Successful!</h3>
                <p class="mpesa-result__sub">Your payment has been confirmed.</p>
                <div class="mpesa-receipt" data-bind="receipt-block" style="display:none;">
                    <span class="mpesa-receipt__label">M-Pesa Receipt</span>
                    <span class="mpesa-receipt__code" data-bind="receipt-number"></span>
                </div>
                <button type="button" class="mpesa-done-btn" onclick="MpesaCheckout.closeModal('{{ $modalId }}')">Done</button>
            </div>
        </div>

        {{-- STATE: failed --}}
        <div class="mpesa-state" data-state="failed" style="display:none;">
            <div class="mpesa-result mpesa-result--failed">
                <div class="mpesa-result__icon">
                    <svg viewBox="0 0 52 52" fill="none">
                        <circle cx="26" cy="26" r="25" fill="#ef4444"/>
                        <path d="M18 18l16 16M34 18L18 34" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <h3 class="mpesa-result__title">Payment Failed</h3>
                <p class="mpesa-result__sub" data-bind="failure-reason">The payment could not be completed.</p>
                <div class="mpesa-result__actions">
                    <button type="button" class="mpesa-retry-btn" onclick="MpesaCheckout.retryPayment('{{ $modalId }}')">Try Again</button>
                    <button type="button" class="mpesa-cancel-link" onclick="MpesaCheckout.closeModal('{{ $modalId }}')">Cancel</button>
                </div>
            </div>
        </div>

        {{-- STATE: expired --}}
        <div class="mpesa-state" data-state="expired" style="display:none;">
            <div class="mpesa-result mpesa-result--expired">
                <div class="mpesa-result__icon">
                    <svg viewBox="0 0 52 52" fill="none">
                        <circle cx="26" cy="26" r="25" fill="#f59e0b"/>
                        <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle"
                              font-family="Arial,sans-serif" font-weight="900" font-size="26" fill="#fff">!</text>
                    </svg>
                </div>
                <h3 class="mpesa-result__title">Session Expired</h3>
                <p class="mpesa-result__sub">No response was received in time.</p>
                <div class="mpesa-result__actions">
                    <button type="button" class="mpesa-retry-btn" onclick="MpesaCheckout.retryPayment('{{ $modalId }}')">Try Again</button>
                    <button type="button" class="mpesa-cancel-link" onclick="MpesaCheckout.closeModal('{{ $modalId }}')">Cancel</button>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <footer class="mpesa-modal__footer">
            <svg width="12" height="14" viewBox="0 0 12 14" fill="none" aria-hidden="true">
                <rect x="1" y="5" width="10" height="8" rx="1.5" fill="#9ca3af"/>
                <path d="M3.5 5V3.5a2.5 2.5 0 015 0V5" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Secured by Safaricom M-Pesa
        </footer>
    </div>
</div>

@once
    @push('styles')
    <style>
        :root {
            --mpesa-green:    #00a651;
            --mpesa-green-dk: #007a3d;
            --mpesa-green-lt: #e8f5ee;
            --mpesa-red:      #ef4444;
            --mpesa-amber:    #f59e0b;
            --mpesa-text:     #111827;
            --mpesa-muted:    #6b7280;
            --mpesa-border:   #e5e7eb;
            --mpesa-bg:       #ffffff;
            --mpesa-radius:   1rem;
            --mpesa-shadow:   0 24px 64px rgba(0,0,0,0.18);
            --mpesa-t:        0.22s ease;
        }

        .mpesa-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            visibility: hidden;
            transition: opacity var(--mpesa-t), backdrop-filter var(--mpesa-t), background var(--mpesa-t), visibility 0s linear var(--mpesa-t);
        }
        .mpesa-overlay.is-open {
            opacity: 1;
            visibility: visible;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(4px);
            transition: opacity var(--mpesa-t), backdrop-filter var(--mpesa-t), background var(--mpesa-t);
        }

        .mpesa-modal {
            background: var(--mpesa-bg);
            border-radius: var(--mpesa-radius);
            box-shadow: var(--mpesa-shadow);
            width: 100%;
            max-width: 400px;
            max-height: 90dvh;
            overflow-y: auto;
            transform: translateY(16px) scale(0.97);
            opacity: 0;
            transition: transform var(--mpesa-t), opacity var(--mpesa-t);
        }
        .mpesa-overlay.is-open .mpesa-modal {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        @media (max-width: 480px) {
            .mpesa-overlay { align-items: flex-end; padding: 0; }
            .mpesa-modal   { border-radius: var(--mpesa-radius) var(--mpesa-radius) 0 0; max-height: 92dvh; }
        }

        .mpesa-modal__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.1rem 1.25rem 0;
        }
        .mpesa-modal__brand { display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; }
        .mpesa-modal__logo  { height: 24px; border-radius: 4px; }
        .mpesa-modal__close {
            width: 32px; height: 32px;
            border: none; background: #f3f4f6; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--mpesa-muted);
            transition: background var(--mpesa-t), color var(--mpesa-t);
        }
        .mpesa-modal__close:hover { background: #e5e7eb; color: var(--mpesa-text); }

        .mpesa-modal__mpesa-badge { padding: 0.75rem 1.25rem 0; }
        .mpesa-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--mpesa-green-lt);
            border: 1px solid rgba(0,166,81,0.2);
            border-radius: 100px;
            padding: 0.3rem 0.75rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--mpesa-green-dk);
        }
        .mpesa-badge__icon { width: 20px; height: 20px; }
        .mpesa-badge__lock { width: 14px; height: 14px; }

        .mpesa-modal__amount {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 0.35rem;
            padding: 1.25rem 1.25rem 0;
        }
        .mpesa-modal__amount-currency { font-size: 1.1rem; font-weight: 700; color: var(--mpesa-muted); }
        .mpesa-modal__amount-value { font-size: 2.6rem; font-weight: 800; color: var(--mpesa-text); letter-spacing: -0.03em; line-height: 1; }
        .mpesa-modal__description { text-align: center; font-size: 0.85rem; color: var(--mpesa-muted); padding: 0.25rem 1.25rem 0; margin: 0; }

        .mpesa-form { padding: 1.25rem; }
        .mpesa-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--mpesa-muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.5rem; }
        .mpesa-phone-wrap {
            display: flex; align-items: center;
            border: 2px solid var(--mpesa-border); border-radius: 0.6rem; overflow: hidden;
            transition: border-color var(--mpesa-t), box-shadow var(--mpesa-t);
        }
        .mpesa-phone-wrap:focus-within { border-color: var(--mpesa-green); box-shadow: 0 0 0 3px rgba(0,166,81,0.12); }
        .mpesa-phone-flag { padding: 0 0.75rem; background: #f9fafb; border-right: 2px solid var(--mpesa-border); font-size: 0.9rem; white-space: nowrap; height: 48px; display: flex; align-items: center; }
        .mpesa-input { flex: 1; border: none; outline: none; padding: 0 0.75rem; height: 48px; font-size: 1rem; font-weight: 500; letter-spacing: 0.05em; }
        .mpesa-hint { font-size: 0.78rem; color: var(--mpesa-muted); margin: 0.5rem 0 0; }
        .mpesa-error { font-size: 0.8rem; color: var(--mpesa-red); margin-top: 0.5rem; padding: 0.5rem 0.75rem; background: #fef2f2; border-radius: 0.4rem; border-left: 3px solid var(--mpesa-red); }

        .mpesa-submit-btn {
            width: calc(100% - 2.5rem);
            margin: 0 1.25rem 1.25rem;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            height: 52px;
            border: none; border-radius: 0.65rem;
            background: linear-gradient(135deg, var(--mpesa-green) 0%, var(--mpesa-green-dk) 100%);
            color: #fff; font-size: 1rem; font-weight: 700; cursor: pointer;
            transition: transform var(--mpesa-t), box-shadow var(--mpesa-t);
            box-shadow: 0 4px 14px rgba(0,166,81,0.4);
        }
        .mpesa-submit-btn:hover  { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,166,81,0.5); }
        .mpesa-submit-btn:active { transform: translateY(0); }
        .mpesa-submit-btn:disabled { opacity: 0.6; pointer-events: none; }
        .mpesa-submit-btn__arrow { width: 20px; height: 20px; }

        .mpesa-processing { padding: 2rem 1.25rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
        .mpesa-spinner { width: 64px; height: 64px; margin-bottom: 0.5rem; }
        .mpesa-processing__title { font-size: 1.3rem; font-weight: 800; color: var(--mpesa-text); margin: 0; }
        .mpesa-processing__sub   { font-size: 0.9rem; color: var(--mpesa-muted); margin: 0; }
        .mpesa-processing__phone { font-size: 1.05rem; font-weight: 700; color: var(--mpesa-text); margin: 0; }

        .mpesa-timer { width: 100%; max-width: 240px; margin-top: 0.75rem; }
        .mpesa-timer__bar-wrap { height: 4px; background: var(--mpesa-border); border-radius: 100px; overflow: hidden; }
        .mpesa-timer__bar { height: 100%; background: var(--mpesa-green); border-radius: 100px; width: 100%; transition: width 1s linear; }
        .mpesa-timer__label { display: block; text-align: right; font-size: 0.78rem; color: var(--mpesa-muted); margin-top: 0.25rem; font-variant-numeric: tabular-nums; }

        .mpesa-result { padding: 2rem 1.25rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
        .mpesa-result__icon { width: 64px; height: 64px; margin-bottom: 0.25rem; }
        .mpesa-result__title { font-size: 1.3rem; font-weight: 800; color: var(--mpesa-text); margin: 0; }
        .mpesa-result__sub   { font-size: 0.9rem; color: var(--mpesa-muted); margin: 0; max-width: 240px; }
        .mpesa-result__actions { display: flex; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap; justify-content: center; }

        .mpesa-receipt { background: var(--mpesa-green-lt); border: 1px solid rgba(0,166,81,0.25); border-radius: 0.5rem; padding: 0.6rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; margin-top: 0.25rem; }
        .mpesa-receipt__label { font-size: 0.75rem; font-weight: 600; color: var(--mpesa-green-dk); text-transform: uppercase; letter-spacing: 0.05em; }
        .mpesa-receipt__code  { font-size: 1rem; font-weight: 800; letter-spacing: 0.1em; }

        .mpesa-done-btn, .mpesa-retry-btn { padding: 0.65rem 1.75rem; border: none; border-radius: 0.5rem; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: transform var(--mpesa-t), box-shadow var(--mpesa-t); }
        .mpesa-done-btn  { background: var(--mpesa-green); color: #fff; box-shadow: 0 3px 10px rgba(0,166,81,0.35); margin-top: 0.5rem; }
        .mpesa-done-btn:hover  { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(0,166,81,0.45); }
        .mpesa-retry-btn { background: var(--mpesa-text); color: #fff; box-shadow: 0 3px 10px rgba(0,0,0,0.15); }
        .mpesa-retry-btn:hover { transform: translateY(-1px); }
        .mpesa-cancel-link { background: none; border: none; color: var(--mpesa-muted); font-size: 0.9rem; cursor: pointer; text-decoration: underline; text-underline-offset: 2px; padding: 0.65rem 0.75rem; }
        .mpesa-cancel-link:hover { color: var(--mpesa-text); }

        .mpesa-modal__footer { display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.6rem; border-top: 1px solid var(--mpesa-border); font-size: 0.72rem; color: #9ca3af; margin-top: 0.25rem; }
    </style>
    @endpush

    @push('scripts')
    <script src="{{ asset('vendor/mpesa-checkout/mpesa-checkout.js') }}"></script>
    @endpush
@endonce
