<?php

use Illuminate\Support\Facades\Http;
use ShadrackJm\Snippe\Facades\Snippe;
use ShadrackJm\Snippe\Payment;
use ShadrackJm\Snippe\PaymentSession;
use ShadrackJm\Snippe\SnippeClient;

// ── Mobile Money (createPayment) ──────────────────────────────────────────────

it('sends mobile money payment and returns a Payment object', function () {
    Http::fake([
        '*/payments' => Http::response([
            'data' => [
                'reference'    => '9015c155-9e29-4e8e-8fe6-d5d81553c8e6',
                'status'       => 'pending',
                'payment_type' => 'mobile',
                'amount'       => ['value' => 5000, 'currency' => 'TZS'],
            ],
        ], 200),
    ]);

    $payment = Snippe::mobileMoney(5000, '0754123456')
        ->customer('John Doe', 'john@email.com')
        ->send();

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->reference())->toBe('9015c155-9e29-4e8e-8fe6-d5d81553c8e6')
        ->and($payment->status())->toBe('pending')
        ->and($payment->isPending())->toBeTrue()
        ->and($payment->amount())->toBe(5000)
        ->and($payment->currency())->toBe('TZS');
});

// ── initiateMobilePayment (array style) ───────────────────────────────────────

it('initiates mobile payment via array payload', function () {
    Http::fake([
        '*/payments' => Http::response([
            'data' => [
                'reference' => 'ref-mobile-array',
                'status'    => 'pending',
            ],
        ], 200),
    ]);

    $payment = Snippe::initiateMobilePayment([
        'amount'       => 3000,
        'phone_number' => '0754123456',
        'customer'     => [
            'firstname' => 'Jane',
            'lastname'  => 'Doe',
            'email'     => 'jane@email.com',
        ],
    ]);

    expect($payment->reference())->toBe('ref-mobile-array')
        ->and($payment->isPending())->toBeTrue();
});

// ── Card Payment ──────────────────────────────────────────────────────────────

it('creates a card payment and returns checkout url', function () {
    Http::fake([
        '*/payments' => Http::response([
            'data' => [
                'reference'   => 'card-ref-001',
                'status'      => 'pending',
                'payment_url' => 'https://checkout.snippe.sh/pay/card-ref-001',
            ],
        ], 200),
    ]);

    $payment = Snippe::card(10000)
        ->phone('0754123456')
        ->customer('Jane Doe', 'jane@email.com')
        ->billing('123 Main St', 'Dar es Salaam', 'DSM', '14101', 'TZ')
        ->redirectTo('https://app.com/success', 'https://app.com/cancel')
        ->send();

    expect($payment->paymentUrl())->toBe('https://checkout.snippe.sh/pay/card-ref-001');
});

// ── createPayment (raw array) ─────────────────────────────────────────────────

it('creates payment via raw array payload', function () {
    Http::fake([
        '*/payments' => Http::response([
            'data' => ['reference' => 'raw-ref-123', 'status' => 'pending'],
        ], 200),
    ]);

    $payment = Snippe::createPayment([
        'payment_type' => 'mobile',
        'details'      => ['amount' => 1000, 'currency' => 'TZS'],
        'phone_number' => '255754123456',
        'customer'     => ['firstname' => 'Test', 'lastname' => 'User', 'email' => 'test@mail.com'],
    ]);

    expect($payment->reference())->toBe('raw-ref-123');
});

// ── verifyTransaction / find ──────────────────────────────────────────────────

it('verifies a transaction status by reference', function () {
    Http::fake([
        '*/payments/9015c155-*' => Http::response([
            'data' => [
                'reference'    => '9015c155-9e29-4e8e-8fe6-d5d81553c8e6',
                'status'       => 'completed',
                'payment_type' => 'mobile',
                'amount'       => ['value' => 5000, 'currency' => 'TZS'],
                'completed_at' => '2026-01-25T00:50:44.105159Z',
            ],
        ], 200),
    ]);

    $payment = Snippe::verifyTransaction('9015c155-9e29-4e8e-8fe6-d5d81553c8e6');

    expect($payment->isCompleted())->toBeTrue()
        ->and($payment->completedAt())->toBe('2026-01-25T00:50:44.105159Z');
});

// ── Account Balance ───────────────────────────────────────────────────────────

it('retrieves account balance', function () {
    Http::fake([
        '*/balance' => Http::response([
            'data' => [
                'available' => ['value' => 6943, 'currency' => 'TZS'],
            ],
        ], 200),
    ]);

    $balance = Snippe::balance();

    expect($balance['data']['available']['value'])->toBe(6943)
        ->and($balance['data']['available']['currency'])->toBe('TZS');
});

// ── Payment Sessions ──────────────────────────────────────────────────────────

it('creates a payment session and returns checkout url', function () {
    Http::fake([
        '*/sessions' => Http::response([
            'data' => [
                'reference'   => 'sess_abc123def456',
                'status'      => 'pending',
                'checkout_url' => 'https://checkout.snippe.sh/sess_abc123def456',
                'short_code'  => 'ABC123',
                'amount'      => ['value' => 50000, 'currency' => 'TZS'],
            ],
        ], 201),
    ]);

    $session = Snippe::session(50000)
        ->customer('John Doe', 'john@email.com')
        ->redirectTo('https://app.com/success', 'https://app.com/cancel')
        ->metadata(['plan_id' => 3, 'company_id' => 12])
        ->send();

    expect($session)->toBeInstanceOf(PaymentSession::class)
        ->and($session->reference())->toBe('sess_abc123def456')
        ->and($session->checkoutUrl())->toBe('https://checkout.snippe.sh/sess_abc123def456')
        ->and($session->isPending())->toBeTrue()
        ->and($session->amount())->toBe(50000)
        ->and($session->currency())->toBe('TZS');
});

it('creates a session with allowed methods restriction', function () {
    Http::fake([
        '*/sessions' => Http::response([
            'data' => [
                'reference'    => 'sess_methods123',
                'status'       => 'pending',
                'checkout_url' => 'https://checkout.snippe.sh/sess_methods123',
            ],
        ], 201),
    ]);

    $session = Snippe::session(20000)
        ->allowedMethods(['mobile_money'])
        ->customer('Jane Doe', 'jane@email.com')
        ->send();

    expect($session->reference())->toBe('sess_methods123');

    Http::assertSent(function ($request) {
        $body = $request->data();
        return $body['allowed_methods'] === ['mobile_money'];
    });
});

it('creates a session with custom amount enabled', function () {
    Http::fake([
        '*/sessions' => Http::response([
            'data' => [
                'reference'          => 'sess_custom123',
                'status'             => 'pending',
                'checkout_url'       => 'https://checkout.snippe.sh/sess_custom123',
                'allow_custom_amount' => true,
            ],
        ], 201),
    ]);

    $session = Snippe::session()
        ->allowCustomAmount()
        ->customer('John Doe', 'john@email.com')
        ->send();

    expect($session->reference())->toBe('sess_custom123');

    Http::assertSent(function ($request) {
        $body = $request->data();
        return ($body['allow_custom_amount'] ?? false) === true
            && ! isset($body['amount']);
    });
});

it('creates a session with expiry and profile', function () {
    Http::fake([
        '*/sessions' => Http::response([
            'data' => [
                'reference'    => 'sess_exp123',
                'status'       => 'pending',
                'checkout_url' => 'https://checkout.snippe.sh/sess_exp123',
            ],
        ], 201),
    ]);

    $session = Snippe::session(15000)
        ->customer('John Doe', 'john@email.com')
        ->profile('prof_abc')
        ->expiresIn(1800)
        ->send();

    expect($session->reference())->toBe('sess_exp123');

    Http::assertSent(function ($request) {
        $body = $request->data();
        return $body['expires_in'] === 1800
            && $body['profile_id'] === 'prof_abc';
    });
});

// ── Error Handling ────────────────────────────────────────────────────────────

it('throws SnippeException on 401 unauthorized', function () {
    Http::fake([
        '*/payments' => Http::response([
            'message'    => 'invalid or missing API key',
            'error_code' => 'unauthorized',
        ], 401),
    ]);

    Snippe::mobileMoney(5000, '0754123456')
        ->customer('John Doe', 'john@email.com')
        ->send();
})->throws(\ShadrackJm\Snippe\Exceptions\SnippeException::class, 'invalid or missing API key');

it('exposes http status code and error code from SnippeException', function () {
    Http::fake([
        '*/payments' => Http::response([
            'message'    => 'Missing required field: amount',
            'error_code' => 'validation_error',
        ], 400),
    ]);

    try {
        Snippe::mobileMoney(5000, '0754123456')
            ->customer('John Doe', 'john@email.com')
            ->send();
    } catch (\ShadrackJm\Snippe\Exceptions\SnippeException $e) {
        expect($e->getCode())->toBe(400)
            ->and($e->getErrorCode())->toBe('validation_error')
            ->and($e->getMessage())->toContain('Missing required field');
    }
});

it('throws SnippeException on session 422 validation error', function () {
    Http::fake([
        '*/sessions' => Http::response([
            'message'    => 'amount must be at least 500',
            'error_code' => 'validation_error',
        ], 422),
    ]);

    Snippe::session(10)
        ->customer('John Doe', 'john@email.com')
        ->send();
})->throws(\ShadrackJm\Snippe\Exceptions\SnippeException::class);

// ── Service Provider & Facade ─────────────────────────────────────────────────

it('resolves SnippeClient from the container', function () {
    $client = app(SnippeClient::class);
    expect($client)->toBeInstanceOf(SnippeClient::class);
});

it('resolves SnippeClient via the snippe alias', function () {
    $client = app('snippe');
    expect($client)->toBeInstanceOf(SnippeClient::class);
});
