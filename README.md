# Laravel Snippe

![Intelfric Technology](watermark.png)


A clean, expressive Laravel package for integrating the **Snippe Tanzania** payment gateway.
Supports **Mobile Money** (Airtel, M-Pesa, Halotel, Mixx), **Bank Transfer**, **Card payments**, **Dynamic QR** codes, and **Payment Sessions** (hosted checkout) — with webhook signature verification and a fluent builder API.

Built by **Intelfric Technology**: https://intelfric.com

## Our Story & Philosophy

### Smart Ideas Born from Real Challenges

We believe the most powerful innovations emerge when intelligence meets resistance. Every challenge we face in Africa becomes a catalyst for creating solutions that work locally and scale to global impact.

### The Ant Philosophy

The ant in our logo is not just a design element, it is a philosophy. Ants represent structure, teamwork, resilience, and purpose. They build complex systems through small, intentional actions, reflecting how we build software: focused, unified, efficient, and mission-driven.

### The Meaning Behind Intelfric

**Intelfric** combines two core ideas:
- **Intel**: intelligence, insight, and innovation.
- **Fric**: friction that sharpens innovation, and Africa as the root of our identity.

Together, the name reflects our belief that smart ideas born from real challenges, deeply rooted in Africa, can create solutions with global impact.

### Core Values

- **Innovation**: we embrace modern technologies and inventive approaches for complex problems.
- **Client-Centric**: we prioritize outcomes, trust, transparency, and long-term partnership.
- **Quality & Excellence**: we deliver robust, scalable, and secure software.
- **Agility**: we move fast and adapt quickly to changing business needs.

### Our Mission

To leverage cutting-edge technology and innovative thinking to deliver custom software solutions that solve real business problems, improve operational efficiency, and accelerate growth across industries.

### Our Vision

To be the leading technology partner in East Africa, recognized for innovative solutions, exceptional quality, and commitment to digital transformation that creates sustainable value for businesses and communities.

---

## Requirements

| Dependency | Version              |
|------------|----------------------|
| PHP        | ^8.2                 |
| Laravel    | ^11.0 \| ^12.0 \| ^13.0 |

---

## Installation

```bash
composer require intelfric-technology/laravel-snippe
```

> Package discovery is automatic — no need to manually register the service provider.

### Publish the config file

```bash
php artisan vendor:publish --tag=snippe-config
```

This creates `config/snippe.php` in your application.

### Add credentials to `.env`

```dotenv
SNIPPE_API_KEY=snp_your_live_key_here
SNIPPE_BASE_URL=https://api.snippe.sh/v1
SNIPPE_WEBHOOK_URL=https://yourapp.com/snippe/webhook
SNIPPE_WEBHOOK_SECRET=your_webhook_signing_secret
SNIPPE_WEBHOOK_PATH=snippe/webhook
SNIPPE_CURRENCY=TZS
SNIPPE_TIMEOUT=30
```

> Find `SNIPPE_WEBHOOK_SECRET` in your Snippe Dashboard → Webhooks → Signing Secret.

### Exclude webhook route from CSRF

In **Laravel 11 / 12 / 13** (`bootstrap/app.php`):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'snippe/*',
    ]);
})
```

Or if you register your own route (see [Custom Webhook Controller](#custom-webhook-controller)):

```php
Route::post('/webhooks/snippe', [SnippeWebhookController::class, 'handle'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
```

---

## Quick Start

```php
use ShadrackJm\Snippe\Facades\Snippe;

// Mobile Money — USSD push sent to customer's phone
$payment = Snippe::mobileMoney(5000, '0754123456')
    ->customer('John Doe', 'john@email.com')
    ->send();

echo $payment->reference(); // "9015c155-9e29-..."
echo $payment->status();    // "pending"
```

---

## Usage

### Payment Sessions (Hosted Checkout) — Recommended

A Payment Session creates a Snippe-hosted checkout page. Redirect your customer there to pay.
This is the recommended approach for subscriptions and one-off charges because you do not need
to handle card/mobile-money UI yourself.

```php
use ShadrackJm\Snippe\Facades\Snippe;

$session = Snippe::session(50000)
    ->customer('John Doe', 'john@email.com')
    ->allowedMethods(['mobile_money', 'bank_transfer']) // restrict payment methods
    ->redirectTo(
        route('subscription.success'),
        route('subscription.cancel')
    )
    ->webhook(route('snippe.webhook'))
    ->metadata(['subscription_id' => 42, 'plan_id' => 3, 'company_id' => 12])
    ->expiresIn(1800) // 30-minute checkout window
    ->send();

// Redirect the customer to Snippe's hosted checkout page
return redirect($session->checkoutUrl());
```

When payment completes, Snippe fires your webhook with the metadata you attached,
allowing you to activate the subscription automatically on the server side.

#### Payment Session Builder Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `session` | `session(?int $amount)` | Start a session (omit amount to allow customer input) |
| `customer` | `->customer(string $name, string $email)` | Attach customer info |
| `allowedMethods` | `->allowedMethods(array $methods)` | Restrict to specific methods: `mobile_money`, `bank_transfer`, `card`, `qr` |
| `redirectTo` | `->redirectTo(string $success, string $cancel)` | URLs Snippe redirects to after payment |
| `webhook` | `->webhook(string $url)` | Override the webhook URL for this session |
| `metadata` | `->metadata(array $data)` | Arbitrary key-value data included in webhook payload |
| `expiresIn` | `->expiresIn(int $seconds)` | Session expiry window (default: 3600) |
| `allowCustomAmount` | `->allowCustomAmount()` | Let the customer enter their own amount (e.g. top-ups) |
| `send` | `->send()` | Execute — returns a `PaymentSession` object |

#### Allow custom amount (e.g. top-ups)

```php
$session = Snippe::session()
    ->allowCustomAmount()
    ->customer('John Doe', 'john@email.com')
    ->redirectTo(route('topup.success'))
    ->send();

return redirect($session->checkoutUrl());
```

---

### Mobile Money

```php
$payment = Snippe::mobileMoney(5000, '0754123456')
    ->customer('John Doe', 'john@email.com')
    ->webhook('https://yourapp.com/snippe/webhook')
    ->metadata(['order_id' => 'ORD-123'])
    ->description('Order #123 from My Shop')
    ->idempotencyKey('order-123-attempt-1')
    ->send();

// The customer receives a USSD push. You get a webhook when done.
```

### Bank Transfer

```php
$session = Snippe::session(100000)
    ->customer('John Doe', 'john@email.com')
    ->allowedMethods(['bank_transfer'])
    ->redirectTo(route('payment.success'), route('payment.cancel'))
    ->metadata(['invoice_id' => 'INV-001'])
    ->send();

return redirect($session->checkoutUrl());
```

### Card Payment

```php
$payment = Snippe::card(10000)
    ->phone('0754123456')
    ->customer('Jane Doe', 'jane@email.com')
    ->billing('123 Main Street', 'Dar es Salaam', 'DSM', '14101', 'TZ')
    ->redirectTo('https://yourapp.com/success', 'https://yourapp.com/cancel')
    ->send();

// Redirect the customer to the secure checkout page
return redirect($payment->paymentUrl());
```

### Dynamic QR Code

```php
$payment = Snippe::qr(5000)
    ->customer('John Doe', 'john@email.com')
    ->redirectTo('https://yourapp.com/success', 'https://yourapp.com/cancel')
    ->send();

$qrData     = $payment->qrCode();    // Render as a QR image
$paymentUrl = $payment->paymentUrl(); // Or redirect to hosted page
```

### Raw Array Payloads

```php
// Generic payment with a full array
$payment = Snippe::createPayment([
    'payment_type' => 'mobile',
    'details'      => ['amount' => 5000, 'currency' => 'TZS'],
    'phone_number' => '255754123456',
    'customer'     => ['firstname' => 'John', 'lastname' => 'Doe', 'email' => 'john@mail.com'],
]);

// Convenience wrapper for mobile payments
$payment = Snippe::initiateMobilePayment([
    'amount'       => 5000,
    'phone_number' => '0754123456',
    'customer'     => ['firstname' => 'John', 'lastname' => 'Doe', 'email' => 'john@mail.com'],
]);
```

---

## Payment Operations

### Verify / Check Status

```php
$payment = Snippe::verifyTransaction('9015c155-9e29-4e8e-8fe6-d5d81553c8e6');
// Or alias:
$payment = Snippe::find('9015c155-...');

echo $payment->status();      // "completed"
echo $payment->amount();      // 5000
echo $payment->completedAt(); // "2026-01-25T00:50:44Z"
```

### List Payments

```php
$result = Snippe::payments(limit: 20, offset: 0);
```

### Account Balance

```php
$balance   = Snippe::balance();
$available = $balance['data']['available']['value'];    // e.g. 6943
$currency  = $balance['data']['available']['currency']; // "TZS"
```

### Retry USSD Push

```php
Snippe::push('payment-reference-uuid');
Snippe::push('payment-reference-uuid', '+255787654321'); // Different number
```

---

## Object Reference

### Payment Object

| Method | Returns | Description |
|--------|---------|-------------|
| `reference()` | `?string` | Unique payment UUID |
| `status()` | `?string` | `pending` \| `completed` \| `failed` \| `expired` \| `voided` |
| `paymentType()` | `?string` | `mobile` \| `card` \| `dynamic-qr` |
| `amount()` | `?int` | Payment amount in TZS |
| `currency()` | `?string` | `TZS` |
| `isPending()` | `bool` | |
| `isCompleted()` | `bool` | |
| `isFailed()` | `bool` | |
| `isExpired()` | `bool` | |
| `isVoided()` | `bool` | |
| `paymentUrl()` | `?string` | Checkout URL (card / QR) |
| `qrCode()` | `?string` | QR data string |
| `fees()` | `?int` | Transaction fees (available after completion) |
| `netAmount()` | `?int` | Amount after fees |
| `customer()` | `array` | Customer info (`name`, `email`, `phone`) |
| `completedAt()` | `?string` | Completion timestamp (ISO 8601) |
| `createdAt()` | `?string` | Creation timestamp (ISO 8601) |
| `toArray()` | `array` | Full raw API response |

### Payment Session Object

| Method | Returns | Description |
|--------|---------|-------------|
| `reference()` | `?string` | Session reference e.g. `sess_abc123` |
| `status()` | `?string` | `pending` \| `completed` \| `expired` \| `cancelled` |
| `checkoutUrl()` | `?string` | Hosted checkout URL — redirect the customer here |
| `paymentLinkUrl()` | `?string` | Shareable short link |
| `shortCode()` | `?string` | Abbreviated session code |
| `amount()` | `?int` | Fixed session amount (null when `allowCustomAmount` is set) |
| `currency()` | `?string` | `TZS` |
| `isPending()` | `bool` | |
| `isCompleted()` | `bool` | |
| `isExpired()` | `bool` | |
| `isCancelled()` | `bool` | |
| `expiresAt()` | `?string` | Session expiry timestamp (ISO 8601) |
| `completedAt()` | `?string` | Completion timestamp (ISO 8601) |
| `toArray()` | `array` | Full raw API response |

### Webhook Object

| Method | Returns | Description |
|--------|---------|-------------|
| `fromRaw(string $body, array $headers)` | `static` | Parse raw webhook payload |
| `verifyOrFail(string $secret)` | `void` | Throws `SnippeException` if signature is invalid |
| `verify(string $secret)` | `bool` | Returns false instead of throwing |
| `isPaymentCompleted()` | `bool` | True when event type is `payment.completed` |
| `isPaymentFailed()` | `bool` | True when event type is `payment.failed` |
| `reference()` | `?string` | Payment reference from the event |
| `amount()` | `?int` | Payment amount |
| `currency()` | `?string` | `TZS` |
| `metadata()` | `array` | The metadata array you attached to the session/payment |
| `toArray()` | `array` | Full raw webhook payload |

---

## Phone Number Normalisation

The builder automatically normalises any Tanzanian number format:

```
0754123456      → 255754123456  ✓ local
+255754123456   → 255754123456  ✓ international with +
255754123456    → 255754123456  ✓ already normalised
754123456       → 255754123456  ✓ no prefix
0754 123 456    → 255754123456  ✓ with spaces
0754-123-456    → 255754123456  ✓ with dashes
```

---

## Webhooks

### Built-in Route

Set `SNIPPE_WEBHOOK_PATH=snippe/webhook` and point your Snippe dashboard to:

```
https://yourapp.com/snippe/webhook
```

The package registers this route automatically and fires Laravel events:

```php
// In a service provider boot() method
Event::listen('snippe.payment.completed', function (array $data) {
    // $data: reference, amount, currency, customer, metadata, payload
    $subscription = Subscription::find($data['metadata']['subscription_id']);
    $subscription->activate();
});

Event::listen('snippe.payment.failed', function (array $data) {
    // Handle failure — notify user, cancel pending record, etc.
});
```

### Custom Webhook Controller

Disable the built-in route with `SNIPPE_WEBHOOK_PATH=false` and register your own:

```php
// routes/web.php
Route::post('/webhooks/snippe', [SnippeWebhookController::class, 'handle'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
```

```php
// app/Http/Controllers/SnippeWebhookController.php
namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ShadrackJm\Snippe\Exceptions\SnippeException;
use ShadrackJm\Snippe\Webhook;

class SnippeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        try {
            $event = Webhook::fromRaw($request->getContent(), $request->headers->all());
        } catch (SnippeException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Verify signature
        $secret = config('snippe.webhook_secret');
        if ($secret) {
            try {
                $event->verifyOrFail($secret);
            } catch (SnippeException $e) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $event->toArray();

        if ($event->isPaymentCompleted()) {
            $subscriptionId = $payload['data']['metadata']['subscription_id'] ?? null;
            $subscription   = Subscription::find($subscriptionId);

            if ($subscription && $subscription->status === 'pending') {
                // Record the transaction
                PaymentTransaction::create([
                    'reference'       => $payload['data']['reference'] ?? null,
                    'amount'          => $payload['data']['amount']['value'] ?? 0,
                    'currency'        => $payload['data']['amount']['currency'] ?? 'TZS',
                    'status'          => 'completed',
                    'channel'         => $payload['data']['channel']['type'] ?? null,
                    'provider'        => $payload['data']['channel']['provider'] ?? null,
                    'customer_name'   => $payload['data']['customer']['name'] ?? null,
                    'customer_phone'  => $payload['data']['customer']['phone'] ?? null,
                    'metadata'        => $payload,
                    'completed_at'    => now(),
                ]);

                // Auto-activate the subscription
                $subscription->activate();
            }
        }

        if ($event->isPaymentFailed()) {
            $subscriptionId = $payload['data']['metadata']['subscription_id'] ?? null;
            Subscription::where('id', $subscriptionId)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        // Always return 200 so Snippe stops retrying
        return response()->json(['status' => 'received']);
    }
}
```

### Signature Verification

```php
use ShadrackJm\Snippe\Webhook;

$event = Webhook::fromRaw($request->getContent(), $request->headers->all());

// Option A — throws SnippeException if invalid
$event->verifyOrFail(config('snippe.webhook_secret'));

// Option B — returns bool
if (! $event->verify(config('snippe.webhook_secret'))) {
    return response()->json(['error' => 'Invalid signature'], 401);
}
```

---

## Subscription Integration Pattern

The recommended end-to-end pattern for SaaS subscription billing:

```
User clicks "Subscribe"
       │
       ▼
Controller creates a pending Subscription record
       │
       ▼
Snippe::session($amount)
    ->customer(...)
    ->allowedMethods(['mobile_money', 'bank_transfer'])
    ->metadata(['subscription_id' => $sub->id])
    ->redirectTo($successUrl, $cancelUrl)
    ->send()
       │
       ▼
redirect($session->checkoutUrl())   ← user pays on Snippe page
       │
       ▼
Snippe fires POST /webhooks/snippe
       │
       ▼
Webhook controller verifies signature
       │
       ├─ payment.completed → $subscription->activate()  ← auto-activation
       │                       record PaymentTransaction
       │                       send confirmation email
       │
       └─ payment.failed    → mark subscription cancelled
                               send failure email
```

---

## Error Handling

All API errors throw a `SnippeException`:

```php
use ShadrackJm\Snippe\Exceptions\SnippeException;

try {
    $session = Snippe::session(50000)
        ->customer('John Doe', 'john@email.com')
        ->send();
} catch (SnippeException $e) {
    $e->getMessage();    // "amount must be at least 500"
    $e->getCode();       // 422 (HTTP status)
    $e->getErrorCode();  // "validation_error"
    $e->getResponse();   // full API error array
}
```

| HTTP | Error Code | Meaning |
|------|------------|---------|
| 400 | `validation_error` | Missing or invalid field |
| 401 | `unauthorized` | Bad or missing API key |
| 403 | `insufficient_scope` | API key lacks permission |
| 404 | `not_found` | Payment or session not found |
| 422 | `validation_error` | Unprocessable entity |

---

## Testing

The package ships with a full Pest test suite:

```bash
composer test
```

In your own application, use Laravel's `Http::fake()` to mock Snippe API responses:

```php
use Illuminate\Support\Facades\Http;
use ShadrackJm\Snippe\Facades\Snippe;

it('creates a payment session', function () {
    Http::fake([
        '*/sessions' => Http::response([
            'data' => [
                'reference'    => 'sess_test123',
                'status'       => 'pending',
                'checkout_url' => 'https://checkout.snippe.sh/sess_test123',
            ],
        ], 201),
    ]);

    $session = Snippe::session(50000)
        ->customer('John Doe', 'john@email.com')
        ->allowedMethods(['mobile_money', 'bank_transfer'])
        ->send();

    expect($session->isPending())->toBeTrue()
        ->and($session->checkoutUrl())->toContain('sess_test123');
});
```

---

## Configuration Reference

```php
// config/snippe.php
return [
    // Your Snippe API key from the dashboard
    'api_key'        => env('SNIPPE_API_KEY', ''),

    // Snippe API base URL — do not change unless testing against a sandbox
    'base_url'       => env('SNIPPE_BASE_URL', 'https://api.snippe.sh/v1'),

    // The URL Snippe will POST webhook events to (must be publicly accessible)
    'webhook_url'    => env('SNIPPE_WEBHOOK_URL', null),

    // Webhook signing secret for verifying event authenticity
    'webhook_secret' => env('SNIPPE_WEBHOOK_SECRET', null),

    // Route path for the built-in webhook endpoint
    // Set to false to disable the built-in route and register your own
    'webhook_path'   => env('SNIPPE_WEBHOOK_PATH', 'snippe/webhook'),

    // HTTP request timeout in seconds
    'timeout'        => env('SNIPPE_TIMEOUT', 30),

    // Currency for all transactions (Tanzania: TZS)
    'currency'       => env('SNIPPE_CURRENCY', 'TZS'),
];
```

---

## Changelog

### v1.0.3 — 2026-04-11
- Added Laravel 13 support (`illuminate/support|http|routing ^13.0`)

### v1.0.2
- Initial stable release with Mobile Money, Card, QR, Payment Sessions, Webhooks

---

## License

MIT — see [LICENSE](LICENSE).
