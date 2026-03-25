<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Symfony\Api;

use Cortex\Bridge\Symfony\Form\Attribute\Api;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Api::class)]
class ApiAttributeTest extends TestCase
{
    // =======================================================================
    // CONSTRUCTION TESTS
    // =======================================================================

    public function testDefaultValues(): void
    {
        $api = new Api();

        $this->assertSame(1, $api->since);
        $this->assertNull($api->deprecated);
        $this->assertNull($api->sunset);
    }

    public function testCustomSince(): void
    {
        $api = new Api(since: 3);

        $this->assertSame(3, $api->since);
    }

    public function testFullLifecycle(): void
    {
        $api = new Api(since: 2, deprecated: 4, sunset: '2026-09-01');

        $this->assertSame(2, $api->since);
        $this->assertSame(4, $api->deprecated);
        $this->assertSame('2026-09-01', $api->sunset);
    }

    // =======================================================================
    // isAvailableIn() TESTS
    // =======================================================================

    #[DataProvider('availabilityProvider')]
    public function testIsAvailableIn(int $since, int $version, bool $expected): void
    {
        $api = new Api(since: $since);

        $this->assertSame($expected, $api->isAvailableIn($version));
    }

    public static function availabilityProvider(): array
    {
        return [
            'available at introduction version' => [1, 1, true],
            'available after introduction' => [1, 3, true],
            'not available before introduction' => [2, 1, false],
            'available at exact since version' => [3, 3, true],
            'available well after since' => [2, 10, true],
        ];
    }

    // =======================================================================
    // isDeprecatedIn() TESTS
    // =======================================================================

    public function testNotDeprecatedWhenNoDeprecatedVersion(): void
    {
        $api = new Api(since: 1);

        $this->assertFalse($api->isDeprecatedIn(1));
        $this->assertFalse($api->isDeprecatedIn(100));
    }

    #[DataProvider('deprecationProvider')]
    public function testIsDeprecatedIn(int $deprecated, int $version, bool $expected): void
    {
        $api = new Api(since: 1, deprecated: $deprecated);

        $this->assertSame($expected, $api->isDeprecatedIn($version));
    }

    public static function deprecationProvider(): array
    {
        return [
            'deprecated at deprecation version' => [3, 3, true],
            'deprecated after deprecation version' => [3, 5, true],
            'not deprecated before deprecation' => [3, 2, false],
            'not deprecated one version before' => [3, 1, false],
        ];
    }

    // =======================================================================
    // ATTRIBUTE TARGET TEST
    // =======================================================================

    public function testIsPhpAttribute(): void
    {
        $ref = new \ReflectionClass(Api::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $instance->flags);
    }
}
