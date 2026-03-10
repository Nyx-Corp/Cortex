<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Security\Service;

use Cortex\Component\Security\Service\TokenHasher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Component\Security\Service\TokenHasher
 */
class TokenHasherTest extends TestCase
{
    private TokenHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new TokenHasher();
    }

    // =======================================================================
    // generate() TESTS
    // =======================================================================

    public function testGenerateReturnsTokenAndHash(): void
    {
        $result = $this->hasher->generate();

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('tokenHash', $result);
    }

    public function testGenerateTokenStartsWithPrefix(): void
    {
        $result = $this->hasher->generate();

        $this->assertStringStartsWith('ct_', $result['token']);
    }

    public function testGenerateTokenWithCustomPrefix(): void
    {
        $hasher = new TokenHasher(prefix: 'lc_');
        $result = $hasher->generate();

        $this->assertStringStartsWith('lc_', $result['token']);
    }

    public function testGenerateTokenIsUrlSafe(): void
    {
        // Run multiple times to increase confidence
        for ($i = 0; $i < 50; ++$i) {
            $result = $this->hasher->generate();
            $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $result['token']);
        }
    }

    public function testGenerateTokenHasSufficientLength(): void
    {
        $result = $this->hasher->generate();

        // 32 bytes → ~43 base64 chars + 3 char prefix = ~46 chars minimum
        $this->assertGreaterThanOrEqual(40, strlen($result['token']));
    }

    public function testGenerateHashIsSha256(): void
    {
        $result = $this->hasher->generate();

        // SHA256 produces 64 hex characters
        $this->assertEquals(64, strlen($result['tokenHash']));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['tokenHash']);
    }

    public function testGenerateProducesUniqueTokens(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; ++$i) {
            $result = $this->hasher->generate();
            $tokens[] = $result['token'];
        }

        $this->assertCount(100, array_unique($tokens));
    }

    public function testGenerateHashMatchesToken(): void
    {
        $result = $this->hasher->generate();

        $this->assertEquals($this->hasher->hash($result['token']), $result['tokenHash']);
    }

    // =======================================================================
    // hash() TESTS
    // =======================================================================

    public function testHashIsDeterministic(): void
    {
        $token = 'ct_test-token-123';

        $this->assertEquals($this->hasher->hash($token), $this->hasher->hash($token));
    }

    public function testHashReturnsSha256(): void
    {
        $hash = $this->hasher->hash('ct_test');

        $this->assertEquals(64, strlen($hash));
        $this->assertEquals(hash('sha256', 'ct_test'), $hash);
    }

    public function testHashDifferentInputsProduceDifferentHashes(): void
    {
        $hash1 = $this->hasher->hash('ct_token-a');
        $hash2 = $this->hasher->hash('ct_token-b');

        $this->assertNotEquals($hash1, $hash2);
    }

    // =======================================================================
    // verify() TESTS
    // =======================================================================

    public function testVerifyWithCorrectToken(): void
    {
        $result = $this->hasher->generate();

        $this->assertTrue($this->hasher->verify($result['token'], $result['tokenHash']));
    }

    public function testVerifyWithWrongToken(): void
    {
        $result = $this->hasher->generate();

        $this->assertFalse($this->hasher->verify('ct_wrong-token', $result['tokenHash']));
    }

    public function testVerifyWithWrongHash(): void
    {
        $result = $this->hasher->generate();

        $this->assertFalse($this->hasher->verify($result['token'], hash('sha256', 'wrong')));
    }

    public function testVerifyIsTimingSafe(): void
    {
        // This tests that hash_equals is used (timing-safe comparison).
        // We can't directly test timing, but we verify correctness
        // with near-match hashes.
        $result = $this->hasher->generate();
        $almostRight = substr($result['tokenHash'], 0, -1).'0';

        if ($almostRight === $result['tokenHash']) {
            $almostRight = substr($result['tokenHash'], 0, -1).'1';
        }

        $this->assertFalse($this->hasher->verify($result['token'], $almostRight));
    }

    // =======================================================================
    // ROUNDTRIP TESTS
    // =======================================================================

    public function testFullRoundtrip(): void
    {
        // Generate → store hash → verify with raw
        $generated = $this->hasher->generate();
        $storedHash = $generated['tokenHash'];
        $rawToken = $generated['token'];

        // Simulate: user presents raw token later
        $this->assertTrue($this->hasher->verify($rawToken, $storedHash));
    }

    public function testDifferentHasherInstancesAreCompatible(): void
    {
        $hasher1 = new TokenHasher();
        $hasher2 = new TokenHasher();

        $generated = $hasher1->generate();

        $this->assertTrue($hasher2->verify($generated['token'], $generated['tokenHash']));
    }
}
