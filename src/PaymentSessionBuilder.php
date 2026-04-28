<?php

namespace ShadrackJm\Snippe;

use ShadrackJm\Snippe\Exceptions\SnippeException;

/**
 * Fluent builder for creating Snippe Payment Sessions (hosted checkout).
 *
 * A Payment Session creates a hosted checkout page that you redirect your
 * customer to. This is the recommended approach for subscription and
 * one-off payments where you want Snippe to handle the payment UI.
 *
 * Usage:
 *   $session = Snippe::session(50000)
 *       ->customer('John Doe', 'john@email.com')
 *       ->allowedMethods(['mobile_money', 'card'])
 *       ->redirectTo(route('subscription.success'), route('subscription.cancel'))
 *       ->webhook(route('snippe.webhook'))
 *       ->metadata(['plan_id' => 3, 'company_id' => 12])
 *       ->expiresIn(1800)
 *       ->send();
 *
 *   return redirect($session->checkoutUrl());
 */
class PaymentSessionBuilder
{
    protected array $payload = [];

    public function __construct(
        protected readonly SnippeClient $client,
        ?int $amount = null,
    ) {
        if ($amount !== null) {
            $this->payload['amount']   = $amount;
            $this->payload['currency'] = config('snippe.currency', 'TZS');
        }
    }

    // ── Customer ──────────────────────────────────────────────────────────────

    /**
     * Pre-fill customer details on the checkout form.
     *
     * Accepts a full name (auto-split into first/last) or separate parts.
     *
     * @param  string       $name      Full name or first name.
     * @param  string       $email     Customer email.
     * @param  string|null  $phone     Customer phone number.
     * @param  string|null  $lastName  Explicit last name (optional).
     */
    public function customer(
        string $name,
        string $email,
        ?string $phone = null,
        ?string $lastName = null,
    ): static {
        if ($lastName !== null) {
            $firstName = $name;
        } else {
            $parts     = explode(' ', trim($name), 2);
            $firstName = $parts[0];
            $lastName  = $parts[1] ?? '';
        }

        $this->payload['customer'] = array_filter([
            'firstname' => $firstName,
            'lastname'  => $lastName,
            'email'     => $email,
            'phone'     => $phone,
        ]);

        return $this;
    }

    // ── Payment Methods ───────────────────────────────────────────────────────

    /**
     * Restrict which payment methods are shown on the checkout page.
     *
     * @param  string[]  $methods  e.g. ['mobile_money', 'card', 'qr']
     */
    public function allowedMethods(array $methods): static
    {
        $this->payload['allowed_methods'] = $methods;

        return $this;
    }

    // ── Custom Amount ─────────────────────────────────────────────────────────

    /**
     * Allow the customer to enter their own payment amount.
     * When enabled, the amount passed to session() is ignored.
     */
    public function allowCustomAmount(bool $allow = true): static
    {
        $this->payload['allow_custom_amount'] = $allow;

        // Remove fixed amount when custom amount is enabled
        if ($allow) {
            unset($this->payload['amount'], $this->payload['currency']);
        }

        return $this;
    }

    // ── Redirect URLs ─────────────────────────────────────────────────────────

    /**
     * Set where the customer is sent after payment completes or is cancelled.
     */
    public function redirectTo(string $successUrl, string $cancelUrl = ''): static
    {
        $this->payload['redirect_url'] = $successUrl;

        if ($cancelUrl !== '') {
            $this->payload['cancel_url'] = $cancelUrl;
        }

        return $this;
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    /**
     * Set the webhook URL that receives payment completion events for this session.
     * Overrides the global default set in config('snippe.webhook_url').
     */
    public function webhook(string $url): static
    {
        $this->payload['webhook_url'] = $url;

        return $this;
    }

    // ── Metadata ──────────────────────────────────────────────────────────────

    /**
     * Attach arbitrary key-value metadata (returned in the webhook payload).
     * Use this to carry your internal IDs — plan_id, company_id, order_id, etc.
     */
    public function metadata(array $data): static
    {
        $this->payload['metadata'] = $data;

        return $this;
    }

    // ── Profile ───────────────────────────────────────────────────────────────

    /**
     * Link a Snippe Payment Profile for custom branding on the checkout page.
     */
    public function profile(string $profileId): static
    {
        $this->payload['profile_id'] = $profileId;

        return $this;
    }

    // ── Expiry ────────────────────────────────────────────────────────────────

    /**
     * Set session timeout in seconds (60–86400).
     * Defaults to Snippe's own default if not set.
     */
    public function expiresIn(int $seconds): static
    {
        $this->payload['expires_in'] = $seconds;

        return $this;
    }

    // ── Currency ──────────────────────────────────────────────────────────────

    /**
     * Override the currency (defaults to config('snippe.currency')).
     */
    public function currency(string $currency): static
    {
        $this->payload['currency'] = $currency;

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
     * Send the session creation request to the Snippe API.
     *
     * @throws SnippeException
     */
    public function send(): PaymentSession
    {
        return $this->client->sendSession($this->buildPayload());
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    protected function buildPayload(): array
    {
        $payload = $this->payload;

        // Apply the global webhook default if not already set per-session
        if (! isset($payload['webhook_url'])) {
            $default = $this->client->getWebhookUrl();

            if ($default) {
                $payload['webhook_url'] = $default;
            }
        }

        return $payload;
    }
}
