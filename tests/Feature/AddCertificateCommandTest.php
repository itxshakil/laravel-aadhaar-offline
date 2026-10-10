<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Shakil\AadhaarOffline\Console\Commands\AddCertificate;
use Shakil\AadhaarOffline\Security\CertificateStore;
use Shakil\AadhaarOffline\Tests\Support\Fixtures;
use Shakil\AadhaarOffline\Tests\TestCase;

class AddCertificateCommandTest extends TestCase
{
    private string $directory;

    private string $environmentFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = Fixtures::directory() . '/certs-' . bin2hex(random_bytes(4));
        $this->environmentFile = Fixtures::file("APP_NAME=Test\nAADHAAR_OFFLINE_CERTIFICATES=/old/uidai.cer\nAPP_DEBUG=true\n", 'env');
        $this->app?->loadEnvironmentFrom(basename($this->environmentFile));
        $this->app?->useEnvironmentPath(dirname($this->environmentFile));
    }

    public function test_it_stores_a_certificate_whose_fingerprint_matches_and_appends_it_to_the_env_file(): void
    {
        $this->addCert(Fixtures::certificateFile(der: true), $this->fingerprint())
            ->expectsOutputToContain('The fingerprint matches.')
            ->assertSuccessful();

        $stored = glob($this->directory . '/uidai-*.pem') ?: [];
        $this->assertCount(1, $stored);
        $this->assertSame($this->fingerprint(), CertificateStore::describePem((string) file_get_contents($stored[0]))['fingerprint']);
        $this->assertSame(
            "APP_NAME=Test\nAADHAAR_OFFLINE_CERTIFICATES=/old/uidai.cer," . $stored[0] . "\nAPP_DEBUG=true\n",
            file_get_contents($this->environmentFile),
        );
    }

    public function test_it_accepts_a_fingerprint_without_colons_in_any_case(): void
    {
        $this->addCert(Fixtures::certificateFile(), mb_strtolower(str_replace(':', '', $this->fingerprint())))
            ->assertSuccessful();
    }

    public function test_it_changes_nothing_when_the_fingerprint_differs(): void
    {
        $before = file_get_contents($this->environmentFile);

        $this->addCert(Fixtures::certificateFile('attacker'), $this->fingerprint())
            ->expectsOutputToContain('does not match')
            ->assertFailed();

        $this->assertSame($before, file_get_contents($this->environmentFile));
        $this->assertDirectoryDoesNotExist($this->directory);
    }

    public function test_it_asks_for_confirmation_without_a_fingerprint(): void
    {
        $this->addCert(Fixtures::certificateFile())
            ->expectsConfirmation('Does this fingerprint match the one UIDAI publishes for this certificate?', 'no')
            ->expectsOutputToContain('Nothing was changed.')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->directory);
    }

    public function test_it_downloads_over_https(): void
    {
        $pem = Fixtures::identity()['cert'];
        Http::fake(['https://uidai.example/cert.cer' => Http::response($pem)]);

        $this->addCert('https://uidai.example/cert.cer', $this->fingerprint())->assertSuccessful();
    }

    public function test_it_refuses_plain_http(): void
    {
        Http::fake();

        $this->addCert('http://uidai.example/cert.cer', $this->fingerprint())
            ->expectsOutputToContain('https://')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_rejects_an_expired_certificate(): void
    {
        // PHP cannot back-date a certificate; with 0 days notAfter is the signing second, so wait past it.
        $path = Fixtures::certificateFile('expired', validDays: 0);
        usleep(1_100_000);

        $this->addCert($path)
            ->expectsOutputToContain('expired')
            ->assertFailed();
    }

    public function test_an_expired_certificate_can_be_added_on_purpose_for_old_qr_codes(): void
    {
        $path = Fixtures::certificateFile('expired-allowed', validDays: 0);
        usleep(1_100_000);

        $this->addCert($path, $this->fingerprint('expired-allowed', validDays: 0), ['--allow-expired' => true])
            ->expectsOutputToContain('only useful for Secure QR codes')
            ->assertSuccessful();
    }

    public function test_it_rejects_a_file_that_is_not_a_certificate(): void
    {
        $this->addCert(Fixtures::file('not a certificate', 'cer'), $this->fingerprint())
            ->expectsOutputToContain('could not be read')
            ->assertFailed();
    }

    public function test_the_env_line_is_added_when_missing_and_left_alone_when_already_listed(): void
    {
        $this->assertSame("A=1\nAADHAAR_OFFLINE_CERTIFICATES=/c.pem\n", AddCertificate::withCertificate("A=1\n", '/c.pem'));
        $this->assertSame("AADHAAR_OFFLINE_CERTIFICATES=/c.pem\n", AddCertificate::withCertificate('', '/c.pem'));
        $this->assertSame("AADHAAR_OFFLINE_CERTIFICATES=/a.pem,/c.pem\n", AddCertificate::withCertificate("AADHAAR_OFFLINE_CERTIFICATES=/a.pem,/c.pem\n", '/c.pem'));
        $this->assertSame("AADHAAR_OFFLINE_CERTIFICATES=/c.pem\n", AddCertificate::withCertificate("AADHAAR_OFFLINE_CERTIFICATES=\n", '/c.pem'));
        $this->assertSame("AADHAAR_OFFLINE_CERTIFICATES=\"/a.pem,/My Certs/c.pem\"\n", AddCertificate::withCertificate("AADHAAR_OFFLINE_CERTIFICATES=\"/a.pem\"\n", '/My Certs/c.pem'));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function addCert(string $certificate, ?string $fingerprint = null, array $options = []): PendingCommand
    {
        $command = $this->artisan('aadhaar-offline:add-cert', array_filter([
            'certificate' => $certificate,
            '--fingerprint' => $fingerprint,
            '--directory' => $this->directory,
            ...$options,
        ]));
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    private function fingerprint(string $identity = 'uidai-test', int $validDays = 365): string
    {
        return CertificateStore::describePem(Fixtures::identity($identity, $validDays)['cert'])['fingerprint'];
    }
}
