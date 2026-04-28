<?php

namespace ShadrackJm\Snippe\Facades;

use Illuminate\Support\Facades\Facade;
use ShadrackJm\Snippe\Payment;
use ShadrackJm\Snippe\PaymentBuilder;
use ShadrackJm\Snippe\PaymentSession;
use ShadrackJm\Snippe\PaymentSessionBuilder;
use ShadrackJm\Snippe\SnippeClient;

/**
 * Snippe Facade.
 *
 * Provides static access to SnippeClient methods from anywhere in your app.
 *
 * @method static PaymentBuilder        mobileMoney(int $amount, string $phone)
 * @method static PaymentBuilder        card(int $amount)
 * @method static PaymentBuilder        qr(int $amount)
 * @method static PaymentSessionBuilder session(?int $amount = null)
 * @method static Payment               createPayment(array $data)
 * @method static Payment               initiateMobilePayment(array $data)
 * @method static Payment               sendPayment(array $payload)
 * @method static PaymentSession        sendSession(array $payload)
 * @method static Payment               find(string $reference)
 * @method static Payment               verifyTransaction(string $reference)
 * @method static array                 payments(int $limit = 20, int $offset = 0)
 * @method static array                 balance()
 * @method static array                 push(string $reference, ?string $phone = null)
 * @method static array                 search(string $query)
 * @method static SnippeClient          setApiKey(string $apiKey)
 * @method static SnippeClient          setWebhookUrl(string $url)
 * @method static SnippeClient          setTimeout(int $seconds)
 * @method static SnippeClient          setBaseUrl(string $url)
 * @method static string|null           getWebhookUrl()
 *
 * @see \ShadrackJm\Snippe\SnippeClient
 */
class Snippe extends Facade
{
    /**
     * Get the registered name of the component in the service container.
     */
    protected static function getFacadeAccessor(): string
    {
        return SnippeClient::class;
    }
}
