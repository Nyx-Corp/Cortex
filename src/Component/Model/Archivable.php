<?php

namespace Cortex\Component\Model;

trait Archivable
{
    public ?\DateTimeInterface $archivedAt = null {
        set(?\DateTimeInterface $value) => $value;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function archive(?\DateTimeInterface $archivedAt = null): void
    {
        $this->archivedAt = $archivedAt ?? new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->archivedAt = null;
    }
}
