<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Override;
use Shakil\AadhaarOffline\Casts\MaskedAadhaar;

/**
 * @property string|null $aadhaar
 */
class Resident extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['aadhaar' => MaskedAadhaar::class];
    }
}
