<?php

use ShadrackJm\Snippe\SnippeClient;

beforeEach(function () {
    $this->client = app(SnippeClient::class);
});

// ── Fixed Amount ──────────────────────────────────────────────────────────────

it('builds correct fixed-amount session payload', function () {
    $payload = $this->client->session(50000)
        ->customer('John Doe', 'john@email.com')
        ->toArray();

    expect($payload['amount'])->toBe(50000)
        ->and($payload['currency'])->toBe('TZS')
        ->and($payload['customer']['firstname'])->toBe('John')
        ->and($payload['customer']['lastname'])->toBe('Doe')
        ->and($payload['customer']['email'])->toBe('john@email.com');
});

// ── Custom Amount ─────────────────────────────────────────────────────────────

it('removes amount when allowCustomAmount is enabled', function () {
    $payload = $this->client->session(50000)
        ->allowCustomAmount()
        ->toArray();

    expect($payload)->not->toHaveKey('amount')
        ->and($payload['allow_custom_amount'])->toBeTrue();
});

it('builds session with null amount', function () {
    $payload = $this->client->session()
        ->allowCustomAmount()
        ->toArray();

    expect($payload)->not->toHaveKey('amount')
        ->and($payload['allow_custom_amount'])->toBeTrue();
});

// ── Redirect URLs ─────────────────────────────────────────────────────────────

it('sets redirect and cancel urls', function () {
    $payload = $this->client->session(10000)
        ->redirectTo('https://app.com/success', 'https://app.com/cancel')
        ->toArray();

    expect($payload['redirect_url'])->toBe('https://app.com/success')
        ->and($payload['cancel_url'])->toBe('https://app.com/cancel');
});

it('sets only redirect url when cancel url is omitted', function () {
    $payload = $this->client->session(10000)
        ->redirectTo('https://app.com/success')
        ->toArray();

    expect($payload['redirect_url'])->toBe('https://app.com/success')
        ->and($payload)->not->toHaveKey('cancel_url');
});

// ── Metadata ──────────────────────────────────────────────────────────────────

it('attaches metadata to session payload', function () {
    $payload = $this->client->session(10000)
        ->metadata(['plan_id' => 3, 'company_id' => 12])
        ->toArray();

    expect($payload['metadata'])->toBe(['plan_id' => 3, 'company_id' => 12]);
});

// ── Allowed Methods ───────────────────────────────────────────────────────────

it('sets allowed payment methods', function () {
    $payload = $this->client->session(10000)
        ->allowedMethods(['mobile_money', 'card'])
        ->toArray();

    expect($payload['allowed_methods'])->toBe(['mobile_money', 'card']);
});

// ── Profile & Expiry ──────────────────────────────────────────────────────────

it('sets profile id and expiry', function () {
    $payload = $this->client->session(10000)
        ->profile('prof_abc123')
        ->expiresIn(3600)
        ->toArray();

    expect($payload['profile_id'])->toBe('prof_abc123')
        ->and($payload['expires_in'])->toBe(3600);
});

// ── Customer with phone ───────────────────────────────────────────────────────

it('includes phone in customer block when provided', function () {
    $payload = $this->client->session(10000)
        ->customer('Jane Doe', 'jane@email.com', '+255754123456')
        ->toArray();

    expect($payload['customer']['phone'])->toBe('+255754123456');
});

// ── Default Webhook URL ───────────────────────────────────────────────────────

it('injects default webhook url into session payload', function () {
    $this->client->setWebhookUrl('https://app.com/webhook');

    $payload = $this->client->session(10000)->toArray();

    expect($payload['webhook_url'])->toBe('https://app.com/webhook');
});

it('per-session webhook overrides default', function () {
    $this->client->setWebhookUrl('https://app.com/webhook');

    $payload = $this->client->session(10000)
        ->webhook('https://app.com/custom-session-hook')
        ->toArray();

    expect($payload['webhook_url'])->toBe('https://app.com/custom-session-hook');
});

// ── Currency Override ─────────────────────────────────────────────────────────

it('overrides currency on session', function () {
    $payload = $this->client->session(10000)
        ->currency('USD')
        ->toArray();

    expect($payload['currency'])->toBe('USD');
});
