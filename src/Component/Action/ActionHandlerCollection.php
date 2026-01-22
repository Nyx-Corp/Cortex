<?php

namespace Cortex\Component\Action;

use Cortex\ValueObject\RegisteredClass;

class ActionHandlerCollection
{
    private array $commandMapping = [];

    public function __construct(
        iterable $actionHandlers,
    ) {
        foreach ($actionHandlers as $commandClass => $actionHandler) {
            $this->commandMapping[new RegisteredClass($commandClass)->value] = $actionHandler;
        }
    }

    public function getRegisteredCommands(): array
    {
        return array_keys($this->commandMapping);
    }

    public function handleCommand(object $command): object
    {
        $commandClass = get_class($command);
        if (!is_callable($this->commandMapping[$commandClass])) {
            throw new \InvalidArgumentException(sprintf('No registered handler for given command, "%s" given.', $commandClass));
        }

        return $this->commandMapping[$commandClass]($command);
    }
}
