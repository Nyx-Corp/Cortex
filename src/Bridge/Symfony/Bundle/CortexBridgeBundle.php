<?php

namespace Cortex\Bridge\Symfony\Bundle;

use Cortex\Bridge\Symfony\Bundle\DependencyInjection\Compiler\ActionHandlerCompilerPass;
use Cortex\Bridge\Symfony\Bundle\DependencyInjection\Compiler\ModelProcessorCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class CortexBridgeBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ModelProcessorCompilerPass());
        $container->addCompilerPass(new ActionHandlerCompilerPass());
    }
}
