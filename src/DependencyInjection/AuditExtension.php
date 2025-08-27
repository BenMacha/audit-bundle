<?php

namespace BenMacha\AuditBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Audit Bundle Extension.
 *
 * Handles configuration loading and service registration for the audit bundle.
 */
class AuditExtension extends Extension
{
    /**
     * Load bundle configuration and register services.
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store processed configuration in container parameters
        $container->setParameter('audit.config', $config);
        $container->setParameter('audit.enabled', $config['enabled']);
        $container->setParameter('audit.entities', $config['entities']);
        $container->setParameter('audit.retention_days', $config['retention_days']);
        $container->setParameter('audit.async_processing', $config['async_processing']);
        $container->setParameter('audit.database_connection', $config['database_connection']);
        $container->setParameter('audit.api.enabled', $config['api']['enabled']);
        $container->setParameter('audit.api.rate_limit', $config['api']['rate_limit']);
        $container->setParameter('audit.security.roles', $config['security']['roles']);

        // Load service definitions
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Conditionally load API services if API is enabled
        if ($config['api']['enabled']) {
            $loader->load('api_services.yaml');
        }
    }

    /**
     * Get the alias for this extension.
     */
    public function getAlias(): string
    {
        return 'audit';
    }
}
