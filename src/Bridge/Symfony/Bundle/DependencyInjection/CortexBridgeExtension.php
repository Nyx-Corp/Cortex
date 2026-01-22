<?php

namespace Cortex\Bridge\Symfony\Bundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CortexBridgeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // $container->setParameter('ddd.model_pattern', $config['model_pattern']);
        // $container->setParameter('ddd.middleware_pattern', $config['middleware_pattern']);

        // load services
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('services.yaml');
        if ($container->getParameter('kernel.environment') === 'dev') {
            $loader->load('services_dev.yaml');
        }
    }
}
