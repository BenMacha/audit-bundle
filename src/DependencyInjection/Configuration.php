<?php

namespace BenMacha\AuditBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('audit');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable/disable the audit system')
                ->end()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('driver')
                            ->defaultValue('doctrine')
                            ->info('Storage driver for audit logs')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('level')
                            ->defaultValue('info')
                            ->info('Logging level for audit events')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('entities')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('auto_discover')
                            ->defaultTrue()
                            ->info('Auto-discover entities with Auditable attribute')
                        ->end()
                        ->arrayNode('defaults')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('track_created_at')->defaultTrue()->end()
                                ->booleanNode('track_updated_at')->defaultTrue()->end()
                                ->booleanNode('track_deleted_at')->defaultTrue()->end()
                                ->booleanNode('store_old_values')->defaultTrue()->end()
                                ->booleanNode('store_new_values')->defaultTrue()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('web_interface')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('route_prefix')->defaultValue('/audit')->end()
                    ->end()
                ->end()
                ->arrayNode('api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('route_prefix')->defaultValue('/api/audit')->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('view_role')->defaultValue('ROLE_ADMIN')->end()
                        ->scalarNode('manage_role')->defaultValue('ROLE_SUPER_ADMIN')->end()
                        ->scalarNode('rollback_role')->defaultValue('ROLE_SUPER_ADMIN')->end()
                    ->end()
                ->end()
                ->arrayNode('performance')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('batch_size')->defaultValue(100)->end()
                        ->booleanNode('cache_enabled')->defaultTrue()->end()
                        ->integerNode('cache_ttl')->defaultValue(3600)->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}