<?php

namespace Cortex\Component\Security\Model;

use Cortex\Component\Model\Uuidentifiable;
use Symfony\Component\Uid\Uuid;

class Token
{
    use Uuidentifiable;

    public function __construct(
        public readonly Account $account,
        public readonly string $intention,
        public readonly string $tokenHash,
        public readonly \DateTimeInterface $expiresAt,
        public readonly ?string $label = null,
        public readonly ?array $scopes = null,
        public readonly ?\DateTimeInterface $createdAt = null,
        ?Uuid $uuid = null,
    ) {
        $this->uuid = $uuid;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function matchesScope(string $path): bool
    {
        if (null === $this->scopes) {
            return true;
        }

        foreach ($this->scopes as $scope) {
            if (fnmatch($scope, $path)) {
                return true;
            }
        }

        return false;
    }
}
