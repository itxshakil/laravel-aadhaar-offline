<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Shakil\AadhaarOffline\AadhaarOffline as AadhaarOfflineManager;
use Shakil\AadhaarOffline\Contracts\Verifier;
use Shakil\AadhaarOffline\Events\OfflineKycRejected;
use Shakil\AadhaarOffline\Events\OfflineKycVerified;
use Shakil\AadhaarOffline\Exceptions\InvalidShareCodeException;
use Shakil\AadhaarOffline\Exceptions\InvalidSignatureException;
use Shakil\AadhaarOffline\Exceptions\StaleKycException;
use Shakil\AadhaarOffline\Facades\AadhaarOffline;
use Shakil\AadhaarOffline\Testing\Fixtures;
use Shakil\AadhaarOffline\Tests\TestCase;

class AadhaarOfflineTest extends TestCase
{
    public function test_the_facade_resolves_the_manager(): void
    {
        $this->assertInstanceOf(AadhaarOfflineManager::class, AadhaarOffline::getFacadeRoot());
        $this->assertSame(app(AadhaarOfflineManager::class), app('aadhaar-offline'));
    }

    public function test_it_reads_and_verifies_an_uploaded_zip(): void
    {
        $upload = new UploadedFile(Fixtures::zip(Fixtures::signedXml()), 'offline.zip', 'application/zip', null, true);

        $kyc = AadhaarOffline::fromZip($upload, Fixtures::SHARE_CODE)->verify();

        $this->assertTrue($kyc->isVerified());
        $this->assertSame('Asha Verma', $kyc->name());
        $this->assertTrue($kyc->matchesMobile(Fixtures::MOBILE));
    }

    public function test_verify_zip_and_verify_xml_shortcuts(): void
    {
        $this->assertTrue(AadhaarOffline::verifyZip(Fixtures::zip(Fixtures::signedXml()), Fixtures::SHARE_CODE)->isVerified());
        $this->assertTrue(AadhaarOffline::verifyXml(Fixtures::signedXml(), Fixtures::SHARE_CODE)->isVerified());
    }

    public function test_it_reads_an_xml_file(): void
    {
        $kyc = AadhaarOffline::fromXmlFile(Fixtures::file(Fixtures::signedXml(), 'xml'), Fixtures::SHARE_CODE);

        $this->assertTrue($kyc->verify()->matchesEmail(Fixtures::EMAIL));
    }

    public function test_from_xml_validates_the_share_code_format(): void
    {
        $this->expectException(InvalidShareCodeException::class);

        AadhaarOffline::fromXml(Fixtures::signedXml(), 'abcd');
    }

    public function test_it_dispatches_a_verified_event_without_personal_data(): void
    {
        Event::fake();

        $kyc = AadhaarOffline::verifyXml(Fixtures::signedXml(['last_four' => '1357']));

        Event::assertDispatched(OfflineKycVerified::class, fn (OfflineKycVerified $event): bool => $event->referenceId === $kyc->referenceId()
            && $event->maskedAadhaar === 'XXXX XXXX 1357'
            && ! str_contains(serialize($event), 'Asha'));
    }

    public function test_it_dispatches_a_rejected_event_on_a_bad_signature(): void
    {
        Event::fake();

        try {
            AadhaarOffline::verifyXml(str_replace('Asha', 'Usha', Fixtures::signedXml()));
            $this->fail('Expected an invalid signature.');
        } catch (InvalidSignatureException) {
            Event::assertDispatched(OfflineKycRejected::class, fn (OfflineKycRejected $event): bool => $event->exception === InvalidSignatureException::class);
            Event::assertNotDispatched(OfflineKycVerified::class);
        }
    }

    public function test_events_can_be_disabled(): void
    {
        config()->set('aadhaar-offline.events.enabled', false);
        $this->refreshManager();
        Event::fake();

        AadhaarOffline::verifyXml(Fixtures::signedXml());

        Event::assertNothingDispatched();
    }

    public function test_it_rejects_stale_payloads_using_the_configured_age(): void
    {
        config()->set('aadhaar-offline.max_age_days', 1);
        $this->refreshManager();

        $this->expectException(StaleKycException::class);

        AadhaarOffline::verifyXml(Fixtures::signedXml(['generated_at' => new DateTimeImmutable('-2 days', new DateTimeZone('Asia/Kolkata'))]));
    }

    public function test_signature_verification_can_be_disabled_by_config(): void
    {
        config()->set('aadhaar-offline.verify_signature', false);
        $this->refreshManager();

        $this->assertTrue(AadhaarOffline::verifyXml(Fixtures::xml())->isVerified());
    }

    public function test_it_stores_the_photo_on_a_private_disk_with_a_random_name(): void
    {
        Storage::fake('local');

        $kyc = AadhaarOffline::verifyXml(Fixtures::signedXml(['last_four' => '2468']));
        $path = AadhaarOffline::storePhoto($kyc);

        $this->assertIsString($path);
        $this->assertStringStartsWith('aadhaar-offline/photos/', $path);
        $this->assertStringEndsWith('.png', $path);
        $this->assertStringNotContainsString('2468', basename($path, '.png'));
        Storage::disk('local')->assertExists($path);
    }

    private function refreshManager(): void
    {
        foreach (['aadhaar-offline', AadhaarOfflineManager::class, Verifier::class] as $abstract) {
            app()->forgetInstance($abstract);
        }

        AadhaarOffline::clearResolvedInstances();
    }
}
