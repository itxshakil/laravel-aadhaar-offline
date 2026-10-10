<?php

declare(strict_types=1);

namespace Shakil\AadhaarOffline\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Shakil\AadhaarOffline\AadhaarOffline;
use Shakil\AadhaarOffline\Data\OfflineKyc;
use Shakil\AadhaarOffline\Facades\AadhaarOffline as AadhaarOfflineFacade;
use Shakil\AadhaarOffline\Rules\ValidOfflineKycZip;
use Shakil\AadhaarOffline\Testing\AadhaarOfflineFake;
use Shakil\AadhaarOffline\Testing\OfflineKycFactory;
use Shakil\AadhaarOffline\Tests\TestCase;

/**
 * Laravel Boost renders resources/boost/guidelines/core.blade.php into AI agents' instructions.
 * A guideline that names a method that doesn't exist sends agents the wrong way.
 */
class BoostGuidelinesTest extends TestCase
{
    private const string PATH = __DIR__ . '/../../resources/boost/guidelines/core.blade.php';

    public function test_the_guidelines_render_as_blade_unchanged(): void
    {
        $source = (string) file_get_contents(self::PATH);

        $this->assertSame(trim($source), trim(Blade::render($source)));
    }

    public function test_every_method_the_guidelines_name_exists(): void
    {
        $classes = [AadhaarOffline::class, AadhaarOfflineFacade::class, AadhaarOfflineFake::class, OfflineKyc::class, ValidOfflineKycZip::class, OfflineKycFactory::class];
        preg_match_all('/(?:->|::|`)([a-z][A-Za-z]+)\(/', (string) file_get_contents(self::PATH), $matches);
        $methods = array_unique($matches[1]);

        $this->assertNotEmpty($methods);

        foreach ($methods as $method) {
            $found = array_filter($classes, static fn (string $class): bool => method_exists($class, $method));

            $this->assertNotEmpty($found, "The Boost guidelines mention {$method}(), which no public class has.");
        }
    }

    public function test_every_class_the_guidelines_name_exists(): void
    {
        preg_match_all('/`(Shakil\\\\AadhaarOffline\\\\[A-Za-z\\\\]+)`/', (string) file_get_contents(self::PATH), $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $class) {
            $this->assertTrue(class_exists($class) || interface_exists($class), "The Boost guidelines mention {$class}, which does not exist.");
        }
    }
}
