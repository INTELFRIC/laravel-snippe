<?php

use ShadrackJm\Snippe\Webhook;
use ShadrackJm\Snippe\Exceptions\SnippeException;

// ── Completed Payment ─────────────────────────────────────────────────────────

it('parses a payment.completed webhook event', function () {
    $body = json_encode([
        'data' => [
            'reference' => 'test-ref-abc123',
            'status'    => 'completed',
            'amount'    => ['value' => 5000, 'currency' => 'TZS'],
            'customer'  => [
                'firstname' => 'John',
                'lastname'  => 'Doe',
                'email'     => 'john@email.com',
            ],
        ],
    ]);

    $event = Webhook::fromRaw($body, ['X-Webhook-Event' => 'payment.completed']);

    expect($event->isPaymentCompleted())->toBeTrue()
        ->and($event->isPaymentFailed())->toBeFalse()
        ->and($event->reference())->toBe('test-ref-abc123')
        ->and($event->status())->toBe('completed')
        ->and($event->amount())->toBe(5000)
        ->and($event->currency())->toBe('TZS');
});

// ── Failed Payment ────────────────────────────────────────────────────────────

it('parses a payment.failed webhook event', function () {
    $body = json_encode([
        'data' => [
            'reference' => 'failed-ref-xyz',
            'status'    => 'failed',
            'amount'    => ['value' => 1000, 'currency' => 'TZS'],
        ],
    ]);

    $event = Webhook::fromRaw($body, ['X-Webhook-Event' => 'payment.failed']);

    expect($event->isPaymentFailed())->toBeTrue()
        ->and($event->isPaymentCompleted())->toBeFalse()
        ->and($event->reference())->toBe('failed-ref-xyz');
});

// ── Payout Events ─────────────────────────────────────────────────────────────

it('detects payout.completed event', function () {
    $body  = json_encode(['data' => ['reference' => 'payout-ref-1', 'status' => 'completed']]);
    $event = Webhook::fromRaw($body, ['X-Webhook-Event' => 'payout.completed']);

    expect($event->isPayoutCompleted())->toBeTrue()
        ->and($event->isPayoutFailed())->toBeFalse();
});

it('detects payout.failed event', function () {
    $body  = json_encode(['data' => ['reference' => 'payout-ref-2', 'status' => 'failed']]);
    $event = Webhook::fromRaw($body, ['X-Webhook-Event' => 'payout.failed']);

    expect($event->isPayoutFailed())->toBeTrue()
        ->and($event->isPayoutCompleted())->toBeFalse();
});

// ── Header Case Insensitivity ─────────────────────────────────────────────────

it('handles lowercase header keys', function () {
    $body  = json_encode(['data' => ['reference' => 'ref-1', 'status' => 'completed']]);
    $event = Webhook::fromRaw($body, ['x-webhook-event' => 'payment.completed']);

    expect($event->isPaymentCompleted())->toBeTrue();
});

it('handles mixed-case header keys', function () {
    $body  = json_encode(['data' => ['reference' => 'ref-2', 'status' => 'completed']]);
    $event = Webhook::fromRaw($body, ['X-Webhook-Event' => 'payment.completed']);

    expect($event->isPaymentCompleted())->toBeTrue();
});

// ── Metadata ──────────────────────────────────────────────────────────────────

it('returns custom metadata from webhook payload', function () {
    $body = json_encode([
        'data' => [
            'reference' => 'meta-ref',
            'status'    => 'completed',
            'metadata'  => ['order_id' => 'ORD-999'],
        ],
    ]);

    $event = Webhook::fromRaw($body, ['X-Webhook-Event' => 'payment.completed']);

    expect($event->metadata())->toBe(['order_id' => 'ORD-999']);
});

// ── Invalid JSON ──────────────────────────────────────────────────────────────

it('throws SnippeException on invalid json body', function () {
    Webhook::fromRaw('not-valid-json', []);
})->throws(SnippeException::class);

// ── Event from payload body ───────────────────────────────────────────────────

it('falls back to event_type in payload when header is missing', function () {
    $body = json_encode([
        'event_type' => 'payment.completed',
        'data'       => ['reference' => 'body-event-ref', 'status' => 'completed'],
    ]);

    $event = Webhook::fromRaw($body, []);

    expect($event->isPaymentCompleted())->toBeTrue();
});

it('falls back to type field in payload when header is missing', function () {
    $body = json_encode([
        'type' => 'payment.completed',
        'data' => ['reference' => 'type-field-ref', 'status' => 'completed'],
    ]);

    $event = Webhook::fromRaw($body, []);

    expect($event->isPaymentCompleted())->toBeTrue();
});

// ── Signature Verification ────────────────────────────────────────────────────

it('verifies a valid HMAC-SHA256 signature', function () {
    $secret  = 'test-webhook-secret';
    $body    = json_encode(['data' => ['reference' => 'sig-ref', 'status' => 'completed']]);
    $sig     = hash_hmac('sha256', $body, $secret);

    $event = Webhook::fromRaw($body, [
        'X-Webhook-Event'     => 'payment.completed',
        'X-Webhook-Signature' => $sig,
    ]);

    expect($event->verify($secret))->toBeTrue();
});

it('rejects an invalid signature', function () {
    $secret = 'test-webhook-secret';
    $body   = json_encode(['data' => ['reference' => 'sig-ref', 'status' => 'completed']]);

    $event = Webhook::fromRaw($body, [
        'X-Webhook-Event'     => 'payment.completed',
        'X-Webhook-Signature' => 'invalid-signature',
    ]);

    expect($event->verify($secret))->toBeFalse();
});

it('rejects when signature header is missing', function () {
    $body  = json_encode(['data' => ['reference' => 'sig-ref', 'status' => 'completed']]);
    $event = Webhook::fromRaw($body, ['X-Webhook-Event' => 'payment.completed']);

    expect($event->verify('some-secret'))->toBeFalse();
});

it('skips verification and returns true when secret is null', function () {
    $body  = json_encode(['data' => ['reference' => 'sig-ref', 'status' => 'completed']]);
    $event = Webhook::fromRaw($body, ['X-Webhook-Event' => 'payment.completed']);

    expect($event->verify(null))->toBeTrue();
});

it('verifyOrFail throws when signature is invalid', function () {
    $body  = json_encode(['data' => ['reference' => 'sig-ref', 'status' => 'completed']]);
    $event = Webhook::fromRaw($body, [
        'X-Webhook-Event'     => 'payment.completed',
        'X-Webhook-Signature' => 'bad-sig',
    ]);

    $event->verifyOrFail('real-secret');
})->throws(SnippeException::class, 'signature verification failed');

it('verifyOrFail returns self when signature is valid', function () {
    $secret = 'test-secret';
    $body   = json_encode(['data' => ['reference' => 'ref', 'status' => 'completed']]);
    $sig    = hash_hmac('sha256', $body, $secret);

    $event = Webhook::fromRaw($body, [
        'X-Webhook-Event'     => 'payment.completed',
        'X-Webhook-Signature' => $sig,
    ]);

    expect($event->verifyOrFail($secret))->toBeInstanceOf(Webhook::class);
});
