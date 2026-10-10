<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Override;
use Shakil\AadhaarOffline\Console\Commands\AddCertificate;
use Shakil\AadhaarOffline\Console\Commands\CheckPayload;
use Shakil\AadhaarOffline\Console\Commands\ListCertificates;
use Shakil\AadhaarOffline\Contracts\Verifier;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Events\OfflineKycRejected;
use Shakil\AadhaarOffline\Events\OfflineKycVerified;
use Shakil\AadhaarOffline\Exceptions\AadhaarOfflineException;
use Shakil\AadhaarOffline\Exceptions\ConfigurationException;
use Shakil\AadhaarOffline\Parsers\SecureQrParser;
use Shakil\AadhaarOffline\Parsers\XmlParser;
use Shakil\AadhaarOffline\Parsers\ZipReader;
use Shakil\AadhaarOffline\Security\CertificateStore;
use Shakil\AadhaarOffline\Security\KycVerifier;
use Shakil\AadhaarOffline\Security\QrSignatureVerifier;
use Shakil\AadhaarOffline\Security\SignatureVerifier;

/**
 * Aadhaar Offline Service Provider for Laravel.
 */
class AadhaarOfflineServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/aadhaar-offline.php',
            'aadhaar-offline',
        );

        $this->app->singleton(CertificateStore::class, fn (Application $app): CertificateStore => CertificateStore::fromPaths(
            array_values(array_map(strval(...), (array) $this->config('certificates', []))),
        ));

        $this->app->singleton(SignatureVerifier::class, fn (Application $app): SignatureVerifier => new SignatureVerifier(
            $app->make(CertificateStore::class),
            array_values(array_map(strval(...), (array) $this->config('algorithms', SignatureVerifier::DEFAULT_ALGORITHMS))),
            $this->limit('max_xml_bytes'),
        ));

        $this->app->singleton(QrSignatureVerifier::class, fn (Application $app): QrSignatureVerifier => new QrSignatureVerifier(
            $app->make(CertificateStore::class),
        ));

        $this->app->singleton(Verifier::class, fn (Application $app): KycVerifier => new KycVerifier(
            signatures: $app->make(SignatureVerifier::class),
            verifySignature: (bool) $this->config('verify_signature', true),
            maxAgeDays: $this->maxAgeDays(),
            onVerified: $this->eventsEnabled() ? function (OfflineKyc $kyc) use ($app): void {
                $app->make(Dispatcher::class)->dispatch(new OfflineKycVerified(
                    $kyc->referenceId(),
                    $kyc->maskedAadhaar(),
                    $kyc->generatedAt(),
                ));
            } : null,
            onRejected: $this->eventsEnabled() ? function (OfflineKyc $kyc, AadhaarOfflineException $exception) use ($app): void {
                $app->make(Dispatcher::class)->dispatch(new OfflineKycRejected(
                    $kyc->referenceId(),
                    $exception->getMessage(),
                    $exception::class,
                ));
            } : null,
            qrSignatures: $app->make(QrSignatureVerifier::class),
            qrMaxAgeDays: KycVerifier::normalizeMaxAgeDays($this->config('secure_qr.max_age_days')),
        ));

        $this->app->singleton(AadhaarOffline::class, fn (Application $app): AadhaarOffline => new AadhaarOffline(
            zipReader: new ZipReader($this->limit('max_zip_bytes'), $this->limit('max_xml_bytes')),
            parser: new XmlParser($this->limit('max_xml_bytes')),
            verifier: $app->make(Verifier::class),
            certificates: $app->make(CertificateStore::class),
            filesystem: $app->make(FilesystemFactory::class),
            photoDisk: is_string($disk = $this->config('photo.disk')) ? $disk : null,
            photoDirectory: (string) $this->config('photo.directory', 'aadhaar-offline/photos'),
            qrParser: new SecureQrParser(),
        ));

        $this->app->alias(AadhaarOffline::class, 'aadhaar-offline');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'aadhaar-offline');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AddCertificate::class,
                CheckPayload::class,
                ListCertificates::class,
            ]);

            // AboutCommand ships with laravel/framework, not the illuminate/* components this package requires.
            if (class_exists(AboutCommand::class)) {
                AboutCommand::add('Aadhaar Offline', fn (): array => $this->aboutDetails());
            }

            $this->publishes([
                __DIR__ . '/../config/aadhaar-offline.php' => config_path('aadhaar-offline.php'),
            ], 'aadhaar-offline-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/aadhaar-offline'),
            ], 'aadhaar-offline-views');
        }
    }

    /**
     * @return array<string, string>
     */
    private function aboutDetails(): array
    {
        $details = [
            'Signature verification' => (bool) $this->config('verify_signature', true)
                ? '<fg=green;options=bold>ENABLED</>'
                : '<fg=red;options=bold>DISABLED</>',
        ];

        try {
            $certificates = $this->app->make(CertificateStore::class)->describe();
            $current = array_filter($certificates, static fn (array $certificate): bool => ! $certificate['expired']);
            $expiries = array_filter(array_column($current, 'valid_to'));
            sort($expiries);

            $details['Certificates'] = count($current) . ' valid, ' . (count($certificates) - count($current)) . ' expired';
            $details['Next expiry'] = $expiries === [] ? '<fg=yellow;options=bold>NONE VALID</>' : $expiries[0]->format('Y-m-d');
        } catch (ConfigurationException) {
            $details['Certificates'] = '<fg=yellow;options=bold>NOT CONFIGURED</>';
        }

        $xmlAge = KycVerifier::normalizeMaxAgeDays($this->config('max_age_days', 3));
        $qrAge = KycVerifier::normalizeMaxAgeDays($this->config('secure_qr.max_age_days'));
        $details['Max age (XML / QR)'] = ($xmlAge === null ? 'none' : $xmlAge . ' days') . ' / ' . ($qrAge === null ? 'none' : $qrAge . ' days');

        return $details;
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->app->make('config')->get('aadhaar-offline.' . $key, $default);
    }

    private function limit(string $key): int
    {
        $value = $this->config('limits.' . $key, 2 * 1024 * 1024);

        return is_numeric($value) ? (int) $value : 2 * 1024 * 1024;
    }

    private function maxAgeDays(): ?int
    {
        return KycVerifier::normalizeMaxAgeDays($this->config('max_age_days', 3));
    }

    private function eventsEnabled(): bool
    {
        return (bool) $this->config('events.enabled', true);
    }
}
