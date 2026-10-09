<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Testing;

use Closure;
use Override;
use PHPUnit\Framework\Assert;
use SensitiveParameter;
use Shakil\AadhaarOffline\AadhaarOffline;
use Shakil\AadhaarOffline\Contracts\Verifier;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Data\Photo;
use Shakil\AadhaarOffline\Exceptions\AadhaarOfflineException;
use Shakil\AadhaarOffline\Exceptions\InvalidShareCodeException;
use Shakil\AadhaarOffline\Exceptions\InvalidSignatureException;
use Shakil\AadhaarOffline\Exceptions\MalformedPayloadException;
use Shakil\AadhaarOffline\Exceptions\StaleKycException;
use Shakil\AadhaarOffline\Parsers\XmlParser;
use Shakil\AadhaarOffline\Parsers\ZipReader;
use Shakil\AadhaarOffline\Security\CertificateStore;
use SplFileInfo;

/**
 * Test double for the AadhaarOffline facade. Returns synthetic payloads and records every call.
 *
 * Usage: AadhaarOffline::fake(['name' => 'Asha Verma'])->rejectSignature();
 */
class AadhaarOfflineFake extends AadhaarOffline implements Verifier
{
    /** @var list<array{source: string, share_code_given: bool}> */
    private array $reads = [];

    /** @var list<OfflineKyc> */
    private array $verified = [];

    /** @var list<string> */
    private array $storedPhotos = [];

    private ?AadhaarOfflineException $readFailure = null;

    private ?AadhaarOfflineException $verifyFailure = null;

    /**
     * @param OfflineKyc|array<string, mixed>|null $kyc
     */
    public function __construct(
        private readonly OfflineKyc|array|null $kyc = null,
    ) {
        parent::__construct(new ZipReader(), new XmlParser(), $this, new CertificateStore());
    }

    /**
     * Make every read fail as if the share code were wrong.
     */
    public function rejectShareCode(): self
    {
        $this->readFailure = new InvalidShareCodeException('The share code does not open this Offline e-KYC ZIP.');

        return $this;
    }

    /**
     * Make every read fail as if the file were not an Offline e-KYC payload.
     */
    public function rejectAsMalformed(): self
    {
        $this->readFailure = new MalformedPayloadException('The XML is not an Aadhaar Offline e-KYC document.');

        return $this;
    }

    /**
     * Make verify() fail as if the UIDAI signature were invalid.
     */
    public function rejectSignature(): self
    {
        $this->verifyFailure = new InvalidSignatureException('The UIDAI signature does not verify against any trusted certificate.');

        return $this;
    }

    /**
     * Make verify() fail as if the payload were too old.
     */
    public function rejectAsStale(): self
    {
        $this->verifyFailure = new StaleKycException('The Offline e-KYC payload is older than allowed.');

        return $this;
    }

    #[Override]
    public function fromZip(SplFileInfo|string $file, #[SensitiveParameter] string $shareCode): OfflineKyc
    {
        return $this->read('zip', true);
    }

    #[Override]
    public function fromXml(string $xml, #[SensitiveParameter] ?string $shareCode = null): OfflineKyc
    {
        return $this->read('xml', $shareCode !== null);
    }

    #[Override]
    public function fromXmlFile(SplFileInfo|string $file, #[SensitiveParameter] ?string $shareCode = null): OfflineKyc
    {
        return $this->read('xml-file', $shareCode !== null);
    }

    #[Override]
    public function fromSecureQr(string $qr): OfflineKyc
    {
        return $this->read('secure-qr', false);
    }

    #[Override]
    public function storePhoto(OfflineKyc $kyc, ?string $directory = null, ?string $disk = null): ?string
    {
        if (! $kyc->photo() instanceof Photo) {
            return null;
        }

        $path = rtrim($directory ?? 'aadhaar-offline/photos', '/') . '/fake-' . (count($this->storedPhotos) + 1) . '.' . $kyc->photo()->extension();
        $this->storedPhotos[] = $path;

        return $path;
    }

    /**
     * @internal Called by OfflineKyc::verify() on payloads returned by this fake.
     */
    public function verify(OfflineKyc $kyc): void
    {
        if ($this->verifyFailure instanceof AadhaarOfflineException) {
            throw $this->verifyFailure;
        }

        $this->verified[] = $kyc;
    }

    /**
     * Assert a payload was read, optionally an exact number of times.
     */
    public function assertRead(?int $times = null): void
    {
        if ($times === null) {
            Assert::assertNotEmpty($this->reads, 'Expected an Offline e-KYC payload to be read, but none was.');

            return;
        }

        Assert::assertCount($times, $this->reads, "Expected {$times} Offline e-KYC read(s), got " . count($this->reads) . '.');
    }

    /**
     * Assert a ZIP was read.
     */
    public function assertZipRead(): void
    {
        Assert::assertContains('zip', array_column($this->reads, 'source'), 'Expected an Offline e-KYC ZIP to be read, but none was.');
    }

    /**
     * Assert a Secure QR code was read.
     */
    public function assertSecureQrRead(): void
    {
        Assert::assertContains('secure-qr', array_column($this->reads, 'source'), 'Expected an Aadhaar Secure QR code to be read, but none was.');
    }

    /**
     * Assert no payload was read.
     */
    public function assertNothingRead(): void
    {
        Assert::assertEmpty($this->reads, 'Expected no Offline e-KYC payload to be read, but ' . count($this->reads) . ' were.');
    }

    /**
     * Assert a payload was verified, optionally matching a callback.
     *
     * @param (Closure(OfflineKyc): bool)|null $callback
     */
    public function assertVerified(?Closure $callback = null): void
    {
        $matches = $callback instanceof Closure ? array_filter($this->verified, $callback) : $this->verified;

        Assert::assertNotEmpty($matches, 'Expected an Offline e-KYC payload to be verified, but none was.');
    }

    /**
     * Assert no payload was successfully verified.
     */
    public function assertNotVerified(): void
    {
        Assert::assertEmpty($this->verified, 'Expected no Offline e-KYC payload to be verified, but ' . count($this->verified) . ' were.');
    }

    /**
     * Assert a resident photo was stored.
     */
    public function assertPhotoStored(): void
    {
        Assert::assertNotEmpty($this->storedPhotos, 'Expected a resident photo to be stored, but none was.');
    }

    /**
     * Assert no resident photo was stored.
     */
    public function assertNoPhotoStored(): void
    {
        Assert::assertEmpty($this->storedPhotos, 'Expected no resident photo to be stored.');
    }

    /**
     * Payloads that passed verify().
     *
     * @return list<OfflineKyc>
     */
    public function verifiedPayloads(): array
    {
        return $this->verified;
    }

    private function read(string $source, bool $shareCodeGiven): OfflineKyc
    {
        $this->reads[] = ['source' => $source, 'share_code_given' => $shareCodeGiven];

        if ($this->readFailure instanceof AadhaarOfflineException) {
            throw $this->readFailure;
        }

        if ($this->kyc instanceof OfflineKyc) {
            return $this->kyc;
        }

        return OfflineKycFactory::make($this->kyc ?? [], $this);
    }
}
