<?php

namespace ShadrackJm\Snippe;

use ShadrackJm\Snippe\Exceptions\SnippeException;

/**
 * Fluent builder for constructing and sending payment requests.
 *
 * Usage:
 *   Snippe::mobileMoney(5000, '0754123456')
 *       ->customer('John Doe', 'john@email.com')
 *       ->webhook('https://app.com/webhook')
 *       ->send();
 */
class PaymentBuilder
{
    protected array $payload = [];

    protected string $paymentType;

    public function __construct(
        protected readonly SnippeClient $client,
        string $paymentType,
        int $amount,
    ) {
        $this->paymentType = $paymentType;

        $this->payload = [
            'payment_type' => $paymentType,
            'details'      => [
                'amount'   => $amount,
                'currency' => config('snippe.currency', 'TZS'),
            ],
        ];
    }

    // ── Customer ─────────────────────────────────────────────────────────────

    /**
     * Set customer details.
     *
     * Accepts a full name (auto-split into first/last) or separate parts.
     *
     * @param  string  $name      Full name or first name.
     * @param  string  $email     Customer email.
     * @param  string|null  $lastName  Explicit last name (optional).
     */
    public function customer(string $name, string $email, ?string $lastName = null): static
    {
        if ($lastName !== null) {
            $firstName = $name;
        } else {
            $parts     = explode(' ', trim($name), 2);
            $firstName = $parts[0];
            $lastName  = $parts[1] ?? '';
        }

        $this->payload['customer'] = [
            'firstname' => $firstName,
            'lastname'  => $lastName,
            'email'     => $email,
        ];

        // Include phone in customer block if already set
        if (isset($this->payload['phone_number'])) {
            $this->payload['customer']['phone'] = $this->payload['phone_number'];
        }

        return $this;
    }

    // ── Phone Number ──────────────────────────────────────────────────────────

    /**
     * Set and normalise a Tanzanian phone number.
     *
     * All of the following are accepted and normalised to 255XXXXXXXXX:
     *   0754123456, +255754123456, 255754123456, 754123456
     */
    public function phone(string $phone): static
    {
        $this->payload['phone_number'] = $this->normalizePhone($phone);

        if (isset($this->payload['customer'])) {
            $this->payload['customer']['phone'] = $this->payload['phone_number'];
        }

        return $this;
    }

    // ── Billing (card payments) ───────────────────────────────────────────────

    /**
     * Set billing address for card payments.
     */
    public function billing(
        string $address,
        string $city,
        string $state,
        string $postcode,
        string $country = 'TZ',
    ): static {
        $this->payload['billing'] = [
            'address'  => $address,
            'city'     => $city,
            'state'    => $state,
            'postcode' => $postcode,
            'country'  => $country,
        ];

        return $this;
    }

    // ── Redirect URLs ─────────────────────────────────────────────────────────

    /**
     * Set success and cancel redirect URLs (card / QR payments).
     */
    public function redirectTo(string $successUrl, string $cancelUrl): static
    {
        $this->payload['redirect_url'] = $successUrl;
        $this->payload['cancel_url']   = $cancelUrl;

        return $this;
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    /**
     * Override the default webhook URL for this payment.
     */
    public function webhook(string $url): static
    {
        $this->payload['webhook_url'] = $url;

        return $this;
    }

    // ── Metadata / Description ────────────────────────────────────────────────

    /**
     * Attach arbitrary key-value metadata to the payment.
     */
    public function metadata(array $data): static
    {
        $this->payload['metadata'] = $data;

        return $this;
    }

    /**
     * Human-readable description of the payment.
     */
    public function description(string $text): static
    {
        $this->payload['description'] = $text;

        return $this;
    }

    // ── Idempotency ───────────────────────────────────────────────────────────

    /**
     * Set a custom idempotency key to prevent duplicate charges.
     * If not set, the client auto-generates one.
     */
    public function idempotencyKey(string $key): static
    {
        $this->payload['_idempotency_key'] = $key;

        return $this;
    }

    // ── Amount (override) ─────────────────────────────────────────────────────

    /**
     * Override the amount and currency if needed.
     */
    public function amount(int $amount, string $currency = 'TZS'): static
    {
        $this->payload['details']['amount']   = $amount;
        $this->payload['details']['currency'] = $currency;

        return $this;
    }

    // ── Build / Send ──────────────────────────────────────────────────────────

    /**
     * Preview the payload that will be sent to the API without sending it.
     */
    public function toArray(): array
    {
        return $this->buildPayload();
    }

    /**
     * Send the payment request to the Snippe API.
     *
     * @throws SnippeException
     */
    public function send(): Payment
    {
        return $this->client->sendPayment($this->buildPayload());
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Merge in the default webhook URL (if configured) and return the final payload.
     */
    protected function buildPayload(): array
    {
        $payload = $this->payload;

        // Apply the global webhook default if not already set per-payment
        if (! isset($payload['webhook_url'])) {
            $default = $this->client->getWebhookUrl();

            if ($default) {
                $payload['webhook_url'] = $default;
            }
        }

        return $payload;
    }

    /**
     * Normalise various Tanzanian phone number formats to 255XXXXXXXXX.
     */
    protected function normalizePhone(string $phone): string
    {
        // Strip all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        return match (true) {
            // Already full international format: 255XXXXXXXXX (12 digits)
            strlen($digits) === 12 && str_starts_with($digits, '255') => $digits,

            // Local format: 0XXXXXXXXX (10 digits)
            strlen($digits) === 10 && str_starts_with($digits, '0')  => '255' . substr($digits, 1),

            // Without leading 0 or country code: 9 digits
            strlen($digits) === 9 => '255' . $digits,

            // Already has country code but without +: 255XXXXXXXXX
            str_starts_with($digits, '255') => $digits,

            default => $digits,
        };
    }
}
