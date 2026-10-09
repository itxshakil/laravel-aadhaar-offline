<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Shakil\AadhaarOffline\Support\AadhaarNumber;

/**
 * Validates a 12-digit Aadhaar number: format, no leading 0/1, and Verhoeff check digit.
 *
 * Spaces and hyphens are allowed ("2345 6789 0123").
 */
class ValidAadhaarNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=):PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('The :attribute must be a valid Aadhaar number.');

            return;
        }

        if (! AadhaarNumber::isValid((string) $value)) {
            $fail('The :attribute must be a valid Aadhaar number.');
        }
    }
}
