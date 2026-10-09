<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Events;

use DateTimeImmutable;

/**
 * Fired when an Offline e-KYC payload passes signature and freshness checks.
 *
 * Carries only non-sensitive identifiers. Keep the OfflineKyc object yourself
 * if a listener needs the resident's details.
 */
class OfflineKycVerified
{
    /**
     * Create a new event instance.
     *
     * @param string            $referenceId   UIDAI reference ID (last 4 digits + timestamp)
     * @param string            $maskedAadhaar e.g. "XXXX XXXX 1234"
     * @param DateTimeImmutable $generatedAt   When UIDAI generated the payload
     */
    public function __construct(
        public string $referenceId,
        public string $maskedAadhaar,
        public DateTimeImmutable $generatedAt,
    ) {}
}
