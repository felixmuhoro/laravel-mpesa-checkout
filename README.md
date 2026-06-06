# laravel-mpesa-checkout

Drop-in M-Pesa STK Push checkout UI for Laravel — a Blade component + controller that handles the complete payment flow with JS polling.

## Requirements

- PHP 8.1+
- Laravel 10 / 11 / 12 / 13
- [`felixmuhoro/laravel-mpesa`](https://github.com/felixmuhoro/laravel-mpesa) ^1.2

## Installation

```bash
composer require felixmuhoro/laravel-mpesa-checkout
```

The service provider registers automatically via Laravel's package discovery.

Publish the config:

```bash
php artisan vendor:publish --tag=mpesa-checkout-config
```

Publish assets (JS):

```bash
php artisan vendor:publish --tag=mpesa-checkout-assets
```

## Configuration

`config/mpesa-checkout.php` (with `.env` overrides):

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `session_ttl` | `MPESA_CHECKOUT_TTL` | `300` | Session lifetime in seconds |
| `poll_interval_ms` | `MPESA_CHECKOUT_POLL_INTERVAL` | `3000` | JS poll interval (ms) |
| `poll_timeout_ms` | `MPESA_CHECKOUT_POLL_TIMEOUT` | `120000` | JS timeout before "expired" (ms) |
| `route_prefix` | `MPESA_CHECKOUT_ROUTE_PREFIX` | `mpesa-checkout` | URL prefix for package routes |
| `cache_store` | `MPESA_CHECKOUT_CACHE_STORE` | `null` (default) | Cache driver for sessions |
| `webhook_secret` | `MPESA_CHECKOUT_WEBHOOK_SECRET` | `null` | HMAC secret for callback verification |
| `brand_name` | `APP_NAME` | `My App` | Shown in the modal header |
| `brand_logo` | `MPESA_CHECKOUT_LOGO` | `null` | URL to your logo |
| `redirect_success` | `MPESA_CHECKOUT_SUCCESS_URL` | `/` | Page-flow success redirect |
| `redirect_cancel` | `MPESA_CHECKOUT_CANCEL_URL` | `/` | Page-flow cancel redirect |

## Usage

### Blade Component (modal flow)

Add to your layout — the button opens a modal, handles everything in-page:

```blade
{{-- In your layout head / bottom of body --}}
@stack('styles')
@stack('scripts')

{{-- Anywhere on the page --}}
<x-mpesa-button
    :amount="1500"
    reference="INV-2024-001"
    description="Order payment"
    :phone="auth()->user()->phone ?? ''"
    on-success="handleMpesaSuccess"
    on-fail="handleMpesaFail"
    label="Pay KES 1,500"
    size="lg"
/>
```

JavaScript callbacks (optional):

```js
function handleMpesaSuccess(detail) {
    console.log('Paid! Receipt:', detail.receipt);
    window.location.reload();
}

function handleMpesaFail(detail) {
    console.log('Failed:', detail.reason || detail.status);
}
```

You can also listen to DOM events:

```js
document.addEventListener('mpesa:success', e => {
    console.log('receipt:', e.detail.receipt);
});
document.addEventListener('mpesa:fail', e => {
    console.log('reason:', e.detail.reason);
});
```

### Standalone Page Flow

Redirect the user to the checkout page:

```php
return redirect()->route('mpesa-checkout.page', [
    'amount'      => 1500,
    'reference'   => 'INV-2024-001',
    'description' => 'Order payment',
    'phone'       => $user->phone,
]);
```

Set `MPESA_CHECKOUT_SUCCESS_URL` and `MPESA_CHECKOUT_CANCEL_URL` to control where the user lands after payment.

### Programmatic API

```php
use FelixMuhoro\MpesaCheckout\CheckoutManager;

class OrderController extends Controller
{
    public function pay(Request $request, CheckoutManager $checkout)
    {
        $session = $checkout->initiate(
            amount:      1500,
            phone:       '0712345678',
            reference:   'INV-001',
            description: 'Order #1234',
        );

        return response()->json([
            'session_id' => $session->sessionId,
            'status'     => $session->status->value,
        ]);
    }
}
```

## Routes

| Method | Path | Name | Description |
|--------|------|------|-------------|
| POST | `/mpesa-checkout/initiate` | `mpesa-checkout.initiate` | Start STK Push |
| GET | `/mpesa-checkout/poll/{sessionId}` | `mpesa-checkout.poll` | JS polling endpoint |
| POST | `/mpesa-checkout/webhook` | `mpesa-checkout.webhook` | M-Pesa callback URL |
| DELETE | `/mpesa-checkout/{sessionId}` | `mpesa-checkout.cancel` | Cancel session |
| GET | `/mpesa-checkout/pay` | `mpesa-checkout.page` | Standalone checkout page |

### Webhook / Callback URL

Point your Safaricom Daraja app's **Confirmation URL** to:

```
https://yourdomain.com/mpesa-checkout/webhook
```

This URL is automatically excluded from CSRF verification.

## Checkout Flow

```
User clicks button
    -> JS POSTs /mpesa-checkout/initiate
    -> Safaricom sends STK Push to phone
    -> Modal shows "Check your phone" + countdown
    -> JS polls /mpesa-checkout/poll every 3 s
    -> On result: success or fail state shown
    -> Optional JS callback / DOM event fired
```

The poll endpoint also calls `stkQuery` against Safaricom on each poll, so it works even when the M-Pesa webhook callback is delayed or unreliable.

## Customising Views

```bash
php artisan vendor:publish --tag=mpesa-checkout-views
```

Views are published to `resources/views/vendor/mpesa-checkout/`.

## Testing

```bash
composer test
```

## License

MIT - Felix Muhoro
