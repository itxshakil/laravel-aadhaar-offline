<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Shakil\AadhaarOffline\AadhaarOffline;
use Shakil\AadhaarOffline\Security\CertificateStore;
use Shakil\AadhaarOffline\Tests\TestCase;

class ListCertificatesCommandTest extends TestCase
{
    public function test_it_lists_the_trusted_certificates(): void
    {
        $this->assertSame(0, Artisan::call('aadhaar-offline:certs'));
        $this->assertStringContainsString('uidai-test', Artisan::output());
    }

    public function test_it_fails_when_no_certificate_is_configured(): void
    {
        config()->set('aadhaar-offline.certificates', []);
        app()->forgetInstance(CertificateStore::class);
        app()->forgetInstance(AadhaarOffline::class);

        $this->assertSame(1, Artisan::call('aadhaar-offline:certs'));
        $this->assertStringContainsString('AADHAAR_OFFLINE_CERTIFICATES', Artisan::output());
    }

    public function test_it_warns_when_signature_verification_is_disabled(): void
    {
        config()->set('aadhaar-offline.verify_signature', false);

        $this->assertSame(0, Artisan::call('aadhaar-offline:certs'));
        $this->assertStringContainsString('DISABLED', Artisan::output());
    }

    public function test_it_warns_about_certificates_that_expire_soon(): void
    {
        $this->assertSame(0, Artisan::call('aadhaar-offline:certs', ['--warn-days' => 400]));
        $this->assertStringContainsString('expires soon', Artisan::output());
    }

    public function test_it_does_not_warn_about_certificates_far_from_expiry(): void
    {
        $this->assertSame(0, Artisan::call('aadhaar-offline:certs', ['--warn-days' => 30]));
        $this->assertStringNotContainsString('expires soon', Artisan::output());
    }
}
