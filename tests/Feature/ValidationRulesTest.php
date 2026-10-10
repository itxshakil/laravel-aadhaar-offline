<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Shakil\AadhaarOffline\Rules\ValidAadhaarNumber;
use Shakil\AadhaarOffline\Rules\ValidOfflineKycZip;
use Shakil\AadhaarOffline\Support\Verhoeff;
use Shakil\AadhaarOffline\Testing\Fixtures;
use Shakil\AadhaarOffline\Tests\TestCase;

class ValidationRulesTest extends TestCase
{
    public function test_valid_aadhaar_number_rule(): void
    {
        $valid = '23456789012' . Verhoeff::generate('23456789012');

        $this->assertTrue(Validator::make(['uid' => $valid], ['uid' => [new ValidAadhaarNumber()]])->passes());
        $this->assertTrue(Validator::make(['uid' => implode(' ', mb_str_split($valid, 4))], ['uid' => [new ValidAadhaarNumber()]])->passes());
        $this->assertTrue(Validator::make(['uid' => '123456789012'], ['uid' => [new ValidAadhaarNumber()]])->fails());
        $this->assertTrue(Validator::make(['uid' => ['array']], ['uid' => [new ValidAadhaarNumber()]])->fails());

        $messages = Validator::make(['uid' => '999'], ['uid' => [new ValidAadhaarNumber()]])->errors()->first('uid');
        $this->assertSame('The uid must be a valid Aadhaar number.', $messages);
    }

    public function test_offline_kyc_zip_rule_passes_for_a_genuine_file(): void
    {
        $validator = $this->validate(Fixtures::zip(Fixtures::signedXml()), Fixtures::SHARE_CODE);

        $this->assertTrue($validator->passes(), (string) $validator->errors()->first());
    }

    public function test_offline_kyc_zip_rule_reports_a_wrong_share_code(): void
    {
        $validator = $this->validate(Fixtures::zip(Fixtures::signedXml()), '1111');

        $this->assertSame('The share code does not open the aadhaar zip.', $validator->errors()->first('aadhaar_zip'));
    }

    public function test_offline_kyc_zip_rule_reports_a_forged_file(): void
    {
        $validator = $this->validate(Fixtures::zip(Fixtures::signedXml(identity: 'attacker')), Fixtures::SHARE_CODE);

        $this->assertStringContainsString('not a genuine UIDAI-signed', $validator->errors()->first('aadhaar_zip'));
    }

    public function test_offline_kyc_zip_rule_can_skip_verification(): void
    {
        $validator = $this->validate(Fixtures::zip(Fixtures::xml()), Fixtures::SHARE_CODE, verify: false);

        $this->assertTrue($validator->passes());
    }

    public function test_offline_kyc_zip_rule_requires_a_file_and_share_code(): void
    {
        $noFile = Validator::make(['aadhaar_zip' => 'text', 'share_code' => '1234'], ['aadhaar_zip' => [new ValidOfflineKycZip()]]);
        $noCode = Validator::make(['aadhaar_zip' => $this->upload(Fixtures::zip(Fixtures::signedXml()))], ['aadhaar_zip' => [new ValidOfflineKycZip()]]);

        $this->assertTrue($noFile->fails());
        $this->assertSame('A share code is required to open the aadhaar zip.', $noCode->errors()->first('aadhaar_zip'));
    }

    public function test_offline_kyc_zip_rule_reports_a_malformed_archive(): void
    {
        $validator = $this->validate(Fixtures::file('nope', 'zip'), Fixtures::SHARE_CODE);

        $this->assertSame('The aadhaar zip is not a valid Offline e-KYC file.', $validator->errors()->first('aadhaar_zip'));
    }

    public function test_the_zip_rule_exposes_the_verified_payload_so_it_is_read_once(): void
    {
        $rule = new ValidOfflineKycZip('share_code');
        $validator = Validator::make(
            ['aadhaar_zip' => $this->upload(Fixtures::zip(Fixtures::signedXml())), 'share_code' => Fixtures::SHARE_CODE],
            ['aadhaar_zip' => [$rule]],
        );

        $this->assertTrue($validator->passes());
        $this->assertTrue($rule->kyc()->isVerified());
        $this->assertSame('Asha Verma', $rule->kyc()->name());
    }

    public function test_the_zip_rule_has_no_payload_after_a_failure(): void
    {
        $rule = new ValidOfflineKycZip('share_code');
        $validator = Validator::make(
            ['aadhaar_zip' => $this->upload(Fixtures::zip(Fixtures::signedXml(identity: 'attacker'))), 'share_code' => Fixtures::SHARE_CODE],
            ['aadhaar_zip' => [$rule]],
        );

        $this->assertFalse($validator->passes());
        $this->expectException(LogicException::class);

        $rule->kyc();
    }

    private function validate(string $zipPath, string $shareCode, bool $verify = true): \Illuminate\Validation\Validator
    {
        return Validator::make(
            ['aadhaar_zip' => $this->upload($zipPath), 'share_code' => $shareCode],
            ['aadhaar_zip' => ['required', new ValidOfflineKycZip('share_code', $verify)], 'share_code' => ['required', 'digits:4']],
        );
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'offline.zip', 'application/zip', null, true);
    }
}
