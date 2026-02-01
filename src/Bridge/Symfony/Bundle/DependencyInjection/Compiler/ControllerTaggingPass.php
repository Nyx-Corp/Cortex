<?php

namespace Cortex\Bridge\Symfony\Bundle\DependencyInjection\Compiler;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Bridge\Symfony\Routing\ControllerRouteLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tags all services implementing ControllerInterface with 'controller.service_arguments'.
 * Also injects controller classes into the route loader for auto-discovery.
 */
class ControllerTaggingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $controllers = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (null === $class) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, ControllerInterface::class)) {
                continue;
            }

            if (!$definition->hasTag('controller.service_arguments')) {
                $definition->addTag('controller.service_arguments');
            }

            $controllers[] = $class;
        }

        // Inject controllers into route loader
        if ($container->hasDefinition(ControllerRouteLoader::class)) {
            $container->getDefinition(ControllerRouteLoader::class)
                ->setArgument('$controllers', $controllers);
        }
    }
}
