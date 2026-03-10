<?php

namespace Cortex\Component\Security\Service;

class TokenHasher
{
    private const TOKEN_BYTES = 32;
    private const HASH_ALGO = 'sha256';

    public function __construct(
        private readonly string $prefix = 'ct_',
    ) {
    }

    /**
     * Generates a token pair. The raw token is returned ONCE
     * and must be shown to the user immediately. Only the hash
     * should be persisted.
     *
     * @return array{token: string, tokenHash: string}
     */
    public function generate(): array
    {
        $raw = $this->prefix.rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');

        return [
            'token' => $raw,
            'tokenHash' => $this->hash($raw),
        ];
    }

    public function hash(string $rawToken): string
    {
        return hash(self::HASH_ALGO, $rawToken);
    }

    public function verify(string $rawToken, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hash($rawToken));
    }
}
