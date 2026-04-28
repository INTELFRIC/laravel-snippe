<?php

namespace ShadrackJm\Snippe;

/**
 * Represents a Snippe Payment Session response.
 *
 * Returned by Snippe::session()->send().
 * Redirect the customer to checkoutUrl() to complete payment.
 *
 * @example
 *   $session = Snippe::session(50000)
 *       ->customer('John Doe', 'john@email.com')
 *       ->redirectTo(route('subscription.success'), route('subscription.cancel'))
 *       ->metadata(['plan_id' => 3, 'company_id' => 12])
 *       ->send();
 *
 *   return redirect($session->checkoutUrl());
 */
class PaymentSession
{
    public function __construct(protected readonly array $data) {}

    // ── Identity ──────────────────────────────────────────────────────────────

    /** Session reference, e.g. "sess_abc123def456". */
    public function reference(): ?string
    {
        return $this->data['reference'] ?? null;
    }

    /** Short code for payment link generation. */
    public function shortCode(): ?string
    {
        return $this->data['short_code'] ?? null;
    }

    // ── Status ────────────────────────────────────────────────────────────────

    /** Status: pending | active | completed | expired | cancelled */
    public function status(): ?string
    {
        return $this->data['status'] ?? null;
    }

    public function isPending(): bool
    {
        return $this->status() === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status() === 'completed';
    }

    public function isExpired(): bool
    {
        return $this->status() === 'expired';
    }

    public function isCancelled(): bool
    {
        return $this->status() === 'cancelled';
    }

    // ── URLs ──────────────────────────────────────────────────────────────────

    /** The hosted checkout URL — redirect the customer here to pay. */
    public function checkoutUrl(): ?string
    {
        return $this->data['checkout_url']
            ?? $this->data['payment_url']
            ?? null;
    }

    /** Shareable short payment link. */
    public function paymentLinkUrl(): ?string
    {
        return $this->data['payment_link_url'] ?? null;
    }

    // ── Amount ────────────────────────────────────────────────────────────────

    /** Session amount in smallest currency unit (e.g. TZS). */
    public function amount(): ?int
    {
        $amount = $this->data['amount'] ?? null;

        if (is_array($amount)) {
            return (int) ($amount['value'] ?? 0);
        }

        return $amount !== null ? (int) $amount : null;
    }

    /** Currency code, e.g. "TZS". */
    public function currency(): ?string
    {
        $amount = $this->data['amount'] ?? null;

        if (is_array($amount)) {
            return $amount['currency'] ?? null;
        }

        return $this->data['currency'] ?? 'TZS';
    }

    /** Whether the customer can enter their own amount. */
    public function allowCustomAmount(): bool
    {
        return (bool) ($this->data['allow_custom_amount'] ?? false);
    }

    // ── Timestamps ────────────────────────────────────────────────────────────

    public function expiresAt(): ?string
    {
        return $this->data['expires_at'] ?? null;
    }

    public function createdAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    public function completedAt(): ?string
    {
        return $this->data['completed_at'] ?? null;
    }

    // ── Raw access ────────────────────────────────────────────────────────────

    /** Return the full raw response array. */
    public function toArray(): array
    {
        return $this->data;
    }

    /** Dynamic property access for any field in the response. */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
