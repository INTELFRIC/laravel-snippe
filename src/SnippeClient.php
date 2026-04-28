<?php

namespace ShadrackJm\Snippe;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use ShadrackJm\Snippe\Exceptions\SnippeException;
use ShadrackJm\Snippe\PaymentSession;
use ShadrackJm\Snippe\PaymentSessionBuilder;

/**
 * Main HTTP client for the Snippe Tanzania payment gateway.
 *
 * Bind via the Facade:
 *   Snippe::mobileMoney(5000, '0754123456')->customer(...)->send();
 *
 * Or resolve from the container:
 *   app(SnippeClient::class)->mobileMoney(5000, '0754123456')->...
 */
class SnippeClient
{
    protected string  $apiKey;
    protected string  $baseUrl;
    protected int     $timeout;
    protected ?string $webhookUrl;

    public function __construct(
        string $apiKey,
        string $baseUrl,
        int $timeout = 30,
        ?string $webhookUrl = null,
    ) {
        $this->apiKey     = $apiKey;
        $this->baseUrl    = rtrim($baseUrl, '/');
        $this->timeout    = $timeout;
        $this->webhookUrl = $webhookUrl;
    }

    // ── Payment Builders ──────────────────────────────────────────────────────

    /**
     * Initiate a Mobile Money (USSD push) payment.
     *
     * Supported networks: Airtel Money, M-Pesa, Mixx by Yas, Halotel.
     *
     * @param  int     $amount  Amount in TZS (e.g. 5000).
     * @param  string  $phone   Customer phone number (normalised automatically).
     */
    public function mobileMoney(int $amount, string $phone): PaymentBuilder
    {
        return (new PaymentBuilder($this, 'mobile', $amount))->phone($phone);
    }

    /**
     * Initiate a Card payment (Visa, Mastercard, local debit cards).
     *
     * After calling ->send(), redirect the customer to Payment::paymentUrl().
     *
     * @param  int  $amount  Amount in TZS.
     */
    public function card(int $amount): PaymentBuilder
    {
        return new PaymentBuilder($this, 'card', $amount);
    }

    /**
     * Generate a Dynamic QR code payment.
     *
     * @param  int  $amount  Amount in TZS.
     */
    public function qr(int $amount): PaymentBuilder
    {
        return new PaymentBuilder($this, 'dynamic-qr', $amount);
    }

    /**
     * Create a hosted Payment Session (checkout page).
     *
     * The customer is redirected to a Snippe-hosted page to complete payment.
     * This is the recommended approach for subscription and one-off payments.
     *
     * @param  int|null  $amount  Fixed amount in TZS. Pass null to allow custom amount.
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
    public function session(?int $amount = null): PaymentSessionBuilder
    {
        return new PaymentSessionBuilder($this, $amount);
    }

    // ── Direct Payment Methods (createPayment / initiateMobilePayment) ────────

    /**
     * Create a payment using a raw array payload.
     *
     * Useful when you want full control over the request body or are building
     * a non-standard payment type.
     *
     * @throws SnippeException
     */
    public function createPayment(array $data): Payment
    {
        return $this->sendPayment($data);
    }

    /**
     * Initiate a Mobile Money payment directly from an array payload.
     *
     * Convenience wrapper around createPayment() that enforces the
     * payment_type => 'mobile' key.
     *
     * Expected keys: amount, phone_number, customer[firstname, lastname, email]
     *
     * @throws SnippeException
     */
    public function initiateMobilePayment(array $data): Payment
    {
        $data['payment_type'] = 'mobile';

        if (isset($data['amount']) && ! isset($data['details'])) {
            $data['details'] = [
                'amount'   => (int) $data['amount'],
                'currency' => $data['currency'] ?? config('snippe.currency', 'TZS'),
            ];

            unset($data['amount'], $data['currency']);
        }

        return $this->sendPayment($data);
    }

    // ── Payment Operations ────────────────────────────────────────────────────

    /**
     * Find and return a Payment by its reference UUID.
     *
     * @throws SnippeException
     */
    public function find(string $reference): Payment
    {
        $response = $this->http()->get("/payments/{$reference}");

        $this->throwIfFailed($response);

        return new Payment($response->json('data') ?? $response->json() ?? []);
    }

    /**
     * Alias for find() — used in the "verifyTransaction" naming convention.
     *
     * @throws SnippeException
     */
    public function verifyTransaction(string $reference): Payment
    {
        return $this->find($reference);
    }

    /**
     * List all payments with optional pagination.
     *
     * @throws SnippeException
     */
    public function payments(int $limit = 20, int $offset = 0): array
    {
        $response = $this->http()->get('/payments', [
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        $this->throwIfFailed($response);

        return $response->json() ?? [];
    }

    /**
     * Retrieve account balance.
     *
     * @throws SnippeException
     */
    public function balance(): array
    {
        $response = $this->http()->get('/balance');

        $this->throwIfFailed($response);

        return $response->json() ?? [];
    }

    /**
     * Retry a USSD push for a mobile money payment.
     *
     * @param  string       $reference  Payment reference UUID.
     * @param  string|null  $phone      Optional alternative phone number.
     * @throws SnippeException
     */
    public function push(string $reference, ?string $phone = null): array
    {
        $payload = array_filter(['phone_number' => $phone]);

        $response = $this->http()->post("/payments/{$reference}/push", $payload);

        $this->throwIfFailed($response);

        return $response->json() ?? [];
    }

    /**
     * Search for payments by reference or external reference.
     *
     * @throws SnippeException
     */
    public function search(string $query): array
    {
        $response = $this->http()->get('/payments/search', ['q' => $query]);

        $this->throwIfFailed($response);

        return $response->json() ?? [];
    }

    // ── Configuration ─────────────────────────────────────────────────────────

    /** Set the default webhook URL applied to all subsequent payments. */
    public function setWebhookUrl(string $url): static
    {
        $this->webhookUrl = $url;

        return $this;
    }

    /** Retrieve the currently configured default webhook URL. */
    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    /** Override the HTTP timeout (seconds). */
    public function setTimeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /** Override the API base URL (useful for testing against a mock server). */
    public function setBaseUrl(string $url): static
    {
        $this->baseUrl = rtrim($url, '/');

        return $this;
    }

    /** Override the API key at runtime (e.g. when credentials are stored in the database). */
    public function setApiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    // ── Internal HTTP ─────────────────────────────────────────────────────────

    /**
     * Execute an already-built session payload against the POST /sessions endpoint.
     *
     * Called by PaymentSessionBuilder::send().
     *
     * @throws SnippeException
     */
    public function sendSession(array $payload): PaymentSession
    {
        // Sessions endpoint lives at /api/v1/sessions regardless of the configured base URL.
        // We derive the host from baseUrl and always use the correct path.
        $host     = rtrim(preg_replace('|/v1$|', '', $this->baseUrl), '/');
        $response = $this->http()->post($host . '/api/v1/sessions', $payload);

        $this->throwIfFailed($response);

        return new PaymentSession($response->json('data') ?? $response->json() ?? []);
    }

    /**
     * Execute an already-built payment payload against the POST /payments endpoint.
     *
     * Called by PaymentBuilder::send() and the direct methods above.
     *
     * @throws SnippeException
     */
    public function sendPayment(array $payload): Payment
    {
        // Pull out our internal idempotency key if present
        $idempotencyKey = $payload['_idempotency_key'] ?? $this->generateIdempotencyKey();
        unset($payload['_idempotency_key']);

        $response = $this->http()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/payments', $payload);

        $this->throwIfFailed($response);

        return new Payment($response->json('data') ?? $response->json() ?? []);
    }

    /**
     * Build a pre-configured PendingRequest with auth + base URL + timeout.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson();
    }

    /**
     * Throw a SnippeException when the response indicates a failure.
     *
     * @throws SnippeException
     */
    protected function throwIfFailed(\Illuminate\Http\Client\Response $response): void
    {
        if ($response->failed()) {
            throw SnippeException::fromResponse($response);
        }
    }

    /**
     * Generate a random idempotency key for requests that don't supply one.
     */
    protected function generateIdempotencyKey(): string
    {
        return sprintf(
            '%s-%s',
            now()->format('YmdHis'),
            bin2hex(random_bytes(8)),
        );
    }
}
