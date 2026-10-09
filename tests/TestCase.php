<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Shakil\AadhaarOffline\AadhaarOfflineServiceProvider;
use Shakil\AadhaarOffline\Facades\AadhaarOffline;
use Shakil\AadhaarOffline\Tests\Support\Fixtures;

abstract class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param  Application              $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AadhaarOfflineServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  Application                 $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'AadhaarOffline' => AadhaarOffline::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('aadhaar-offline.certificates', [Fixtures::certificateFile()]);
        $app['config']->set('aadhaar-offline.verify_signature', true);
        $app['config']->set('aadhaar-offline.max_age_days', 3);
        $app['config']->set('aadhaar-offline.events.enabled', true);
    }
}
