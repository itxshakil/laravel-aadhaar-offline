<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Testing;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Shakil\AadhaarOffline\Contracts\Verifier;
use Shakil\AadhaarOffline\Data\Address;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Data\Photo;
use Shakil\AadhaarOffline\Enums\Gender;
use Shakil\AadhaarOffline\Security\HashMatcher;

/**
 * Builds synthetic OfflineKyc objects for tests. Never contains real Aadhaar data.
 *
 * Supported attributes: last_four, generated_at, name, dob, gender, address (array or Address),
 * photo (bytes or null), mobile, email (plain values, hashed for you), share_code.
 */
final class OfflineKycFactory
{
    /** A 1x1 white JPEG. */
    public const string PHOTO_BASE64 = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes = [], ?Verifier $verifier = null): OfflineKyc
    {
        $shareCode = (string) ($attributes['share_code'] ?? '1234');
        $lastFour = (string) ($attributes['last_four'] ?? '1234');
        $lastDigit = (int) mb_substr($lastFour, -1);

        $generatedAt = $attributes['generated_at'] ?? new DateTimeImmutable('now', new DateTimeZone(OfflineKyc::TIMEZONE));
        $generatedAt = DateTimeImmutable::createFromInterface($generatedAt instanceof DateTimeInterface ? $generatedAt : new DateTimeImmutable())
            ->setTimezone(new DateTimeZone(OfflineKyc::TIMEZONE));

        $address = $attributes['address'] ?? [
            'house' => '12',
            'street' => 'Test Street',
            'loc' => 'Test Nagar',
            'vtc' => 'Test City',
            'dist' => 'Test District',
            'state' => 'Delhi',
            'country' => 'India',
            'pc' => '110001',
        ];

        $photo = array_key_exists('photo', $attributes) ? $attributes['photo'] : base64_decode(self::PHOTO_BASE64, true);

        $mobile = array_key_exists('mobile', $attributes) ? $attributes['mobile'] : '9876543210';
        $email = $attributes['email'] ?? null;

        $gender = $attributes['gender'] ?? Gender::Male;

        return new OfflineKyc(
            referenceId: $lastFour . $generatedAt->format('YmdHisv'),
            name: array_key_exists('name', $attributes) ? self::nullableString($attributes['name']) : 'Test Resident',
            dateOfBirth: array_key_exists('dob', $attributes) ? self::nullableString($attributes['dob']) : '01-01-1990',
            gender: $gender instanceof Gender ? $gender : Gender::tryFrom((string) $gender),
            address: $address instanceof Address ? $address : Address::fromUidaiAttributes(array_map(strval(...), (array) $address)),
            photo: is_string($photo) && $photo !== '' ? new Photo($photo) : null,
            mobileHash: is_string($mobile) ? HashMatcher::hash((string) HashMatcher::normalizeMobile($mobile), $shareCode, $lastDigit) : null,
            emailHash: is_string($email) ? HashMatcher::hash($email, $shareCode, $lastDigit) : null,
            shareCode: $shareCode,
            verifier: $verifier,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
