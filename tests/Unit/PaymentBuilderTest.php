<?php

use ShadrackJm\Snippe\PaymentBuilder;
use ShadrackJm\Snippe\SnippeClient;

beforeEach(function () {
    $this->client = app(SnippeClient::class);
});

// ── Phone Normalization ───────────────────────────────────────────────────────

it('normalizes local format 0754123456', function () {
    $payload = $this->client->mobileMoney(5000, '0754123456')->toArray();
    expect($payload['phone_number'])->toBe('255754123456');
});

it('normalizes international format +255754123456', function () {
    $payload = $this->client->mobileMoney(5000, '+255754123456')->toArray();
    expect($payload['phone_number'])->toBe('255754123456');
});

it('normalizes format without leading zero 754123456', function () {
    $payload = $this->client->mobileMoney(5000, '754123456')->toArray();
    expect($payload['phone_number'])->toBe('255754123456');
});

it('normalizes format with spaces 0754 123 456', function () {
    $payload = $this->client->mobileMoney(5000, '0754 123 456')->toArray();
    expect($payload['phone_number'])->toBe('255754123456');
});

it('normalizes format with dashes 0754-123-456', function () {
    $payload = $this->client->mobileMoney(5000, '0754-123-456')->toArray();
    expect($payload['phone_number'])->toBe('255754123456');
});

// ── Customer Name Splitting ───────────────────────────────────────────────────

it('splits full name into firstname and lastname', function () {
    $payload = $this->client->mobileMoney(5000, '0754123456')
        ->customer('John Doe', 'john@email.com')
        ->toArray();

    expect($payload['customer']['firstname'])->toBe('John')
        ->and($payload['customer']['lastname'])->toBe('Doe');
});

it('handles single word name gracefully', function () {
    $payload = $this->client->mobileMoney(5000, '0754123456')
        ->customer('John', 'john@email.com')
        ->toArray();

    expect($payload['customer']['firstname'])->toBe('John')
        ->and($payload['customer']['lastname'])->toBe('');
});

it('accepts explicit last name parameter', function () {
    $payload = $this->client->mobileMoney(5000, '0754123456')
        ->customer('John', 'john@email.com', 'Doe')
        ->toArray();

    expect($payload['customer']['firstname'])->toBe('John')
        ->and($payload['customer']['lastname'])->toBe('Doe');
});

// ── Mobile Money Payload ──────────────────────────────────────────────────────

it('builds correct mobile money payload', function () {
    $payload = $this->client->mobileMoney(5000, '0754123456')
        ->customer('John Doe', 'john@email.com')
        ->metadata(['order_id' => 'ORD-123'])
        ->description('Test order')
        ->toArray();

    expect($payload['payment_type'])->toBe('mobile')
        ->and($payload['details']['amount'])->toBe(5000)
        ->and($payload['details']['currency'])->toBe('TZS')
        ->and($payload['metadata'])->toBe(['order_id' => 'ORD-123'])
        ->and($payload['description'])->toBe('Test order');
});

// ── Card Payload ──────────────────────────────────────────────────────────────

it('builds correct card payload with billing and redirects', function () {
    $payload = $this->client->card(10000)
        ->phone('0754123456')
        ->customer('Jane Doe', 'jane@email.com')
        ->billing('123 Main St', 'Dar es Salaam', 'DSM', '14101', 'TZ')
        ->redirectTo('https://app.com/success', 'https://app.com/cancel')
        ->toArray();

    expect($payload['payment_type'])->toBe('card')
        ->and($payload['details']['amount'])->toBe(10000)
        ->and($payload['billing']['city'])->toBe('Dar es Salaam')
        ->and($payload['billing']['country'])->toBe('TZ')
        ->and($payload['redirect_url'])->toBe('https://app.com/success')
        ->and($payload['cancel_url'])->toBe('https://app.com/cancel');
});

// ── QR Payload ────────────────────────────────────────────────────────────────

it('builds correct qr payload', function () {
    $payload = $this->client->qr(3000)->toArray();

    expect($payload['payment_type'])->toBe('dynamic-qr')
        ->and($payload['details']['amount'])->toBe(3000);
});

// ── Default Webhook URL ───────────────────────────────────────────────────────

it('injects default webhook url when set on client', function () {
    $this->client->setWebhookUrl('https://app.com/webhook');

    $payload = $this->client->mobileMoney(5000, '0754123456')
        ->customer('John Doe', 'john@email.com')
        ->toArray();

    expect($payload['webhook_url'])->toBe('https://app.com/webhook');
});

it('per-payment webhook overrides default', function () {
    $this->client->setWebhookUrl('https://app.com/webhook');

    $payload = $this->client->mobileMoney(5000, '0754123456')
        ->customer('John Doe', 'john@email.com')
        ->webhook('https://app.com/custom-hook')
        ->toArray();

    expect($payload['webhook_url'])->toBe('https://app.com/custom-hook');
});
