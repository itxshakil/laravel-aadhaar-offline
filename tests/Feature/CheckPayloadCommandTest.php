<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use DateTimeImmutable;
use Illuminate\Testing\PendingCommand;
use Shakil\AadhaarOffline\Testing\Fixtures;
use Shakil\AadhaarOffline\Tests\TestCase;

class CheckPayloadCommandTest extends TestCase
{
    public function test_it_passes_a_genuine_zip_without_printing_personal_data(): void
    {
        $this->check(Fixtures::zip(Fixtures::signedXml()))
            ->expectsQuestion('Share code', Fixtures::SHARE_CODE)
            ->expectsOutputToContain('XXXX XXXX 5678')
            ->expectsOutputToContain('valid, signed by a configured certificate')
            ->doesntExpectOutputToContain('Asha Verma')
            ->doesntExpectOutputToContain('110024')
            ->doesntExpectOutputToContain('15-08-1990')
            ->assertSuccessful();
    }

    public function test_it_reports_a_wrong_share_code(): void
    {
        $this->check(Fixtures::zip(Fixtures::signedXml()))
            ->expectsQuestion('Share code', '0000')
            ->expectsOutputToContain('InvalidShareCodeException')
            ->assertFailed();
    }

    public function test_it_shows_the_untrusted_signer_certificate_when_the_signature_fails(): void
    {
        $this->check(Fixtures::zip(Fixtures::signedXml(identity: 'attacker')))
            ->expectsQuestion('Share code', Fixtures::SHARE_CODE)
            ->expectsOutputToContain('InvalidSignatureException')
            ->expectsOutputToContain('NOT trusted')
            ->expectsOutputToContain('attacker')
            ->assertFailed();
    }

    public function test_it_reports_a_stale_file(): void
    {
        $xml = Fixtures::signedXml(['generated_at' => new DateTimeImmutable('-10 days')]);

        $this->check(Fixtures::file($xml, 'xml'))
            ->expectsOutputToContain('10 day(s) old, limit 3')
            ->assertFailed();
    }

    public function test_it_fails_when_signature_checks_are_disabled(): void
    {
        config()->set('aadhaar-offline.verify_signature', false);

        $this->check(Fixtures::file(Fixtures::signedXml(), 'xml'))
            ->expectsOutputToContain('skipped')
            ->assertFailed();
    }

    public function test_it_checks_a_secure_qr_code_from_a_text_file(): void
    {
        $this->check(Fixtures::file(Fixtures::secureQr() . "\n", 'txt'))
            ->expectsOutputToContain('Secure QR code read')
            ->expectsOutputToContain('valid, signed by a configured certificate')
            ->doesntExpectOutputToContain('Asha Verma')
            ->doesntExpectOutputToContain('3210')
            ->assertSuccessful();
    }

    public function test_it_reports_a_forged_secure_qr_code(): void
    {
        $this->check(Fixtures::file(Fixtures::secureQr(identity: 'attacker'), 'txt'))
            ->expectsOutputToContain('InvalidSignatureException')
            ->doesntExpectOutputToContain('NOT trusted')
            ->assertFailed();
    }

    public function test_it_reports_a_missing_file(): void
    {
        $this->check('/nonexistent/qr.txt')
            ->expectsOutputToContain('MalformedPayloadException')
            ->assertFailed();
    }

    private function check(string $file): PendingCommand
    {
        $command = $this->artisan('aadhaar-offline:check', ['file' => $file]);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }
}
