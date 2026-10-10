<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Shakil\AadhaarOffline\Security\CertificateStore;
use Shakil\AadhaarOffline\Tests\TestCase;

class AboutCommandTest extends TestCase
{
    public function test_about_shows_certificate_health(): void
    {
        $this->assertSame(0, Artisan::call('about', ['--only' => 'aadhaar offline']));
        $output = Artisan::output();

        $this->assertStringContainsString('Aadhaar Offline', $output);
        $this->assertStringContainsString('ENABLED', $output);
        $this->assertStringContainsString('1 valid, 0 expired', $output);
        $this->assertStringContainsString('3 days / none', $output);
    }

    public function test_about_flags_disabled_checks_and_missing_certificates(): void
    {
        config()->set('aadhaar-offline.verify_signature', false);
        config()->set('aadhaar-offline.certificates', []);
        app()->forgetInstance(CertificateStore::class);

        $this->assertSame(0, Artisan::call('about', ['--only' => 'aadhaar offline']));
        $output = Artisan::output();

        $this->assertStringContainsString('DISABLED', $output);
        $this->assertStringContainsString('NOT CONFIGURED', $output);
    }
}
