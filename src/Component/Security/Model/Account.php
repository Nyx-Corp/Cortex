<?php

namespace Cortex\Component\Security\Model;

use Cortex\Component\Model\Archivable;
use Cortex\Component\Model\Uuidentifiable;
use Cortex\ValueObject\Email;
use Cortex\ValueObject\HashedPassword;
use Symfony\Component\Uid\Uuid;

class Account implements \Stringable
{
    use Archivable;
    use Uuidentifiable;

    public private(set) array $acl;

    public function __construct(
        public readonly Email $username,
        public readonly ?HashedPassword $password = null,
        array $acl = [],
        ?Uuid $uuid = null,
    ) {
        $this->uuid = $uuid;
        $this->acl = array_map(fn ($role) => (string) $role, $acl);
    }

    public function __toString(): string
    {
        return (string) $this->username;
    }
}
