<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Mapper;

use Cortex\Component\Mapper\CallbackMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Component\Mapper\CallbackMapper
 */
class CallbackMapperTest extends TestCase
{
    // =======================================================================
    // BASIC CALLBACK TESTS
    // =======================================================================

    public function testSimpleCallback(): void
    {
        $mapper = new CallbackMapper(fn ($data) => [
            'reversed' => strrev($data['value']),
        ]);

        $result = $mapper->map(['value' => 'hello']);

        $this->assertEquals(['reversed' => 'olleh'], $result);
    }

    public function testCallbackAccessesAllData(): void
    {
        $mapper = new CallbackMapper(fn ($data) => [
            'fullName' => $data['first'].' '.$data['last'],
            'initials' => $data['first'][0].$data['last'][0],
        ]);

        $result = $mapper->map(['first' => 'John', 'last' => 'Doe']);

        $this->assertEquals('John Doe', $result['fullName']);
        $this->assertEquals('JD', $result['initials']);
    }

    public function testCallbackReturnsPartialData(): void
    {
        $mapper = new CallbackMapper(fn ($data) => [
            'selected' => $data['important'],
        ]);

        $result = $mapper->map([
            'important' => 'keep',
            'ignored' => 'discard',
        ]);

        $this->assertEquals(['selected' => 'keep'], $result);
        $this->assertArrayNotHasKey('ignored', $result);
    }

    // =======================================================================
    // DEST PARAMETER TESTS
    // =======================================================================

    public function testCallbackReceivesDest(): void
    {
        $mapper = new CallbackMapper(function ($data, &$dest) {
            $dest['from_closure'] = 'added';

            return ['from_return' => 'also added'];
        });

        $existing = ['existing' => 'value'];
        $result = $mapper->map(['input' => 'data'], $existing);

        $this->assertEquals('value', $existing['existing']);
        $this->assertEquals('added', $existing['from_closure']);
        $this->assertEquals('also added', $result['from_return']);
    }

    public function testCallbackCanModifyDest(): void
    {
        $mapper = new CallbackMapper(function ($data, &$dest) {
            $dest['count'] = ($dest['count'] ?? 0) + 1;

            return $dest;
        });

        $dest = ['count' => 5];
        $result = $mapper->map([], $dest);

        $this->assertEquals(6, $result['count']);
    }

    // =======================================================================
    // CONTEXT TESTS
    // =======================================================================

    public function testCallbackWithContext(): void
    {
        $mapper = new CallbackMapper(function ($data, &$dest, $multiplier) {
            return ['result' => $data['value'] * $multiplier];
        });

        $dest = [];
        $result = $mapper->map(['value' => 5], $dest, 10);

        $this->assertEquals(50, $result['result']);
    }

    public function testCallbackWithMultipleContextArgs(): void
    {
        $mapper = new CallbackMapper(function ($data, &$dest, $prefix, $suffix) {
            return ['result' => $prefix.$data['value'].$suffix];
        });

        $dest = [];
        $result = $mapper->map(['value' => 'middle'], $dest, '<<', '>>');

        $this->assertEquals('<<middle>>', $result['result']);
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testCallbackReturnsEmpty(): void
    {
        $mapper = new CallbackMapper(fn ($data) => []);

        $result = $mapper->map(['anything' => 'ignored']);

        $this->assertEquals([], $result);
    }

    public function testCallbackWithComplexLogic(): void
    {
        $mapper = new CallbackMapper(function ($data) {
            $result = [];

            foreach ($data as $key => $value) {
                if (is_numeric($value)) {
                    $result[$key.'_doubled'] = $value * 2;
                } elseif (is_string($value)) {
                    $result[$key.'_upper'] = strtoupper($value);
                }
            }

            return $result;
        });

        $result = $mapper->map([
            'count' => 5,
            'name' => 'test',
            'active' => true,
        ]);

        $this->assertEquals(10, $result['count_doubled']);
        $this->assertEquals('TEST', $result['name_upper']);
        $this->assertArrayNotHasKey('active_doubled', $result);
        $this->assertArrayNotHasKey('active_upper', $result);
    }

    public function testCallbackPreservesTypes(): void
    {
        $mapper = new CallbackMapper(fn ($data) => [
            'int' => $data['int'],
            'float' => $data['float'],
            'bool' => $data['bool'],
            'array' => $data['array'],
            'null' => $data['null'],
        ]);

        $result = $mapper->map([
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'array' => [1, 2, 3],
            'null' => null,
        ]);

        $this->assertIsInt($result['int']);
        $this->assertIsFloat($result['float']);
        $this->assertIsBool($result['bool']);
        $this->assertIsArray($result['array']);
        $this->assertNull($result['null']);
    }

    public function testCallbackWithDateTimeObjects(): void
    {
        $date = new \DateTimeImmutable('2024-01-15');

        $mapper = new CallbackMapper(fn ($data) => [
            'formatted' => $data['date']->format('Y-m-d'),
        ]);

        $result = $mapper->map(['date' => $date]);

        $this->assertEquals('2024-01-15', $result['formatted']);
    }

    public function testCallbackWithNestedData(): void
    {
        $mapper = new CallbackMapper(fn ($data) => [
            'deep_value' => $data['level1']['level2']['level3'],
        ]);

        $result = $mapper->map([
            'level1' => [
                'level2' => [
                    'level3' => 'found',
                ],
            ],
        ]);

        $this->assertEquals('found', $result['deep_value']);
    }

    public function testCallbackCanReturnNestedArray(): void
    {
        $mapper = new CallbackMapper(fn ($data) => [
            'nested' => [
                'a' => $data['x'],
                'b' => [
                    'c' => $data['y'],
                ],
            ],
        ]);

        $result = $mapper->map(['x' => 1, 'y' => 2]);

        $this->assertEquals(1, $result['nested']['a']);
        $this->assertEquals(2, $result['nested']['b']['c']);
    }

    // =======================================================================
    // CLOSURE BINDING TESTS
    // =======================================================================

    public function testCallbackWithExternalDependency(): void
    {
        $multiplier = 10;

        $mapper = new CallbackMapper(fn ($data) => [
            'result' => $data['value'] * $multiplier,
        ]);

        $result = $mapper->map(['value' => 5]);

        $this->assertEquals(50, $result['result']);
    }

    public function testCallbackWithObjectMethod(): void
    {
        $helper = new class () {
            public function transform(string $value): string
            {
                return strtoupper($value);
            }
        };

        $mapper = new CallbackMapper(fn ($data) => [
            'transformed' => $helper->transform($data['value']),
        ]);

        $result = $mapper->map(['value' => 'hello']);

        $this->assertEquals('HELLO', $result['transformed']);
    }
}
