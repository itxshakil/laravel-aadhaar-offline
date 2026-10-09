<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Shakil\AadhaarOffline\Tests\TestCase;

class UploadFormComponentTest extends TestCase
{
    public function test_it_renders_a_multipart_form_with_a_safe_share_code_field(): void
    {
        $html = Blade::render('<x-aadhaar-offline::upload-form action="/kyc" class="card" />');

        $this->assertStringContainsString('action="/kyc"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('class="aadhaar-offline-form card"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="aadhaar_zip"', $html);
        $this->assertStringContainsString('accept=".zip,application/zip"', $html);
        $this->assertMatchesRegularExpression('/<input\s+type="password"\s+id="share_code"\s+name="share_code"\s+inputmode="numeric"\s+pattern="\[0-9\]\{4\}"\s+minlength="4"\s+maxlength="4"\s+autocomplete="off"/', $html);
        $this->assertStringNotContainsString('aria-invalid', $html);
        $this->assertStringContainsString('>Verify Aadhaar</button>', $html);
    }

    public function test_it_uses_custom_field_names_and_shows_their_errors(): void
    {
        // Blade::render() and View::share() exist on every supported Testbench, unlike $this->blade().
        View::share('errors', (new ViewErrorBag())->put('default', new MessageBag(['kyc_file' => ['The kyc file is not a genuine UIDAI-signed Offline e-KYC file.']])));

        $html = Blade::render(
            '<x-aadhaar-offline::upload-form action="/kyc" file-field="kyc_file" share-code-field="pin" submit-label="Continue">'
            . '<input type="hidden" name="extra" value="1">'
            . '</x-aadhaar-offline::upload-form>',
        );

        $this->assertStringContainsString('name="kyc_file"', $html);
        $this->assertStringContainsString('name="pin"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="kyc_file-help kyc_file-error"', $html);
        $this->assertStringContainsString('id="kyc_file-error"', $html);
        $this->assertStringContainsString('aria-describedby="pin-help"', $html);
        $this->assertStringContainsString('not a genuine UIDAI-signed', $html);
        $this->assertStringContainsString('name="extra"', $html);
        $this->assertStringContainsString('>Continue</button>', $html);
    }
}
