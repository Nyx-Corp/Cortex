<?php

namespace Cortex\Component\Model;

use Symfony\Component\Uid\Uuid;

trait Uuidentifiable
{
    public private(set) Uuid $uuid {
        get => $this->uuid ??= Uuid::v7();
        set(?Uuid $uuid) => $this->uuid = $uuid ?? Uuid::v7();
    }
}
