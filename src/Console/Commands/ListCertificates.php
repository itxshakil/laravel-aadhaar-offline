<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Shakil\AadhaarOffline\AadhaarOffline;
use Shakil\AadhaarOffline\Exceptions\ConfigurationException;

/**
 * Lists the UIDAI certificates trusted for signature verification, with fingerprints and expiry.
 */
class ListCertificates extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aadhaar-offline:certs
        {--warn-days=30 : Warn about certificates expiring within this many days}';

    /**
     * @var string
     */
    protected $description = 'List the UIDAI certificates trusted to sign Offline e-KYC payloads';

    /**
     * Execute the console command.
     */
    public function handle(AadhaarOffline $aadhaarOffline): int
    {
        if (! (bool) config('aadhaar-offline.verify_signature', true)) {
            $this->components->warn('Signature verification is DISABLED (AADHAAR_OFFLINE_VERIFY_SIGNATURE=false). Do not run like this in production.');
        }

        try {
            $certificates = $aadhaarOffline->certificates()->describe();
        } catch (ConfigurationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $warnDays = $this->option('warn-days');
        $soon = new DateTimeImmutable('+' . (is_numeric($warnDays) ? (int) $warnDays : 30) . ' days');
        $problems = 0;

        foreach ($certificates as $certificate) {
            $validTo = $certificate['valid_to'];
            $expiresSoon = ! $certificate['expired'] && $validTo instanceof DateTimeImmutable && $validTo < $soon;
            $problems += (int) ($certificate['expired'] || $expiresSoon);

            $this->newLine();
            $this->line('  <options=bold>' . $certificate['source'] . '</>');
            $this->components->twoColumnDetail('  Subject', $certificate['subject']);
            $this->components->twoColumnDetail('  Valid to', ($validTo instanceof DateTimeImmutable ? $validTo->format('Y-m-d') : 'unknown') . ' ' . match (true) {
                $certificate['expired'] => '<fg=red>expired</>',
                $expiresSoon => '<fg=yellow>expires soon</>',
                default => '<fg=green>valid</>',
            });
            $this->line('  SHA-256 fingerprint');
            $this->line('  <fg=gray>' . $certificate['fingerprint'] . '</>');
        }

        $this->newLine();

        if ($problems > 0) {
            $this->components->warn('Some certificates have expired or expire soon. Add the current one from uidai.gov.in with aadhaar-offline:add-cert.');
            $this->components->info('Keep older certificates listed if you verify Secure QR codes: printed cards stay signed with the certificate that was current when they were printed.');
        }

        return self::SUCCESS;
    }
}
