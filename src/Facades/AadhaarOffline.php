<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Facades;

use Illuminate\Support\Facades\Facade;
use Shakil\AadhaarOffline\AadhaarOffline as AadhaarOfflineManager;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Security\CertificateStore;
use Shakil\AadhaarOffline\Testing\AadhaarOfflineFake;
use SplFileInfo;

/**
 * @method static OfflineKyc       fromZip(SplFileInfo|string $file, string $shareCode)
 * @method static OfflineKyc       fromXml(string $xml, ?string $shareCode = null)
 * @method static OfflineKyc       fromXmlFile(SplFileInfo|string $file, ?string $shareCode = null)
 * @method static OfflineKyc       fromSecureQr(string $qr)
 * @method static OfflineKyc       verifyZip(SplFileInfo|string $file, string $shareCode)
 * @method static OfflineKyc       verifyXml(string $xml, ?string $shareCode = null)
 * @method static OfflineKyc       verifySecureQr(string $qr)
 * @method static string|null      storePhoto(OfflineKyc $kyc, ?string $directory = null, ?string $disk = null)
 * @method static CertificateStore certificates()
 *
 * @see AadhaarOfflineManager
 */
class AadhaarOffline extends Facade
{
    /**
     * Replace the bound instance with a fake that returns canned payloads.
     *
     * @param OfflineKyc|array<string, mixed>|null $kyc The payload every read returns, or attributes for OfflineKycFactory
     */
    public static function fake(OfflineKyc|array|null $kyc = null): AadhaarOfflineFake
    {
        $fake = new AadhaarOfflineFake($kyc);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'aadhaar-offline';
    }
}
