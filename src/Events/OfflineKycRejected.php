<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Events;

use Shakil\AadhaarOffline\Exceptions\AadhaarOfflineException;

/**
 * Fired when an Offline e-KYC payload fails verification.
 */
class OfflineKycRejected
{
    /**
     * Create a new event instance.
     *
     * @param string                                $referenceId UIDAI reference ID of the rejected payload
     * @param string                                $reason      Human-readable failure reason (contains no personal data)
     * @param class-string<AadhaarOfflineException> $exception   The exception class that caused the rejection
     */
    public function __construct(
        public string $referenceId,
        public string $reason,
        public string $exception,
    ) {}
}
