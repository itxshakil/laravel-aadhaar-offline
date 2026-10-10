<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Shakil\AadhaarOffline\Support\AadhaarNumber;

/**
 * Stores only the masked form ("XXXX XXXX 1234") of an Aadhaar number.
 *
 * Assigning a full 12-digit number masks it before it reaches the database,
 * so the raw number is never persisted by accident.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class MaskedAadhaar implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param array<string, mixed> $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException("The [{$key}] attribute must be an Aadhaar number, masked value or last four digits.");
        }

        $masked = AadhaarNumber::mask((string) $value);

        if ($masked === null) {
            throw new InvalidArgumentException("The [{$key}] attribute must be an Aadhaar number, masked value or last four digits.");
        }

        return $masked;
    }
}
