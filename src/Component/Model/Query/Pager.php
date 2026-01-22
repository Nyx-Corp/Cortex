<?php

namespace Cortex\Component\Model\Query;

class Pager
{
    public private(set) ?int $nbRecords = null {
        get => $this->nbRecords ?? throw new \LogicException('You have to bind() the pager before using it.');
    }

    public private(set) int $page {
        set {
            $this->page = max(1, (int) $value);
            $this->offset = ($this->page - 1) * $this->nbPerPage;
            $this->limit = $this->offset + $this->nbPerPage;
        }
    }
    public private(set) int $limit = 0;
    public private(set) int $offset = 0;

    private bool $isBound = false;

    public function __construct(
        int $page,
        public readonly int $nbPerPage = 25,
    ) {
        $this->page = $page;
    }

    public function bind(int $nbRecords): void
    {
        if ($this->isBound) {
            throw new \LogicException('Pager is already bound, clone it first.');
        }

        $this->nbRecords = $nbRecords;
        $this->isBound = true;

        // reset offset if page is out of bounds
        if ($this->offset > $nbRecords) {
            $this->page = 1;
        }
    }

    public function getPages(): array
    {
        if (!$this->isBound) {
            throw new \LogicException('You have to bind() the pager before using it.');
        }

        return range(1, max(1, (int) ceil($this->nbRecords / $this->nbPerPage)));
    }

    public function __clone(): void
    {
        $this->isBound = false;
        $this->nbRecords = null;
    }
}
