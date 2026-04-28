<?php

namespace ShadrackJm\Snippe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use ShadrackJm\Snippe\Exceptions\SnippeException;
use ShadrackJm\Snippe\Webhook;

/**
 * WebhookController
 *
 * Handles incoming POST requests from the Snippe payment gateway.
 *
 * The package automatically registers a route at the path defined by
 * config('snippe.webhook_path') — default: POST /snippe/webhook.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * CUSTOMISING WEBHOOK HANDLING
 * ────────────────────────────────────────────────────────────────────────────
 * Option 1 — Listen to the events this controller fires:
 *
 *   // In EventServiceProvider or using #[AsEventListener]
 *   Event::listen(\ShadrackJm\Snippe\Events\PaymentCompleted::class, function ($event) {
 *       $order = Order::where('payment_ref', $event->reference)->firstOrFail();
 *       $order->markPaid();
 *   });
 *
 * Option 2 — Replace this controller entirely:
 *   1. Set SNIPPE_WEBHOOK_PATH=false in .env to disable the package route.
 *   2. Register your own route and controller.
 *   3. Use Webhook::capture() or Webhook::fromRaw() in your handler.
 *
 * ────────────────────────────────────────────────────────────────────────────
 */
class WebhookController extends Controller
{
    /**
     * Handle an incoming Snippe webhook event.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $event = Webhook::fromRaw(
                body: $request->getContent(),
                headers: $request->headers->all(),
            );
        } catch (SnippeException $e) {
            Log::warning('[Snippe] Invalid webhook payload received.', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info('[Snippe] Webhook received.', [
            'event'     => $event->eventType(),
            'reference' => $event->reference(),
            'status'    => $event->status(),
            'amount'    => $event->amount(),
        ]);

        // ── Dispatch to your application ──────────────────────────────────────
        // Fire a Laravel event so your application can respond without
        // modifying this controller.

        if ($event->isPaymentCompleted()) {
            $this->onPaymentCompleted($event);
        }

        if ($event->isPaymentFailed()) {
            $this->onPaymentFailed($event);
        }

        // Always return 200 so Snippe stops retrying.
        return response()->json(['received' => true], 200);
    }

    // ── Hook Methods ──────────────────────────────────────────────────────────
    // Override these in a subclass, or listen to the fired events.

    /**
     * Called when a payment.completed event is received.
     *
     * Override this method by extending this controller, or listen to the
     * 'snippe.payment.completed' event in your EventServiceProvider.
     */
    protected function onPaymentCompleted(Webhook $event): void
    {
        // Fire a generic Laravel event — listen to 'snippe.payment.completed'
        // in your app's EventServiceProvider.
        event('snippe.payment.completed', [
            'reference' => $event->reference(),
            'amount'    => $event->amount(),
            'currency'  => $event->currency(),
            'customer'  => $event->customer(),
            'metadata'  => $event->metadata(),
            'payload'   => $event->payload(),
        ]);
    }

    /**
     * Called when a payment.failed event is received.
     */
    protected function onPaymentFailed(Webhook $event): void
    {
        event('snippe.payment.failed', [
            'reference' => $event->reference(),
            'amount'    => $event->amount(),
            'customer'  => $event->customer(),
            'metadata'  => $event->metadata(),
            'payload'   => $event->payload(),
        ]);
    }
}
