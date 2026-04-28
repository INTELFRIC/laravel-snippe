<?php

namespace ShadrackJm\Snippe\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ShadrackJm\Snippe\Facades\Snippe;
use ShadrackJm\Snippe\SnippeServiceProvider;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SnippeServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Snippe' => Snippe::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('snippe.api_key', 'snp_test_key');
        $app['config']->set('snippe.base_url', 'https://api.snippe.sh/v1');
        $app['config']->set('snippe.webhook_url', null);
        $app['config']->set('snippe.timeout', 10);
        $app['config']->set('snippe.currency', 'TZS');
    }
}
