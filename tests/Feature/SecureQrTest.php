<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Event;
use Shakil\AadhaarOffline\AadhaarOffline as AadhaarOfflineManager;
use Shakil\AadhaarOffline\Contracts\Verifier;
use Shakil\AadhaarOffline\Events\OfflineKycRejected;
use Shakil\AadhaarOffline\Events\OfflineKycVerified;
use Shakil\AadhaarOffline\Exceptions\InvalidSignatureException;
use Shakil\AadhaarOffline\Exceptions\StaleKycException;
use Shakil\AadhaarOffline\Facades\AadhaarOffline;
use Shakil\AadhaarOffline\Tests\Support\Fixtures;
use Shakil\AadhaarOffline\Tests\TestCase;

class SecureQrTest extends TestCase
{
    public function test_it_reads_and_verifies_a_secure_qr_code(): void
    {
        Event::fake();

        $kyc = AadhaarOffline::verifySecureQr(Fixtures::secureQr());

        $this->assertTrue($kyc->isVerified());
        $this->assertSame('Asha Verma', $kyc->name());
        $this->assertSame('secure_qr', $kyc->toArray()['source']);
        Event::assertDispatched(OfflineKycVerified::class, fn (OfflineKycVerified $event): bool => $event->maskedAadhaar === 'XXXX XXXX 5678');
    }

    public function test_it_rejects_a_qr_code_signed_by_an_untrusted_key(): void
    {
        Event::fake();

        try {
            AadhaarOffline::verifySecureQr(Fixtures::secureQr(identity: 'attacker'));
            $this->fail('Expected the forged Secure QR code to be rejected.');
        } catch (InvalidSignatureException) {
            Event::assertDispatched(OfflineKycRejected::class, fn (OfflineKycRejected $event): bool => $event->exception === InvalidSignatureException::class);
        }
    }

    public function test_a_years_old_printed_card_passes_by_default(): void
    {
        $kyc = AadhaarOffline::verifySecureQr(Fixtures::secureQr(['generated_at' => new DateTimeImmutable('-3 years')]));

        $this->assertTrue($kyc->isVerified());
    }

    public function test_the_qr_age_limit_is_separate_from_the_xml_one(): void
    {
        config()->set('aadhaar-offline.max_age_days', 365);
        config()->set('aadhaar-offline.secure_qr.max_age_days', 30);
        $this->refreshManager();

        $this->assertTrue(AadhaarOffline::verifyXml(Fixtures::signedXml(['generated_at' => new DateTimeImmutable('-60 days', new DateTimeZone('Asia/Kolkata'))]))->isVerified());

        $this->expectException(StaleKycException::class);

        AadhaarOffline::verifySecureQr(Fixtures::secureQr(['generated_at' => new DateTimeImmutable('-60 days')]));
    }

    public function test_disabling_signature_checks_covers_qr_codes(): void
    {
        config()->set('aadhaar-offline.verify_signature', false);
        $this->refreshManager();

        $this->assertTrue(AadhaarOffline::verifySecureQr(Fixtures::secureQr(identity: 'attacker'))->isVerified());
    }

    public function test_the_fake_records_qr_reads(): void
    {
        $fake = AadhaarOffline::fake(['name' => 'Ravi Kumar']);

        $kyc = AadhaarOffline::verifySecureQr('123');

        $this->assertSame('Ravi Kumar', $kyc->name());
        $fake->assertSecureQrRead();
        $fake->assertVerified();
    }

    private function refreshManager(): void
    {
        foreach (['aadhaar-offline', AadhaarOfflineManager::class, Verifier::class] as $abstract) {
            app()->forgetInstance($abstract);
        }

        AadhaarOffline::clearResolvedInstances();
    }
}
