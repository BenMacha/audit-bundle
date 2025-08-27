<?php

namespace BenMacha\AuditBundle\Service;

use BenMacha\AuditBundle\Entity\AuditConfig;
use BenMacha\AuditBundle\Entity\EntityConfig;
use BenMacha\AuditBundle\Repository\AuditConfigRepository;
use BenMacha\AuditBundle\Repository\EntityConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ConfigurationService
{
    private ParameterBagInterface $parameterBag;
    private AuditConfigRepository $auditConfigRepository;
    private EntityConfigRepository $entityConfigRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private ?AuditConfig $cachedConfig = null;
    private array $cachedEntityConfigs = [];

    public function __construct(
        ParameterBagInterface $parameterBag,
        AuditConfigRepository $auditConfigRepository,
        EntityConfigRepository $entityConfigRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->parameterBag = $parameterBag;
        $this->auditConfigRepository = $auditConfigRepository;
        $this->entityConfigRepository = $entityConfigRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Check if audit is globally enabled.
     */
    public function isAuditEnabled(): bool
    {
        // Check bundle configuration first
        if ($this->parameterBag->has('audit_bundle.enabled')) {
            $bundleEnabled = $this->parameterBag->get('audit_bundle.enabled');
            if (!$bundleEnabled) {
                return false;
            }
        }

        // Check database configuration
        $config = $this->getAuditConfig();

        return $config ? $config->isEnabled() : true;
    }

    /**
     * Get audit configuration.
     */
    public function getAuditConfig(): ?AuditConfig
    {
        if (null === $this->cachedConfig) {
            $this->cachedConfig = $this->auditConfigRepository->findDefault();
        }

        return $this->cachedConfig;
    }

    /**
     * Get or create audit configuration.
     */
    public function getOrCreateAuditConfig(): AuditConfig
    {
        $config = $this->getAuditConfig();
        if (!$config) {
            $config = $this->auditConfigRepository->getOrCreateDefault();
            $this->cachedConfig = $config;
        }

        return $config;
    }

    /**
     * Update audit configuration.
     */
    public function updateAuditConfig(array $settings): AuditConfig
    {
        $config = $this->getOrCreateAuditConfig();

        if (isset($settings['enabled'])) {
            $config->setEnabled((bool) $settings['enabled']);
        }

        if (isset($settings['retention_days'])) {
            $config->setRetentionDays((int) $settings['retention_days']);
        }

        if (isset($settings['async_processing'])) {
            $config->setAsyncProcessing((bool) $settings['async_processing']);
        }

        if (isset($settings['settings']) && is_array($settings['settings'])) {
            $currentSettings = $config->getSettings() ?? [];
            $config->setSettings(array_merge($currentSettings, $settings['settings']));
        }

        $this->auditConfigRepository->save($config, true);
        $this->cachedConfig = $config;

        $this->logger->info('Audit configuration updated', [
            'config_id' => $config->getId(),
            'settings' => $settings,
        ]);

        return $config;
    }

    /**
     * Get entity configuration.
     */
    public function getEntityConfig(string $entityClass): ?EntityConfig
    {
        if (!isset($this->cachedEntityConfigs[$entityClass])) {
            $this->cachedEntityConfigs[$entityClass] = $this->entityConfigRepository->findByEntityClass($entityClass);
        }

        return $this->cachedEntityConfigs[$entityClass];
    }

    /**
     * Get or create entity configuration.
     */
    public function getOrCreateEntityConfig(string $entityClass): EntityConfig
    {
        $config = $this->getEntityConfig($entityClass);
        if (!$config) {
            $config = $this->entityConfigRepository->getOrCreateByEntityClass($entityClass);
            $this->cachedEntityConfigs[$entityClass] = $config;
        }

        return $config;
    }

    /**
     * Update entity configuration.
     */
    public function updateEntityConfig(string $entityClass, array $settings): EntityConfig
    {
        $config = $this->getOrCreateEntityConfig($entityClass);

        if (isset($settings['enabled'])) {
            $config->setEnabled((bool) $settings['enabled']);
        }

        if (isset($settings['ignored_columns']) && is_array($settings['ignored_columns'])) {
            $config->setIgnoredColumns($settings['ignored_columns']);
        }

        if (isset($settings['create_table'])) {
            $config->setCreateTable((bool) $settings['create_table']);
        }

        if (isset($settings['table_name'])) {
            $config->setTableName($settings['table_name']);
        }

        if (isset($settings['settings']) && is_array($settings['settings'])) {
            $currentSettings = $config->getSettings() ?? [];
            $config->setSettings(array_merge($currentSettings, $settings['settings']));
        }

        $this->entityConfigRepository->save($config, true);
        $this->cachedEntityConfigs[$entityClass] = $config;

        $this->logger->info('Entity configuration updated', [
            'entity_class' => $entityClass,
            'config_id' => $config->getId(),
            'settings' => $settings,
        ]);

        return $config;
    }

    /**
     * Get retention days.
     */
    public function getRetentionDays(): int
    {
        // Check bundle configuration first
        if ($this->parameterBag->has('audit_bundle.retention_days')) {
            return (int) $this->parameterBag->get('audit_bundle.retention_days');
        }

        // Check database configuration
        $config = $this->getAuditConfig();

        return $config ? $config->getRetentionDays() : 365; // Default to 1 year
    }

    /**
     * Check if async processing is enabled.
     */
    public function isAsyncProcessingEnabled(): bool
    {
        // Check bundle configuration first
        if ($this->parameterBag->has('audit_bundle.async_processing')) {
            return (bool) $this->parameterBag->get('audit_bundle.async_processing');
        }

        // Check database configuration
        $config = $this->getAuditConfig();

        return $config ? $config->isAsyncProcessing() : false;
    }

    /**
     * Get database connection name.
     */
    public function getDatabaseConnection(): ?string
    {
        if ($this->parameterBag->has('audit_bundle.database.connection')) {
            return $this->parameterBag->get('audit_bundle.database.connection');
        }

        return null; // Use default connection
    }

    /**
     * Get API configuration.
     */
    public function getApiConfig(): array
    {
        return [
            'enabled' => $this->parameterBag->get('audit_bundle.api.enabled') ?? false,
            'rate_limit' => $this->parameterBag->get('audit_bundle.api.rate_limit') ?? 100,
            'prefix' => $this->parameterBag->get('audit_bundle.api.prefix') ?? '/api/audit',
        ];
    }

    /**
     * Check if API is enabled.
     */
    public function isApiEnabled(): bool
    {
        return (bool) ($this->parameterBag->get('audit_bundle.api.enabled') ?? false);
    }

    /**
     * Get security roles configuration.
     */
    public function getSecurityRoles(): array
    {
        return [
            'admin' => $this->parameterBag->get('audit_bundle.security.roles.admin') ?? 'ROLE_AUDIT_ADMIN',
            'auditor' => $this->parameterBag->get('audit_bundle.security.roles.auditor') ?? 'ROLE_AUDIT_AUDITOR',
            'developer' => $this->parameterBag->get('audit_bundle.security.roles.developer') ?? 'ROLE_AUDIT_DEVELOPER',
        ];
    }

    /**
     * Get UI configuration.
     */
    public function getUiConfig(): array
    {
        return [
            'route_prefix' => $this->parameterBag->get('audit_bundle.ui.route_prefix') ?? '/audit',
            'items_per_page' => $this->parameterBag->get('audit_bundle.ui.items_per_page') ?? 25,
            'show_ip_address' => $this->parameterBag->get('audit_bundle.ui.show_ip_address') ?? true,
        ];
    }

    /**
     * Get all entity configurations.
     */
    public function getAllEntityConfigs(): array
    {
        return $this->entityConfigRepository->findAll();
    }

    /**
     * Get enabled entity configurations.
     */
    public function getEnabledEntityConfigs(): array
    {
        return $this->entityConfigRepository->findEnabledConfigurations();
    }

    /**
     * Check if entity is auditable.
     */
    public function isEntityAuditable(string $entityClass): bool
    {
        if (!$this->isAuditEnabled()) {
            return false;
        }

        return $this->entityConfigRepository->isEntityAuditable($entityClass);
    }

    /**
     * Get ignored columns for entity.
     */
    public function getIgnoredColumns(string $entityClass): array
    {
        $config = $this->getEntityConfig($entityClass);

        return $config ? $config->getIgnoredColumns() : [];
    }

    /**
     * Check if column is ignored for entity.
     */
    public function isColumnIgnored(string $entityClass, string $columnName): bool
    {
        return $this->entityConfigRepository->isColumnIgnored($entityClass, $columnName);
    }

    /**
     * Enable auditing for entity.
     */
    public function enableEntityAuditing(string $entityClass, array $options = []): EntityConfig
    {
        return $this->updateEntityConfig($entityClass, array_merge([
            'enabled' => true,
        ], $options));
    }

    /**
     * Disable auditing for entity.
     */
    public function disableEntityAuditing(string $entityClass): EntityConfig
    {
        return $this->updateEntityConfig($entityClass, ['enabled' => false]);
    }

    /**
     * Add ignored column for entity.
     */
    public function addIgnoredColumn(string $entityClass, string $columnName): EntityConfig
    {
        $config = $this->getOrCreateEntityConfig($entityClass);
        $ignoredColumns = $config->getIgnoredColumns();

        if (!in_array($columnName, $ignoredColumns)) {
            $ignoredColumns[] = $columnName;
            $config->setIgnoredColumns($ignoredColumns);
            $this->entityConfigRepository->save($config, true);
            $this->cachedEntityConfigs[$entityClass] = $config;
        }

        return $config;
    }

    /**
     * Remove ignored column for entity.
     */
    public function removeIgnoredColumn(string $entityClass, string $columnName): EntityConfig
    {
        $config = $this->getOrCreateEntityConfig($entityClass);
        $ignoredColumns = $config->getIgnoredColumns();

        $key = array_search($columnName, $ignoredColumns);
        if (false !== $key) {
            unset($ignoredColumns[$key]);
            $config->setIgnoredColumns(array_values($ignoredColumns));
            $this->entityConfigRepository->save($config, true);
            $this->cachedEntityConfigs[$entityClass] = $config;
        }

        return $config;
    }

    /**
     * Clear configuration cache.
     */
    public function clearCache(): void
    {
        $this->cachedConfig = null;
        $this->cachedEntityConfigs = [];
    }

    /**
     * Get configuration summary.
     */
    public function getConfigurationSummary(): array
    {
        $auditConfig = $this->getAuditConfig();
        $entityConfigs = $this->getAllEntityConfigs();
        $enabledEntityConfigs = $this->getEnabledEntityConfigs();

        return [
            'audit_enabled' => $this->isAuditEnabled(),
            'retention_days' => $this->getRetentionDays(),
            'async_processing' => $this->isAsyncProcessingEnabled(),
            'api_enabled' => $this->isApiEnabled(),
            'total_entities' => count($entityConfigs),
            'enabled_entities' => count($enabledEntityConfigs),
            'disabled_entities' => count($entityConfigs) - count($enabledEntityConfigs),
            'database_connection' => $this->getDatabaseConnection() ?? 'default',
            'ui_config' => $this->getUiConfig(),
            'security_roles' => $this->getSecurityRoles(),
        ];
    }

    /**
     * Export configuration.
     */
    public function exportConfiguration(): array
    {
        $auditConfig = $this->getAuditConfig();
        $entityConfigs = $this->getAllEntityConfigs();

        $export = [
            'audit_config' => $auditConfig ? [
                'name' => $auditConfig->getName(),
                'enabled' => $auditConfig->isEnabled(),
                'retention_days' => $auditConfig->getRetentionDays(),
                'async_processing' => $auditConfig->isAsyncProcessing(),
                'settings' => $auditConfig->getSettings(),
            ] : null,
            'entity_configs' => [],
        ];

        foreach ($entityConfigs as $entityConfig) {
            $export['entity_configs'][] = [
                'entity_class' => $entityConfig->getEntityClass(),
                'enabled' => $entityConfig->isEnabled(),
                'ignored_columns' => $entityConfig->getIgnoredColumns(),
                'create_table' => $entityConfig->shouldCreateTable(),
                'table_name' => $entityConfig->getTableName(),
                'settings' => $entityConfig->getSettings(),
            ];
        }

        return $export;
    }

    /**
     * Import configuration.
     */
    public function importConfiguration(array $config): void
    {
        $this->entityManager->beginTransaction();

        try {
            // Import audit config
            if (isset($config['audit_config']) && $config['audit_config']) {
                $this->updateAuditConfig($config['audit_config']);
            }

            // Import entity configs
            if (isset($config['entity_configs']) && is_array($config['entity_configs'])) {
                foreach ($config['entity_configs'] as $entityConfigData) {
                    if (isset($entityConfigData['entity_class'])) {
                        $this->updateEntityConfig(
                            $entityConfigData['entity_class'],
                            $entityConfigData
                        );
                    }
                }
            }

            $this->entityManager->commit();
            $this->clearCache();

            $this->logger->info('Configuration imported successfully');
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Configuration import failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
