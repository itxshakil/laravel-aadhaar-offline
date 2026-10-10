<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use LogicException;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Exceptions\AadhaarOfflineException;
use Shakil\AadhaarOffline\Exceptions\ConfigurationException;
use Shakil\AadhaarOffline\Exceptions\InvalidShareCodeException;
use Shakil\AadhaarOffline\Exceptions\InvalidSignatureException;
use Shakil\AadhaarOffline\Exceptions\StaleKycException;
use Shakil\AadhaarOffline\Facades\AadhaarOffline;
use SplFileInfo;

/**
 * Validates an uploaded Offline e-KYC ZIP: it must open with the share code
 * from another field and (by default) pass UIDAI signature and freshness checks.
 *
 * Keep a reference to the rule to reuse the payload it read instead of reading the ZIP twice:
 *
 *     $request->validate(['aadhaar_zip' => ['required', 'file', $rule = new ValidOfflineKycZip()]]);
 *     $kyc = $rule->kyc();
 */
class ValidOfflineKycZip implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    protected array $data = [];

    protected ?OfflineKyc $kyc = null;

    /**
     * @param string $shareCodeField Request field holding the 4-digit share code
     * @param bool   $verify         Also verify the signature and freshness
     */
    public function __construct(
        protected string $shareCodeField = 'share_code',
        protected bool $verify = true,
    ) {}

    /**
     * The payload read during validation: verified, unless the rule was created with verify: false.
     *
     * @throws LogicException When validation has not passed yet
     */
    public function kyc(): OfflineKyc
    {
        if (! $this->kyc instanceof OfflineKyc) {
            throw new LogicException('No Offline e-KYC payload is available. Call kyc() only after validation has passed.');
        }

        return $this->kyc;
    }

    /**
     * Set the data under validation.
     *
     * @param array<array-key, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=):PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->kyc = null;

        if (! $value instanceof SplFileInfo) {
            $fail('The :attribute must be the Offline e-KYC ZIP file downloaded from UIDAI.');

            return;
        }

        $shareCode = data_get($this->data, $this->shareCodeField);

        if (! is_string($shareCode) && ! is_int($shareCode)) {
            $fail('A share code is required to open the :attribute.');

            return;
        }

        try {
            $kyc = AadhaarOffline::fromZip($value, (string) $shareCode);

            if ($this->verify) {
                $kyc->verify();
            }

            $this->kyc = $kyc;
        } catch (InvalidShareCodeException) {
            $fail('The share code does not open the :attribute.');
        } catch (InvalidSignatureException) {
            $fail('The :attribute is not a genuine UIDAI-signed Offline e-KYC file.');
        } catch (StaleKycException) {
            $fail('The :attribute is too old. Please download a fresh Offline e-KYC file.');
        } catch (ConfigurationException $exception) {
            throw $exception;
        } catch (AadhaarOfflineException) {
            $fail('The :attribute is not a valid Offline e-KYC file.');
        }
    }
}
