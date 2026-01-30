<?php

namespace Cortex\Bridge\Doctrine;

use Cortex\Component\Middleware\Middleware;
use Cortex\Component\Model\Factory\CreationCommand;
use Cortex\Component\Model\Query\ModelQuery;
use Cortex\Component\Model\Store\RemoveCommand;
use Cortex\Component\Model\Store\SyncCommand;

trait DbalModelAdapterTrait
{
    private DbalAdapter $dbal;

    private function getDbal(): DbalAdapter
    {
        if (!isset($this->dbal)) {
            throw new \LogicException('DbalModelAdapterTrait requires a DbalAdapter instance, but none was set. Forget to call DbalModelMapper::createAdapter() ?');
        }

        return $this->dbal;
    }

    /**
     * Get the DBAL mapping configuration.
     *
     * Used by other mappers to configure JOINs.
     */
    public function getConfiguration(): DbalMappingConfiguration
    {
        return $this->getDbal()->getConfiguration();
    }

    public function onDbal(Middleware $chain, $command): \Generator
    {
        switch (true) {
            case $command instanceof ModelQuery:
                yield from $this->getDbal()->onModelQuery($chain, $command);
                break;
            case $command instanceof SyncCommand:
                yield from $this->getDbal()->onModelSync($chain, $command);
                break;
            case $command instanceof RemoveCommand:
            case $command instanceof CreationCommand:
                yield from ($chain->next)();
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported command type: %s', get_debug_type($command)));
        }
    }
}
