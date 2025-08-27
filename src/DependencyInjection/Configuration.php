<?php

namespace BenMacha\AuditBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Audit Bundle Configuration.
 *
 * Defines the configuration tree structure for the audit bundle.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generate the configuration tree builder.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('audit');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable or disable the audit bundle globally')
                ->end()
                ->integerNode('retention_days')
                    ->defaultValue(365)
                    ->min(1)
                    ->info('Number of days to retain audit logs')
                ->end()
                ->booleanNode('async_processing')
                    ->defaultTrue()
                    ->info('Enable asynchronous processing of audit logs')
                ->end()
                ->scalarNode('database_connection')
                    ->defaultValue('default')
                    ->info('Database connection to use for audit tables')
                ->end()
                ->arrayNode('entities')
                    ->info('Entity-specific audit configuration')
                    ->useAttributeAsKey('class')
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('enabled')
                                ->defaultTrue()
                                ->info('Enable auditing for this entity')
                            ->end()
                            ->arrayNode('ignored_columns')
                                ->info('Columns to ignore during auditing')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                            ->booleanNode('create_table')
                                ->defaultTrue()
                                ->info('Create dedicated audit table for this entity')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable API endpoints')
                        ->end()
                        ->integerNode('rate_limit')
                            ->defaultValue(100)
                            ->min(1)
                            ->info('API rate limit per hour')
                        ->end()
                        ->scalarNode('prefix')
                            ->defaultValue('/api/audit')
                            ->info('API route prefix')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('roles')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('admin')
                                    ->defaultValue('ROLE_AUDIT_ADMIN')
                                    ->info('Role required for admin access')
                                ->end()
                                ->scalarNode('auditor')
                                    ->defaultValue('ROLE_AUDIT_AUDITOR')
                                    ->info('Role required for auditor access')
                                ->end()
                                ->scalarNode('developer')
                                    ->defaultValue('ROLE_AUDIT_DEVELOPER')
                                    ->info('Role required for developer access')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('ui')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('route_prefix')
                            ->defaultValue('/audit')
                            ->info('Web interface route prefix')
                        ->end()
                        ->integerNode('items_per_page')
                            ->defaultValue(20)
                            ->min(1)
                            ->max(100)
                            ->info('Default items per page in listings')
                        ->end()
                        ->booleanNode('show_ip_address')
                            ->defaultTrue()
                            ->info('Show IP addresses in the interface')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
