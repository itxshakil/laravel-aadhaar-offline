<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Shakil\AadhaarOffline\Exceptions\ConfigurationException;
use Shakil\AadhaarOffline\Security\CertificateStore;

/**
 * Adds a UIDAI certificate to the trusted list: shows its fingerprint, checks it against
 * the one you expect, stores it privately and appends it to AADHAAR_OFFLINE_CERTIFICATES.
 *
 * It never decides on its own which certificate to trust. Download it from uidai.gov.in
 * and compare the fingerprint.
 */
class AddCertificate extends Command
{
    public const string ENV_KEY = 'AADHAAR_OFFLINE_CERTIFICATES';

    /**
     * @var string
     */
    protected $signature = 'aadhaar-offline:add-cert
        {certificate : Path or https:// URL of the UIDAI certificate (.cer, PEM or DER)}
        {--fingerprint= : The SHA-256 fingerprint you expect; required when not running interactively}
        {--directory= : Where to store it (default: storage/app/aadhaar-offline/certs)}
        {--allow-expired : Accept an expired certificate, e.g. to verify Secure QR codes printed while it was current}';

    /**
     * @var string
     */
    protected $description = 'Verify a UIDAI certificate by fingerprint, store it privately and add it to AADHAAR_OFFLINE_CERTIFICATES';

    /**
     * Add a path to the AADHAAR_OFFLINE_CERTIFICATES line of a .env file, keeping existing
     * paths (for rotation) and every other line untouched.
     */
    public static function withCertificate(string $environment, string $path): string
    {
        $pattern = '/^' . self::ENV_KEY . '=(.*)$/m';

        if (preg_match($pattern, $environment, $matches) !== 1) {
            return rtrim($environment, "\n") . ($environment === '' ? '' : "\n") . self::ENV_KEY . '=' . self::quote($path) . "\n";
        }

        $paths = array_values(array_filter(
            array_map(trim(...), explode(',', trim($matches[1], " \t\"'"))),
            static fn (string $existing): bool => $existing !== '',
        ));

        if (in_array($path, $paths, true)) {
            return $environment;
        }

        $paths[] = $path;
        $value = self::quote(implode(',', $paths));

        return (string) preg_replace_callback($pattern, static fn (): string => self::ENV_KEY . '=' . $value, $environment, 1);
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = $this->argument('certificate');
        $source = is_string($source) ? $source : '';

        try {
            $pem = CertificateStore::toPem($this->contents($source), $source);
        } catch (ConfigurationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $certificate = CertificateStore::describePem($pem, $source);

        $this->components->twoColumnDetail('Subject', $certificate['subject']);
        $this->components->twoColumnDetail('Valid to', $certificate['valid_to']?->format('Y-m-d') ?? 'unknown');
        $this->line('  SHA-256 fingerprint');
        $this->line('  <fg=gray>' . $certificate['fingerprint'] . '</>');
        $this->newLine();

        if ($certificate['expired']) {
            if (! (bool) $this->option('allow-expired')) {
                $this->components->error('This certificate has expired. Download the current one from uidai.gov.in, or pass --allow-expired to verify Secure QR codes printed while it was current.');

                return self::FAILURE;
            }

            $this->components->warn('This certificate has expired. It is only useful for Secure QR codes printed while it was current.');
        }

        if (! $this->fingerprintConfirmed($certificate['fingerprint'])) {
            return self::FAILURE;
        }

        $path = $this->store($pem, $certificate['fingerprint']);
        $this->components->info("Stored at {$path}");

        $this->updateEnvironment($path);

        return self::SUCCESS;
    }

    private static function quote(string $value): string
    {
        return preg_match('/[\s#"]/', $value) === 1 ? '"' . addcslashes($value, '"\\') . '"' : $value;
    }

    /**
     * @throws ConfigurationException
     */
    private function contents(string $source): string
    {
        if (str_starts_with($source, 'http://')) {
            throw new ConfigurationException('Download the certificate over https://, not http://.');
        }

        if (str_starts_with($source, 'https://')) {
            try {
                $response = Http::timeout(15)->get($source);
            } catch (ConnectionException $exception) {
                throw new ConfigurationException("The certificate could not be downloaded from [{$source}].", $exception->getCode(), previous: $exception);
            }

            if (! $response->successful()) {
                throw new ConfigurationException("The certificate could not be downloaded from [{$source}] (HTTP {$response->status()}).");
            }

            return $response->body();
        }

        if (! is_file($source) || ! is_readable($source)) {
            throw new ConfigurationException("The certificate file [{$source}] does not exist or is not readable.");
        }

        return (string) file_get_contents($source);
    }

    private function fingerprintConfirmed(string $actual): bool
    {
        $expected = $this->option('fingerprint');

        if (is_string($expected) && $expected !== '') {
            if ($this->normalizeFingerprint($expected) !== $this->normalizeFingerprint($actual)) {
                $this->components->error('The fingerprint does not match the one you expected. Nothing was changed.');

                return false;
            }

            $this->components->info('The fingerprint matches.');

            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Pass --fingerprint with the SHA-256 fingerprint you expect when running non-interactively.');

            return false;
        }

        if (! $this->confirm('Does this fingerprint match the one UIDAI publishes for this certificate?')) {
            $this->components->warn('Nothing was changed.');

            return false;
        }

        return true;
    }

    private function store(string $pem, string $fingerprint): string
    {
        $directory = $this->option('directory');
        $directory = is_string($directory) && $directory !== '' ? $directory : storage_path('app/aadhaar-offline/certs');

        if (! is_dir($directory)) {
            mkdir($directory, 0o750, true);
        }

        $path = rtrim($directory, '/') . '/uidai-' . mb_strtolower(mb_substr($this->normalizeFingerprint($fingerprint), 0, 16)) . '.pem';
        file_put_contents($path, $pem);

        return $path;
    }

    private function updateEnvironment(string $path): void
    {
        // environmentFilePath() and configurationIsCached() live on the concrete application, not the contract.
        $application = $this->laravel instanceof Application ? $this->laravel : null;
        $environmentFile = $application?->environmentFilePath() ?? $this->laravel->basePath('.env');

        if (! is_file($environmentFile) || ! is_writable($environmentFile)) {
            $this->components->warn('No writable .env file found. Add this line yourself:');
            $this->line('  ' . self::ENV_KEY . '=' . $path);

            return;
        }

        $contents = (string) file_get_contents($environmentFile);
        $updated = self::withCertificate($contents, $path);

        if ($updated === $contents) {
            $this->components->info('The certificate is already listed in ' . self::ENV_KEY . '.');
        } else {
            file_put_contents($environmentFile, $updated);
            $this->components->info('Added to ' . self::ENV_KEY . ' in ' . basename($environmentFile) . '.');
        }

        if ($application?->configurationIsCached() === true) {
            $this->components->warn('Your configuration is cached. Run `php artisan config:cache` to pick up the change.');
        }
    }

    private function normalizeFingerprint(string $fingerprint): string
    {
        return mb_strtoupper((string) preg_replace('/[^0-9a-f]/i', '', $fingerprint));
    }
}
