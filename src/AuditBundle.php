<?php

namespace BenMacha\AuditBundle;

use BenMacha\AuditBundle\DependencyInjection\AuditExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony Audit Bundle.
 *
 * Provides comprehensive entity change tracking with web interface,
 * API access, and rollback functionality.
 */
class AuditBundle extends Bundle
{
    /**
     * Returns the bundle's container extension.
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new AuditExtension();
    }

    /**
     * Gets the bundle's alias for configuration.
     */
    public function getAlias(): string
    {
        return 'audit';
    }

    /**
     * Boot the bundle - register event listeners and services.
     */
    public function boot(): void
    {
        parent::boot();

        // Backward compatibility for Symfony 5.4 Security class
        if (!class_exists('Symfony\Bundle\SecurityBundle\Security') && class_exists('Symfony\Component\Security\Core\Security')) {
            class_alias('Symfony\Component\Security\Core\Security', 'Symfony\Bundle\SecurityBundle\Security');
        }

        // Additional boot logic if needed
        // Event listeners are automatically registered via DI
    }
}
