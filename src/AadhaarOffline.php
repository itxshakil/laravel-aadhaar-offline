<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Str;
use Shakil\AadhaarOffline\Contracts\Verifier;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Data\Photo;
use Shakil\AadhaarOffline\Exceptions\AadhaarOfflineException;
use Shakil\AadhaarOffline\Parsers\SecureQrParser;
use Shakil\AadhaarOffline\Parsers\XmlParser;
use Shakil\AadhaarOffline\Parsers\ZipReader;
use Shakil\AadhaarOffline\Security\CertificateStore;

/**
 * The manager behind the AadhaarOffline facade.
 *
 * Reading and verifying come from OfflineKycReader in itxshakil/aadhaar-offline.
 * This adds what needs Laravel, such as storing the resident photo on a disk.
 * Every reader returns an unverified OfflineKyc; call verify() before trusting it.
 */
class AadhaarOffline extends OfflineKycReader
{
    public function __construct(
        ZipReader $zipReader,
        XmlParser $parser,
        Verifier $verifier,
        CertificateStore $certificates,
        protected readonly ?FilesystemFactory $filesystem = null,
        protected readonly ?string $photoDisk = null,
        protected readonly string $photoDirectory = 'aadhaar-offline/photos',
        SecureQrParser $qrParser = new SecureQrParser(),
    ) {
        parent::__construct($zipReader, $parser, $verifier, $certificates, $qrParser);
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
}
