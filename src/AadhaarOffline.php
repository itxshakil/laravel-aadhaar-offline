<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Str;
use SensitiveParameter;
use Shakil\AadhaarOffline\Contracts\Verifier;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Data\Photo;
use Shakil\AadhaarOffline\Exceptions\AadhaarOfflineException;
use Shakil\AadhaarOffline\Exceptions\MalformedPayloadException;
use Shakil\AadhaarOffline\Parsers\SecureQrParser;
use Shakil\AadhaarOffline\Parsers\XmlParser;
use Shakil\AadhaarOffline\Parsers\ZipReader;
use Shakil\AadhaarOffline\Security\CertificateStore;
use SplFileInfo;

/**
 * Entry point for reading and verifying Aadhaar Offline e-KYC payloads.
 *
 * Every factory method returns an unverified OfflineKyc. Call verify() on it
 * (or use verifyZip() / verifyXml()) before trusting any of its data.
 */
class AadhaarOffline
{
    public function __construct(
        protected readonly ZipReader $zipReader,
        protected readonly XmlParser $parser,
        protected readonly Verifier $verifier,
        protected readonly CertificateStore $certificates,
        protected readonly ?FilesystemFactory $filesystem = null,
        protected readonly ?string $photoDisk = null,
        protected readonly string $photoDirectory = 'aadhaar-offline/photos',
        protected readonly SecureQrParser $qrParser = new SecureQrParser(),
    ) {}

    /**
     * Read the share-code-protected ZIP downloaded from UIDAI.
     *
     * @param SplFileInfo|string $file An UploadedFile, SplFileInfo or a path
     *
     * @throws AadhaarOfflineException
     */
    public function fromZip(SplFileInfo|string $file, #[SensitiveParameter] string $shareCode): OfflineKyc
    {
        $xml = $this->zipReader->read($this->path($file), $shareCode);

        return $this->parser->parse($xml, $shareCode, $this->verifier);
    }

    /**
     * Read a signed Offline e-KYC XML string, e.g. one returned by a KYC vendor API.
     *
     * The share code is only needed for mobile/email matching.
     *
     * @throws AadhaarOfflineException
     */
    public function fromXml(string $xml, #[SensitiveParameter] ?string $shareCode = null): OfflineKyc
    {
        if ($shareCode !== null) {
            ZipReader::assertShareCode($shareCode);
        }

        return $this->parser->parse($xml, $shareCode, $this->verifier);
    }

    /**
     * Read a signed Offline e-KYC XML file.
     *
     * @throws AadhaarOfflineException
     */
    public function fromXmlFile(SplFileInfo|string $file, #[SensitiveParameter] ?string $shareCode = null): OfflineKyc
    {
        $path = $this->path($file);

        if (! is_file($path) || ! is_readable($path)) {
            throw new MalformedPayloadException('The Offline e-KYC XML file could not be read.');
        }

        return $this->fromXml((string) file_get_contents($path), $shareCode);
    }

    /**
     * Read the Secure QR code printed on an Aadhaar letter, PVC card or e-Aadhaar PDF.
     *
     * Pass the scanned QR text: a long string of digits.
     *
     * @throws AadhaarOfflineException
     */
    public function fromSecureQr(string $qr): OfflineKyc
    {
        return $this->qrParser->parse($qr, $this->verifier);
    }

    /**
     * Read and verify a ZIP in one step.
     *
     * @throws AadhaarOfflineException
     */
    public function verifyZip(SplFileInfo|string $file, #[SensitiveParameter] string $shareCode): OfflineKyc
    {
        return $this->fromZip($file, $shareCode)->verify();
    }

    /**
     * Read and verify an XML string in one step.
     *
     * @throws AadhaarOfflineException
     */
    public function verifyXml(string $xml, #[SensitiveParameter] ?string $shareCode = null): OfflineKyc
    {
        return $this->fromXml($xml, $shareCode)->verify();
    }

    /**
     * Read and verify a Secure QR code in one step.
     *
     * @throws AadhaarOfflineException
     */
    public function verifySecureQr(string $qr): OfflineKyc
    {
        return $this->fromSecureQr($qr)->verify();
    }

    /**
     * Store the resident photo under a random name on the configured (private) disk.
     *
     * Returns the stored path, or null when the payload has no photo.
     */
    public function storePhoto(OfflineKyc $kyc, ?string $directory = null, ?string $disk = null): ?string
    {
        $photo = $kyc->photo();

        if (! $photo instanceof Photo) {
            return null;
        }

        if (! $this->filesystem instanceof FilesystemFactory) {
            throw new AadhaarOfflineException('No filesystem is available to store the photo.');
        }

        $path = rtrim($directory ?? $this->photoDirectory, '/') . '/' . Str::random(40) . '.' . $photo->extension();

        $this->filesystem->disk($disk ?? $this->photoDisk)->put($path, $photo->contents(), 'private');

        return $path;
    }

    /**
     * The trusted UIDAI certificates.
     */
    public function certificates(): CertificateStore
    {
        return $this->certificates;
    }

    protected function path(SplFileInfo|string $file): string
    {
        if ($file instanceof SplFileInfo) {
            $realPath = $file->getRealPath();

            return $realPath !== false ? $realPath : $file->getPathname();
        }

        return $file;
    }
}
