<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use PHPUnit\Framework\AssertionFailedError;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Exceptions\InvalidShareCodeException;
use Shakil\AadhaarOffline\Exceptions\InvalidSignatureException;
use Shakil\AadhaarOffline\Exceptions\MalformedPayloadException;
use Shakil\AadhaarOffline\Exceptions\StaleKycException;
use Shakil\AadhaarOffline\Facades\AadhaarOffline;
use Shakil\AadhaarOffline\Testing\OfflineKycFactory;
use Shakil\AadhaarOffline\Tests\TestCase;

class FakeTest extends TestCase
{
    public function test_it_returns_a_synthetic_payload_and_records_reads_and_verification(): void
    {
        $fake = AadhaarOffline::fake(['name' => 'Ravi Kumar', 'mobile' => '9123456789']);

        $kyc = AadhaarOffline::fromZip('/does/not/matter.zip', '1234')->verify();

        $this->assertSame('Ravi Kumar', $kyc->name());
        $this->assertTrue($kyc->matchesMobile('9123456789'));
        $fake->assertRead(1);
        $fake->assertZipRead();
        $fake->assertVerified(fn (OfflineKyc $verified): bool => $verified->name() === 'Ravi Kumar');
        $this->assertCount(1, $fake->verifiedPayloads());
    }

    public function test_it_can_reject_share_code_and_malformed_files(): void
    {
        AadhaarOffline::fake()->rejectShareCode();
        $this->assertThrows(fn () => AadhaarOffline::fromZip('x.zip', '1234'), InvalidShareCodeException::class);

        AadhaarOffline::fake()->rejectAsMalformed();
        $this->assertThrows(fn () => AadhaarOffline::fromXml('<x/>'), MalformedPayloadException::class);
    }

    public function test_it_can_reject_signature_and_freshness(): void
    {
        $fake = AadhaarOffline::fake()->rejectSignature();
        $this->assertThrows(fn () => AadhaarOffline::fromXml('<x/>')->verify(), InvalidSignatureException::class);
        $fake->assertNotVerified();

        AadhaarOffline::fake()->rejectAsStale();
        $this->assertThrows(fn () => AadhaarOffline::verifyXml('<x/>'), StaleKycException::class);
    }

    public function test_it_records_stored_photos(): void
    {
        $fake = AadhaarOffline::fake();
        $fake->assertNoPhotoStored();

        $path = AadhaarOffline::storePhoto(AadhaarOffline::fromXml('<x/>'));

        $this->assertSame('aadhaar-offline/photos/fake-1.jpg', $path);
        $fake->assertPhotoStored();
        $this->assertNull(AadhaarOffline::storePhoto(OfflineKycFactory::make(['photo' => null])));
    }

    public function test_it_accepts_a_prebuilt_payload(): void
    {
        $kyc = OfflineKycFactory::make(['last_four' => '7777']);
        AadhaarOffline::fake($kyc);

        $this->assertSame($kyc, AadhaarOffline::fromXmlFile('ignored.xml'));
    }

    public function test_assertions_fail_when_nothing_happened(): void
    {
        $fake = AadhaarOffline::fake();
        $fake->assertNothingRead();

        $this->expectException(AssertionFailedError::class);

        $fake->assertVerified();
    }
}
