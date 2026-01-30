<?php

namespace Cortex\Bridge\Doctrine;

use Doctrine\DBAL\Connection;

class DbalBridge
{
    public function __construct(
        protected Connection $dbalConnection,
        protected ?DbalPreloader $preloader = null,
    ) {
    }

    public function createAdapter(DbalMappingConfiguration $configuration): DbalAdapter
    {
        return new DbalAdapter(
            $this->dbalConnection,
            $configuration,
            $this->preloader,
        );
    }
}
