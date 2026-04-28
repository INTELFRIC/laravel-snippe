<?php

namespace ShadrackJm\Snippe;

/**
 * Represents a Snippe payment response.
 *
 * This object is returned by every payment creation method and by
 * SnippeClient::find(). All fields are accessed via typed helper
 * methods or dynamically via __get().
 */
class Payment
{
    public function __construct(protected readonly array $data) {}

    // ── Identity ────────────────────────────────────────────────────────────

    /** Unique payment reference UUID. */
    public function reference(): ?string
    {
        return $this->data['reference'] ?? null;
    }

    // ── Status ───────────────────────────────────────────────────────────────

    /** Raw status string: pending | completed | failed | expired | voided */
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

    public function isFailed(): bool
    {
        return $this->status() === 'failed';
    }

    public function isExpired(): bool
    {
        return $this->status() === 'expired';
    }

    public function isVoided(): bool
    {
        return $this->status() === 'voided';
    }

    // ── Type ─────────────────────────────────────────────────────────────────

    /** Payment type: mobile | card | dynamic-qr */
    public function paymentType(): ?string
    {
        return $this->data['payment_type'] ?? null;
    }

    // ── Amount ───────────────────────────────────────────────────────────────

    /** Raw payment amount (e.g. 5000). */
    public function amount(): ?int
    {
        $amount = $this->data['amount'] ?? $this->data['details']['amount'] ?? null;

        if (is_array($amount)) {
            return (int) ($amount['value'] ?? 0);
        }

        return $amount !== null ? (int) $amount : null;
    }

    /** Currency code, e.g. "TZS". */
    public function currency(): ?string
    {
        $amount = $this->data['amount'] ?? $this->data['details']['amount'] ?? null;

        if (is_array($amount)) {
            return $amount['currency'] ?? null;
        }

        return $this->data['currency'] ?? 'TZS';
    }

    /** Transaction fees (populated after completion). */
    public function fees(): ?int
    {
        $fees = $this->data['fees'] ?? null;

        if (is_array($fees)) {
            return (int) ($fees['value'] ?? 0);
        }

        return $fees !== null ? (int) $fees : null;
    }

    /** Net amount after fees. */
    public function netAmount(): ?int
    {
        $net = $this->data['net_amount'] ?? null;

        if (is_array($net)) {
            return (int) ($net['value'] ?? 0);
        }

        return $net !== null ? (int) $net : null;
    }

    // ── URLs / Tokens ─────────────────────────────────────────────────────────

    /** Redirect URL for card / QR checkout pages. */
    public function paymentUrl(): ?string
    {
        return $this->data['payment_url']
            ?? $this->data['checkout_url']
            ?? null;
    }

    /** Raw QR code data string for dynamic QR payments. */
    public function qrCode(): ?string
    {
        return $this->data['qr_code'] ?? null;
    }

    /** Internal payment token. */
    public function paymentToken(): ?string
    {
        return $this->data['payment_token'] ?? $this->data['token'] ?? null;
    }

    // ── Customer ──────────────────────────────────────────────────────────────

    /** Customer info array: first_name, last_name, email, phone. */
    public function customer(): array
    {
        return $this->data['customer'] ?? [];
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
