<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Shakil\AadhaarOffline\AadhaarOffline;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Data\Photo;
use Shakil\AadhaarOffline\Data\SignedBytes;
use Shakil\AadhaarOffline\Enums\Gender;
use Shakil\AadhaarOffline\Enums\PayloadSource;
use Shakil\AadhaarOffline\Exceptions\AadhaarOfflineException;
use Shakil\AadhaarOffline\Exceptions\MalformedPayloadException;
use Shakil\AadhaarOffline\Security\CertificateStore;
use Shakil\AadhaarOffline\Security\KycVerifier;
use Shakil\AadhaarOffline\Security\QrSignatureVerifier;
use Shakil\AadhaarOffline\Security\SignatureVerifier;

/**
 * Runs every check on a real Offline e-KYC file and reports which stage passes or fails.
 *
 * Accepts the share-code ZIP, an Offline e-KYC XML file, or a text file holding the
 * scanned digits of a Secure QR code.
 *
 * Prints only structural facts (which fields are present, masked Aadhaar, timestamps),
 * never the resident's name, date of birth, address or photo. The share code is
 * prompted for as a secret so it never lands in shell history.
 */
class CheckPayload extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aadhaar-offline:check {file : Path to the Offline e-KYC ZIP, the XML, or a text file with the scanned Secure QR digits}';

    /**
     * @var string
     */
    protected $description = 'Check an Offline e-KYC file stage by stage (open, parse, signature, freshness) without printing personal data';

    /**
     * Execute the console command.
     */
    public function handle(AadhaarOffline $aadhaarOffline, SignatureVerifier $signatures, QrSignatureVerifier $qrSignatures): int
    {
        $file = $this->argument('file');
        $file = is_string($file) ? $file : '';

        try {
            $kyc = $this->read($aadhaarOffline, $file);
        } catch (AadhaarOfflineException $exception) {
            $this->failed('Open and parse', $exception);

            return self::FAILURE;
        }

        $this->stage('Open and parse', true, $kyc->source() === PayloadSource::SecureQr ? 'Secure QR code read' : 'Offline e-KYC XML read');
        $this->describe($kyc);

        $signatureOk = $this->checkSignature($kyc, $signatures, $qrSignatures);
        $freshOk = $this->checkFreshness($kyc);

        return $signatureOk && $freshOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @throws AadhaarOfflineException
     */
    private function read(AadhaarOffline $aadhaarOffline, string $file): OfflineKyc
    {
        if (str_ends_with(mb_strtolower($file), '.zip')) {
            return $aadhaarOffline->fromZip($file, $this->shareCode());
        }

        if (! is_file($file) || ! is_readable($file)) {
            throw new MalformedPayloadException('The file could not be read.');
        }

        $contents = trim((string) file_get_contents($file));

        return preg_match('/^\d+$/', $contents) === 1
            ? $aadhaarOffline->fromSecureQr($contents)
            : $aadhaarOffline->fromXml($contents);
    }

    private function shareCode(): string
    {
        $shareCode = $this->secret('Share code');

        return is_string($shareCode) ? $shareCode : '';
    }

    private function describe(OfflineKyc $kyc): void
    {
        $photo = $kyc->photo();
        $address = array_filter($kyc->address()->toArray(), static fn (mixed $value): bool => $value !== null && $value !== '');

        $this->components->twoColumnDetail('  Masked Aadhaar', $kyc->maskedAadhaar());
        $this->components->twoColumnDetail('  Generated at (IST)', $kyc->generatedAt()->format('Y-m-d H:i:s'));
        $this->components->twoColumnDetail('  Name', $kyc->name() !== null ? 'present' : '<fg=yellow>missing</>');
        $this->components->twoColumnDetail('  Date of birth', match (true) {
            $kyc->dob() instanceof DateTimeImmutable => 'full date',
            $kyc->birthYear() !== null => 'year only',
            default => '<fg=yellow>missing</>',
        });
        $this->components->twoColumnDetail('  Gender', $kyc->gender() instanceof Gender ? 'present' : '<fg=yellow>missing or unrecognised</>');
        $this->components->twoColumnDetail('  Address fields', (string) count($address));
        $this->components->twoColumnDetail('  Photo', $photo instanceof Photo ? $photo->mimeType() . ', ' . $photo->size() . ' bytes' : '<fg=yellow>missing</>');
        $this->components->twoColumnDetail('  Mobile linked', $kyc->hasMobile() ? 'yes' : 'no');
        $this->components->twoColumnDetail('  Email linked', $kyc->hasEmail() ? 'yes' : 'no');
    }

    private function checkSignature(OfflineKyc $kyc, SignatureVerifier $signatures, QrSignatureVerifier $qrSignatures): bool
    {
        if (! (bool) config('aadhaar-offline.verify_signature', true)) {
            $this->stage('Signature', false, 'skipped: AADHAAR_OFFLINE_VERIFY_SIGNATURE=false');

            return false;
        }

        $signedBytes = $kyc->signedBytes();
        $xml = (string) $kyc->rawXml();

        try {
            if ($signedBytes instanceof SignedBytes) {
                $qrSignatures->verify($signedBytes->data, $signedBytes->signature);
            } else {
                $signatures->verify($xml);
            }
        } catch (AadhaarOfflineException $exception) {
            $this->failed('Signature', $exception);

            if (! $signedBytes instanceof SignedBytes) {
                $this->describeEmbeddedCertificate($xml);
            }

            return false;
        }

        $this->stage('Signature', true, 'valid, signed by a configured certificate');

        return true;
    }

    private function describeEmbeddedCertificate(string $xml): void
    {
        try {
            $pem = SignatureVerifier::embeddedCertificate($xml);
        } catch (AadhaarOfflineException) {
            return;
        }

        if ($pem === null) {
            return;
        }

        $certificate = CertificateStore::describePem($pem);

        $this->newLine();
        $this->components->warn('The file says it was signed with the certificate below. It is NOT trusted because it came from the file.');
        $this->components->twoColumnDetail('  Subject', $certificate['subject']);
        $this->line('  SHA-256 fingerprint');
        $this->line('  <fg=gray>' . $certificate['fingerprint'] . '</>');
        $this->components->twoColumnDetail('  Valid to', $certificate['valid_to']?->format('Y-m-d') ?? 'unknown');
        $this->components->info('Download the certificate from uidai.gov.in. If its fingerprint matches, add it to AADHAAR_OFFLINE_CERTIFICATES.');
    }

    private function checkFreshness(OfflineKyc $kyc): bool
    {
        $maxAgeDays = $kyc->source() === PayloadSource::SecureQr
            ? KycVerifier::normalizeMaxAgeDays(config('aadhaar-offline.secure_qr.max_age_days'))
            : KycVerifier::normalizeMaxAgeDays(config('aadhaar-offline.max_age_days', 3));
        $age = $kyc->ageInDays() . ' day(s) old';

        if ($maxAgeDays === null) {
            $this->stage('Freshness', true, $age . ', no limit configured');

            return true;
        }

        $fresh = $kyc->isFresh($maxAgeDays);
        $this->stage('Freshness', $fresh, $age . ", limit {$maxAgeDays}");

        return $fresh;
    }

    private function failed(string $name, AadhaarOfflineException $exception): void
    {
        $this->stage($name, false, class_basename($exception));
        $this->line('       ' . $exception->getMessage());
    }

    private function stage(string $name, bool $passed, string $detail): void
    {
        $this->components->twoColumnDetail(
            ($passed ? '<fg=green>PASS</>' : '<fg=red>FAIL</>') . ' ' . $name,
            $detail,
        );
    }
}
