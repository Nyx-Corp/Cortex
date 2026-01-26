<?php

namespace Cortex\Bridge\Symfony\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes services whose classes have private constructors.
 * This avoids manual exclusions in services.yaml for value objects, etc.
 */
class RemovePrivateConstructorServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if ($class === null) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
                $constructor = $reflection->getConstructor();

                if ($constructor !== null && $constructor->isPrivate()) {
                    $container->removeDefinition($id);
                }
            } catch (\ReflectionException) {
                // Class doesn't exist or can't be reflected, skip
                continue;
            }
        }
    }
}
