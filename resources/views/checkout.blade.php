<!DOCTYPE html>
<html lang="{{ config('mpesa-checkout.locale', 'en') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pay {{ number_format((float)$amount, 2) }} KES &mdash; {{ config('mpesa-checkout.brand_name') }}</title>

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #f8fafc 100%);
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: #111827;
        }

        .page-card {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        .page-card__top {
            background: linear-gradient(135deg, #00a651, #007a3d);
            padding: 1.75rem 1.5rem 1.5rem;
            color: #fff;
            text-align: center;
        }
        .page-card__mpesa-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.18);
            border-radius: 100px;
            padding: 0.3rem 0.9rem;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .page-card__brand   { font-size: 0.85rem; opacity: 0.8; margin-bottom: 0.5rem; }
        .page-card__amount  { font-size: 3rem; font-weight: 900; letter-spacing: -0.04em; line-height: 1; }
        .page-card__amount-currency { font-size: 1.25rem; vertical-align: super; margin-right: 0.1em; font-weight: 700; }
        .page-card__description { margin-top: 0.4rem; font-size: 0.9rem; opacity: 0.85; }

        .page-card__body { padding: 1.75rem 1.5rem; }

        [data-state] { display: none; }
        [data-state].active { display: block; }

        .form-group { margin-bottom: 1.25rem; }
        .form-label  { display: block; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-bottom: 0.5rem; }
        .phone-wrap  { display: flex; align-items: center; border: 2px solid #e5e7eb; border-radius: 0.6rem; overflow: hidden; transition: border-color 0.18s, box-shadow 0.18s; }
        .phone-wrap:focus-within { border-color: #00a651; box-shadow: 0 0 0 3px rgba(0,166,81,0.12); }
        .phone-prefix { padding: 0 0.75rem; background: #f9fafb; border-right: 2px solid #e5e7eb; height: 50px; display: flex; align-items: center; font-size: 0.9rem; white-space: nowrap; }
        .phone-input  { flex: 1; border: none; outline: none; padding: 0 0.75rem; height: 50px; font-size: 1rem; font-weight: 500; letter-spacing: 0.04em; }
        .form-hint { font-size: 0.78rem; color: #9ca3af; margin-top: 0.4rem; }
        .error-msg { display: none; font-size: 0.82rem; color: #ef4444; background: #fef2f2; border-left: 3px solid #ef4444; padding: 0.5rem 0.75rem; border-radius: 0.4rem; margin-top: 0.5rem; }

        .submit-btn {
            width: 100%; height: 54px;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            border: none; border-radius: 0.65rem;
            background: linear-gradient(135deg, #00a651, #007a3d);
            color: #fff; font-size: 1rem; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 14px rgba(0,166,81,0.4);
            transition: transform 0.18s, box-shadow 0.18s;
        }
        .submit-btn:hover  { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,166,81,0.5); }
        .submit-btn:active { transform: translateY(0); }
        .submit-btn:disabled { opacity: 0.6; pointer-events: none; }

        .processing-state { text-align: center; padding: 1.5rem 0; }
        .spinner-wrap { display: flex; justify-content: center; margin-bottom: 1rem; }
        .spinner { width: 64px; height: 64px; }
        .processing-title { font-size: 1.3rem; font-weight: 800; margin: 0 0 0.25rem; }
        .processing-sub   { color: #6b7280; font-size: 0.9rem; margin: 0; }
        .processing-phone { font-size: 1.1rem; font-weight: 700; margin: 0.5rem 0 0; }

        .timer-wrap { margin-top: 1.25rem; max-width: 200px; margin-left: auto; margin-right: auto; }
        .timer-bar-track { height: 4px; background: #e5e7eb; border-radius: 100px; overflow: hidden; }
        .timer-bar-fill  { height: 100%; background: #00a651; border-radius: 100px; width: 100%; transition: width 1s linear; }
        .timer-countdown { text-align: right; font-size: 0.78rem; color: #9ca3af; margin-top: 0.25rem; font-variant-numeric: tabular-nums; }

        .cancel-btn { background: none; border: none; color: #9ca3af; font-size: 0.9rem; cursor: pointer; text-decoration: underline; text-underline-offset: 2px; margin-top: 1rem; }
        .cancel-btn:hover { color: #374151; }

        .result-state { text-align: center; padding: 1.5rem 0; }
        .result-icon  { width: 64px; height: 64px; margin: 0 auto 0.75rem; }
        .result-title { font-size: 1.3rem; font-weight: 800; margin: 0 0 0.25rem; }
        .result-sub   { color: #6b7280; font-size: 0.9rem; margin: 0 0 1rem; }

        .receipt-box { display: none; align-items: center; justify-content: center; gap: 0.75rem; background: #ecfdf5; border: 1px solid rgba(0,166,81,0.25); border-radius: 0.5rem; padding: 0.65rem 1.25rem; margin: 0.5rem auto 1rem; }
        .receipt-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #007a3d; }
        .receipt-code  { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.12em; }

        .action-row { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn-primary   { padding: 0.7rem 1.75rem; background: #00a651; color: #fff; border: none; border-radius: 0.5rem; font-size: 0.95rem; font-weight: 700; cursor: pointer; box-shadow: 0 3px 10px rgba(0,166,81,0.35); transition: transform 0.18s, box-shadow 0.18s; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(0,166,81,0.45); }
        .btn-secondary { padding: 0.7rem 1.25rem; background: #f3f4f6; color: #374151; border: none; border-radius: 0.5rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background 0.18s; }
        .btn-secondary:hover { background: #e5e7eb; }

        .page-card__footer { border-top: 1px solid #f3f4f6; padding: 0.65rem 1.5rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem; font-size: 0.72rem; color: #9ca3af; }
    </style>
</head>
<body>

<div class="page-card">

    <div class="page-card__top">
        <div class="page-card__mpesa-badge">
            <svg width="16" height="16" viewBox="0 0 32 32">
                <circle cx="16" cy="16" r="16" fill="rgba(255,255,255,0.3)"/>
                <text x="50%" y="57%" dominant-baseline="middle" text-anchor="middle"
                      font-family="Arial Black,sans-serif" font-weight="900" font-size="11" fill="#fff">M</text>
            </svg>
            M-Pesa Payment
        </div>
        <p class="page-card__brand">{{ config('mpesa-checkout.brand_name') }}</p>
        <div class="page-card__amount">
            <span class="page-card__amount-currency">KES</span>{{ number_format((float)$amount, 2) }}
        </div>
        @if(!empty($description))
            <p class="page-card__description">{{ $description }}</p>
        @endif
    </div>

    <div class="page-card__body"
         id="mpesa-page"
         data-amount="{{ $amount }}"
         data-reference="{{ $reference }}"
         data-description="{{ $description }}"
         data-phone="{{ $phone }}"
         data-initiate-url="{{ route('mpesa-checkout.initiate') }}"
         data-poll-url="{{ route('mpesa-checkout.poll', ['sessionId' => '__SESSION_ID__']) }}"
         data-cancel-url="{{ route('mpesa-checkout.cancel', ['sessionId' => '__SESSION_ID__']) }}"
         data-redirect-success="{{ config('mpesa-checkout.redirect_success') }}"
         data-redirect-cancel="{{ config('mpesa-checkout.redirect_cancel') }}"
         data-poll-interval="{{ config('mpesa-checkout.poll_interval_ms', 3000) }}"
         data-poll-timeout="{{ config('mpesa-checkout.poll_timeout_ms', 120000) }}"
    >

        {{-- Phone input --}}
        <div data-state="phone-input" class="active">
            <div class="form-group">
                <label class="form-label" for="page-phone">M-Pesa Phone Number</label>
                <div class="phone-wrap">
                    <span class="phone-prefix">🇰🇪 +254</span>
                    <input id="page-phone" type="tel" class="phone-input"
                           placeholder="712 345 678"
                           value="{{ $phone ? preg_replace('/^(\+?254|0)/', '', $phone) : '' }}"
                           maxlength="9" inputmode="numeric" autocomplete="tel-national">
                </div>
                <p class="form-hint">Enter the number that will receive the M-Pesa prompt.</p>
                <div class="error-msg" id="page-error"></div>
            </div>
            <button class="submit-btn" id="page-submit-btn" onclick="MpesaCheckout.initiatePage()">
                <span>Send Payment Request</span>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M4 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        {{-- Processing --}}
        <div data-state="processing">
            <div class="processing-state">
                <div class="spinner-wrap">
                    <svg class="spinner" viewBox="0 0 50 50">
                        <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="#e8f5ee"/>
                        <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="#00a651"
                                stroke-dasharray="80 126" stroke-linecap="round">
                            <animateTransform attributeName="transform" type="rotate"
                                              dur="1s" from="0 25 25" to="360 25 25" repeatCount="indefinite"/>
                        </circle>
                    </svg>
                </div>
                <h2 class="processing-title">Check your phone</h2>
                <p class="processing-sub">Enter your M-Pesa PIN on the prompt sent to</p>
                <p class="processing-phone" id="page-display-phone"></p>
                <div class="timer-wrap">
                    <div class="timer-bar-track"><div class="timer-bar-fill" id="page-timer-bar"></div></div>
                    <div class="timer-countdown" id="page-timer-label">2:00</div>
                </div>
                <button class="cancel-btn" onclick="MpesaCheckout.cancelPage()">Cancel payment</button>
            </div>
        </div>

        {{-- Success --}}
        <div data-state="success">
            <div class="result-state">
                <div class="result-icon">
                    <svg viewBox="0 0 52 52" fill="none">
                        <circle cx="26" cy="26" r="25" fill="#00a651"/>
                        <path d="M14 27l9 9 16-16" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"
                              stroke-dasharray="40" stroke-dashoffset="40">
                            <animate attributeName="stroke-dashoffset" from="40" to="0" dur="0.4s" fill="freeze" begin="0.1s"/>
                        </path>
                    </svg>
                </div>
                <h2 class="result-title">Payment Confirmed!</h2>
                <p class="result-sub">Your payment has been processed successfully.</p>
                <div class="receipt-box" id="page-receipt-box">
                    <span class="receipt-label">M-Pesa Receipt</span>
                    <span class="receipt-code" id="page-receipt-number"></span>
                </div>
                <button class="btn-primary" onclick="MpesaCheckout.pageRedirect('success')">Continue</button>
            </div>
        </div>

        {{-- Failed --}}
        <div data-state="failed">
            <div class="result-state">
                <div class="result-icon">
                    <svg viewBox="0 0 52 52" fill="none">
                        <circle cx="26" cy="26" r="25" fill="#ef4444"/>
                        <path d="M18 18l16 16M34 18L18 34" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <h2 class="result-title">Payment Failed</h2>
                <p class="result-sub" id="page-failure-reason">The payment could not be completed.</p>
                <div class="action-row">
                    <button class="btn-primary" onclick="MpesaCheckout.resetPage()">Try Again</button>
                    <button class="btn-secondary" onclick="MpesaCheckout.pageRedirect('cancel')">Cancel</button>
                </div>
            </div>
        </div>

        {{-- Expired --}}
        <div data-state="expired">
            <div class="result-state">
                <div class="result-icon">
                    <svg viewBox="0 0 52 52" fill="none">
                        <circle cx="26" cy="26" r="25" fill="#f59e0b"/>
                        <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle"
                              font-family="Arial,sans-serif" font-weight="900" font-size="26" fill="#fff">!</text>
                    </svg>
                </div>
                <h2 class="result-title">Session Expired</h2>
                <p class="result-sub">No response received within the time limit.</p>
                <div class="action-row">
                    <button class="btn-primary" onclick="MpesaCheckout.resetPage()">Try Again</button>
                    <button class="btn-secondary" onclick="MpesaCheckout.pageRedirect('cancel')">Cancel</button>
                </div>
            </div>
        </div>

    </div>

    <div class="page-card__footer">
        <svg width="12" height="14" viewBox="0 0 12 14" fill="none">
            <rect x="1" y="5" width="10" height="8" rx="1.5" fill="#9ca3af"/>
            <path d="M3.5 5V3.5a2.5 2.5 0 015 0V5" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        Secured by Safaricom M-Pesa
    </div>
</div>

<script>
window._mpesaCsrfToken = '{{ csrf_token() }}';
</script>
<script src="{{ asset('vendor/mpesa-checkout/mpesa-checkout.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    MpesaCheckout.initPage(document.getElementById('mpesa-page'));
});
</script>

</body>
</html>
