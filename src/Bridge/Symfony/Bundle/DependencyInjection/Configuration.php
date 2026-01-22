<?php

namespace Cortex\Bridge\Symfony\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('cortex');

        // $treeBuilder->getRootNode()
        //     ->children()
        //     ->scalarNode('model_pattern')->defaultValue('src/Domain/**/Model/*.php')->end()
        //     ->scalarNode('factory_pattern')->defaultValue('src/**/{Model}Factory.php')->end()
        //     ->scalarNode('middleware_pattern')->defaultValue('src/**/{Model}*Middleware.php')->end()
        //     ->end();

        return $treeBuilder;
    }
}
