<?php

namespace ShadrackJm\Snippe;

use ShadrackJm\Snippe\Exceptions\SnippeException;

/**
 * Parses and represents an incoming Snippe webhook event.
 *
 * Usage in your controller / handler:
 *
 *   $event = Webhook::fromRaw($request->getContent(), $request->headers->all());
 *
 *   // Optional but strongly recommended — verify the signature
 *   if (! $event->verify(config('snippe.webhook_secret'))) {
 *       abort(401);
 *   }
 *
 *   if ($event->isPaymentCompleted()) {
 *       Order::markPaid($event->reference());
 *   }
 *
 *   return response()->json(['received' => true]);
 */
class Webhook
{
    protected array  $payload;
    protected string $rawBody;
    protected string $eventType;
    protected array  $headers;

    protected function __construct(string $rawBody, array $headers)
    {
        $decoded = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SnippeException('Invalid JSON payload received from Snippe webhook.');
        }

        $this->rawBody   = $rawBody;
        $this->payload   = $decoded;
        $this->headers   = $headers;
        $this->eventType = $this->extractEventType($headers);
    }

    // ── Factory Methods ───────────────────────────────────────────────────────

    /**
     * Create a Webhook instance from raw body and headers.
     *
     * Use this in your controller:
     *   $event = Webhook::fromRaw($request->getContent(), $request->headers->all());
     *
     * @param  array<string,string|array>  $headers
     * @throws SnippeException
     */
    public static function fromRaw(string $body, array $headers = []): static
    {
        return new static($body, static::normalizeHeaders($headers));
    }

    // ── Signature Verification ────────────────────────────────────────────────

    /**
     * Verify the HMAC-SHA256 signature sent by Snippe in the X-Webhook-Signature header.
     *
     * Always call this before processing any webhook event in production.
     *
     * @param  string|null  $secret  Your webhook signing secret (from config('snippe.webhook_secret')).
     *                               If null, verification is skipped and true is returned — useful
     *                               in local development but not recommended for production.
     */
    public function verify(?string $secret): bool
    {
        if ($secret === null || $secret === '') {
            return true;
        }

        $signature = $this->headers['x-webhook-signature'] ?? null;

        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $this->rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Throw a SnippeException if the signature is invalid.
     *
     * Convenience wrapper around verify() for cleaner controller code:
     *   $event->verifyOrFail(config('snippe.webhook_secret'));
     *
     * @throws SnippeException
     */
    public function verifyOrFail(?string $secret): static
    {
        if (! $this->verify($secret)) {
            throw new SnippeException('Snippe webhook signature verification failed.', 401);
        }

        return $this;
    }

    // ── Event Type ────────────────────────────────────────────────────────────

    /** Raw event type string, e.g. "payment.completed". */
    public function eventType(): string
    {
        return $this->eventType;
    }

    public function isPaymentCompleted(): bool
    {
        return $this->eventType === 'payment.completed';
    }

    public function isPaymentFailed(): bool
    {
        return $this->eventType === 'payment.failed';
    }

    public function isPayoutCompleted(): bool
    {
        return $this->eventType === 'payout.completed';
    }

    public function isPayoutFailed(): bool
    {
        return $this->eventType === 'payout.failed';
    }

    // ── Data Accessors ────────────────────────────────────────────────────────

    /** Payment reference UUID. */
    public function reference(): ?string
    {
        return $this->payload['data']['reference']
            ?? $this->payload['reference']
            ?? null;
    }

    /** Payment status string. */
    public function status(): ?string
    {
        return $this->payload['data']['status']
            ?? $this->payload['status']
            ?? null;
    }

    /** Payment amount as integer. */
    public function amount(): ?int
    {
        $amount = $this->payload['data']['amount']
            ?? $this->payload['amount']
            ?? null;

        if (is_array($amount)) {
            return (int) ($amount['value'] ?? 0);
        }

        return $amount !== null ? (int) $amount : null;
    }

    /** Currency code, e.g. "TZS". */
    public function currency(): ?string
    {
        $amount = $this->payload['data']['amount']
            ?? $this->payload['amount']
            ?? null;

        if (is_array($amount)) {
            return $amount['currency'] ?? null;
        }

        return $this->payload['data']['currency']
            ?? $this->payload['currency']
            ?? null;
    }

    /** Customer info array. */
    public function customer(): array
    {
        return $this->payload['data']['customer']
            ?? $this->payload['customer']
            ?? [];
    }

    /** Custom metadata you attached at payment creation. */
    public function metadata(): array
    {
        return $this->payload['data']['metadata']
            ?? $this->payload['metadata']
            ?? [];
    }

    /** Full parsed payload array. */
    public function payload(): array
    {
        return $this->payload;
    }

    /** Raw JSON string as received from Snippe. */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /** All normalised headers. */
    public function headers(): array
    {
        return $this->headers;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    protected function extractEventType(array $headers): string
    {
        return $headers['x-webhook-event']
            ?? $headers['x-snippe-event']
            ?? $this->payload['event']
            ?? $this->payload['event_type']
            ?? $this->payload['type']
            ?? '';
    }

    /**
     * Normalise headers to lowercase scalar values so Apache/Nginx/PHP-FPM
     * quirks (and Laravel's HeaderBag arrays) don't affect lookups.
     */
    protected static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $normalized;
    }
}
