<?php

namespace Cortex\Bridge\Symfony\Bundle\DependencyInjection\Compiler;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Action\ActionHandlerCollection;
use Cortex\ValueObject\RegisteredClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ActionHandlerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ActionHandlerCollection::class)) {
            return;
        }

        $actionHandlerCollectionDefinition = $container->getDefinition(ActionHandlerCollection::class);
        $handlerCommandMapping = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (!is_subclass_of($class, ActionHandler::class)) {
                continue;
            }

            $commandClass = new RegisteredClass(
                preg_replace('/Handler$/', 'Command', $class)
            );

            $handlerCommandMapping[$commandClass->value] = new Reference($id);
        }

        $actionHandlerCollectionDefinition
            ->setArgument(0, $handlerCommandMapping)
        ;
    }
}
