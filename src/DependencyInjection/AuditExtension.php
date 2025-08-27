<?php

namespace BenMacha\AuditBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AuditExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set configuration parameters
        $container->setParameter('audit.enabled', $config['enabled']);
        $container->setParameter('audit.storage.driver', $config['storage']['driver']);
        $container->setParameter('audit.logging.level', $config['logging']['level']);
        $container->setParameter('audit.entities.auto_discover', $config['entities']['auto_discover']);
        $container->setParameter('audit.entities.defaults', $config['entities']['defaults']);
        $container->setParameter('audit.web_interface.enabled', $config['web_interface']['enabled']);
        $container->setParameter('audit.web_interface.route_prefix', $config['web_interface']['route_prefix']);
        $container->setParameter('audit.api.enabled', $config['api']['enabled']);
        $container->setParameter('audit.api.route_prefix', $config['api']['route_prefix']);
        $container->setParameter('audit.security', $config['security']);
        $container->setParameter('audit.performance', $config['performance']);

        // Load services
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
        
        // Conditionally load web interface services
        if ($config['web_interface']['enabled']) {
            $loader->load('web_services.yaml');
        }
        
        // Conditionally load API services
        if ($config['api']['enabled']) {
            $loader->load('api_services.yaml');
        }
    }

    public function getAlias(): string
    {
        return 'audit';
    }
}