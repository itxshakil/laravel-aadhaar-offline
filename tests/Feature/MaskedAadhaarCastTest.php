<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use InvalidArgumentException;
use Shakil\AadhaarOffline\Tests\Support\Resident;
use Shakil\AadhaarOffline\Tests\TestCase;

class MaskedAadhaarCastTest extends TestCase
{
    public function test_it_masks_a_full_number_before_storage(): void
    {
        $model = $this->model();
        $model->aadhaar = '2345 6789 9012';

        $this->assertSame('XXXX XXXX 9012', $model->getAttributes()['aadhaar']);
        $this->assertSame('XXXX XXXX 9012', $model->aadhaar);
    }

    public function test_it_accepts_last_four_and_null(): void
    {
        $model = $this->model();

        $model->aadhaar = '9012';
        $this->assertSame('XXXX XXXX 9012', $model->getAttributes()['aadhaar']);

        $model->aadhaar = null;
        $this->assertNull($model->getAttributes()['aadhaar']);
    }

    public function test_it_rejects_values_without_last_four_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model()->aadhaar = '12345';
    }

    private function model(): Resident
    {
        return new Resident();
    }
}
