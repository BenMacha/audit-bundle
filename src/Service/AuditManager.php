<?php

namespace BenMacha\AuditBundle\Service;

use BenMacha\AuditBundle\Attribute\Auditable;
use BenMacha\AuditBundle\Attribute\IgnoreAudit;
use BenMacha\AuditBundle\Entity\AuditChange;
use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Entity\EntityConfig;
use BenMacha\AuditBundle\Repository\AuditChangeRepository;
use BenMacha\AuditBundle\Repository\AuditConfigRepository;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Repository\EntityConfigRepository;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

class AuditManager
{
    private EntityManagerInterface $entityManager;
    private AuditConfigRepository $auditConfigRepository;
    private EntityConfigRepository $entityConfigRepository;
    private AuditLogRepository $auditLogRepository;
    private AuditChangeRepository $auditChangeRepository;
    private Security $security;
    private RequestStack $requestStack;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;
    private MetadataCollector $metadataCollector;
    private ConfigurationService $configurationService;
    private array $pendingAudits = [];
    private bool $isProcessing = false;

    public function __construct(
        EntityManagerInterface $entityManager,
        AuditConfigRepository $auditConfigRepository,
        EntityConfigRepository $entityConfigRepository,
        AuditLogRepository $auditLogRepository,
        AuditChangeRepository $auditChangeRepository,
        Security $security,
        RequestStack $requestStack,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        MetadataCollector $metadataCollector,
        ConfigurationService $configurationService
    ) {
        $this->entityManager = $entityManager;
        $this->auditConfigRepository = $auditConfigRepository;
        $this->entityConfigRepository = $entityConfigRepository;
        $this->auditLogRepository = $auditLogRepository;
        $this->auditChangeRepository = $auditChangeRepository;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->metadataCollector = $metadataCollector;
        $this->configurationService = $configurationService;
    }

    /**
     * Process audit for an entity operation.
     */
    public function processAudit(
        object $entity,
        string $operation,
        array $oldValues = [],
        array $newValues = []
    ): ?AuditLog {
        if ($this->isProcessing) {
            return null; // Prevent recursive auditing
        }

        try {
            $this->isProcessing = true;
            $entityClass = get_class($entity);

            // Check if entity should be audited
            if (!$this->shouldAuditEntity($entityClass)) {
                return null;
            }

            // Get entity configuration
            $entityConfig = $this->entityConfigRepository->getOrCreateByEntityClass($entityClass);
            if (!$entityConfig->isEnabled()) {
                return null;
            }

            // Create audit log entry
            $auditLog = $this->createAuditLog($entity, $operation, $entityConfig);

            // Process field changes
            $this->processFieldChanges($auditLog, $oldValues, $newValues, $entityConfig);

            // Save audit log
            $this->auditLogRepository->save($auditLog, true);

            // Create audit table if needed
            if ($entityConfig->shouldCreateTable()) {
                $this->createAuditTable($entityConfig);
            }

            $this->logger->info('Audit processed', [
                'entity_class' => $entityClass,
                'entity_id' => $this->getEntityId($entity),
                'operation' => $operation,
                'audit_log_id' => $auditLog->getId(),
            ]);

            return $auditLog;
        } catch (\Exception $e) {
            $this->logger->error('Audit processing failed', [
                'entity_class' => get_class($entity),
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Queue audit for asynchronous processing.
     */
    public function queueAudit(
        object $entity,
        string $operation,
        array $oldValues = [],
        array $newValues = []
    ): void {
        $this->pendingAudits[] = [
            'entity' => $entity,
            'operation' => $operation,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'timestamp' => new \DateTime(),
        ];
    }

    /**
     * Process all pending audits.
     */
    public function processPendingAudits(): void
    {
        foreach ($this->pendingAudits as $auditData) {
            $this->processAudit(
                $auditData['entity'],
                $auditData['operation'],
                $auditData['old_values'],
                $auditData['new_values']
            );
        }
        $this->pendingAudits = [];
    }

    /**
     * Check if entity should be audited.
     */
    public function shouldAuditEntity(string $entityClass): bool
    {
        // Check global configuration
        if (!$this->configurationService->isAuditEnabled()) {
            return false;
        }

        // Check if entity has IgnoreAudit attribute
        $reflectionClass = new \ReflectionClass($entityClass);
        $ignoreAttributes = $reflectionClass->getAttributes(IgnoreAudit::class);
        if (!empty($ignoreAttributes)) {
            return false;
        }

        // Check if entity has Auditable attribute
        $auditableAttributes = $reflectionClass->getAttributes(Auditable::class);
        if (!empty($auditableAttributes)) {
            $auditable = $auditableAttributes[0]->newInstance();

            return $auditable->enabled;
        }

        // Check entity configuration
        return $this->entityConfigRepository->isEntityAuditable($entityClass);
    }

    /**
     * Get ignored columns for an entity.
     */
    public function getIgnoredColumns(string $entityClass): array
    {
        $ignoredColumns = [];

        // Get from entity configuration
        $entityConfig = $this->entityConfigRepository->findByEntityClass($entityClass);
        if ($entityConfig) {
            $ignoredColumns = array_merge($ignoredColumns, $entityConfig->getIgnoredColumns());
        }

        // Get from Auditable attribute
        $reflectionClass = new \ReflectionClass($entityClass);
        $auditableAttributes = $reflectionClass->getAttributes(Auditable::class);
        if (!empty($auditableAttributes)) {
            $auditable = $auditableAttributes[0]->newInstance();
            $ignoredColumns = array_merge($ignoredColumns, $auditable->ignoredColumns);
        }

        // Get from property IgnoreAudit attributes
        foreach ($reflectionClass->getProperties() as $property) {
            $ignoreAttributes = $property->getAttributes(IgnoreAudit::class);
            if (!empty($ignoreAttributes)) {
                $ignoredColumns[] = $property->getName();
            }
        }

        return array_unique($ignoredColumns);
    }

    /**
     * Create audit log entry.
     */
    private function createAuditLog(object $entity, string $operation, EntityConfig $entityConfig): AuditLog
    {
        $auditLog = new AuditLog();
        $auditLog->setEntityClass(get_class($entity));
        $auditLog->setEntityId($this->getEntityId($entity));
        $auditLog->setOperation($operation);
        $auditLog->setEntityConfig($entityConfig);

        // Set user information
        $user = $this->security->getUser();
        if ($user) {
            $auditLog->setUserId($this->getUserId($user));
            $auditLog->setUsername($this->getUsername($user));
        }

        // Set request information
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        // Set metadata
        $metadata = $this->metadataCollector->collectMetadata($entity, $operation);
        $auditLog->setMetadata($metadata);

        return $auditLog;
    }

    /**
     * Process field changes.
     */
    private function processFieldChanges(
        AuditLog $auditLog,
        array $oldValues,
        array $newValues,
        EntityConfig $entityConfig
    ): void {
        $ignoredColumns = $this->getIgnoredColumns($auditLog->getEntityClass());
        $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allFields as $fieldName) {
            // Skip ignored columns
            if (in_array($fieldName, $ignoredColumns)) {
                continue;
            }

            $oldValue = $oldValues[$fieldName] ?? null;
            $newValue = $newValues[$fieldName] ?? null;

            // Skip if values are the same
            if ($this->valuesAreEqual($oldValue, $newValue)) {
                continue;
            }

            $auditChange = new AuditChange();
            $auditChange->setAuditLog($auditLog);
            $auditChange->setFieldName($fieldName);
            $auditChange->setOldValue($this->serializeValue($oldValue));
            $auditChange->setNewValue($this->serializeValue($newValue));
            $auditChange->setFieldType($this->getFieldType($oldValue, $newValue));

            $auditLog->addAuditChange($auditChange);
        }

        // Set old and new values on audit log
        $auditLog->setOldValues($oldValues);
        $auditLog->setNewValues($newValues);
    }

    /**
     * Create audit table for entity.
     */
    private function createAuditTable(EntityConfig $entityConfig): void
    {
        try {
            $connection = $this->entityManager->getConnection();
            $schemaManager = $connection->createSchemaManager();
            $tableName = $entityConfig->getEffectiveTableName();

            // Check if table already exists
            if ($schemaManager->tablesExist([$tableName])) {
                return;
            }

            $table = new Table($tableName);
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
            $table->addColumn('entity_id', Types::STRING, ['length' => 255]);
            $table->addColumn('operation', Types::STRING, ['length' => 10]);
            $table->addColumn('user_id', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('username', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('ip_address', Types::STRING, ['length' => 45, 'notnull' => false]);
            $table->addColumn('user_agent', Types::TEXT, ['notnull' => false]);
            $table->addColumn('old_values', Types::JSON, ['notnull' => false]);
            $table->addColumn('new_values', Types::JSON, ['notnull' => false]);
            $table->addColumn('metadata', Types::JSON, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME_MUTABLE);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['entity_id']);
            $table->addIndex(['operation']);
            $table->addIndex(['user_id']);
            $table->addIndex(['created_at']);

            $schemaManager->createTable($table);

            $this->logger->info('Audit table created', ['table_name' => $tableName]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create audit table', [
                'table_name' => $entityConfig->getEffectiveTableName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get entity ID.
     */
    private function getEntityId(object $entity): string
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $identifierValues = $metadata->getIdentifierValues($entity);

        if (1 === count($identifierValues)) {
            return (string) reset($identifierValues);
        }

        return json_encode($identifierValues);
    }

    /**
     * Get user ID.
     */
    private function getUserId($user): ?string
    {
        if (method_exists($user, 'getId')) {
            return (string) $user->getId();
        }

        if (method_exists($user, 'getUserIdentifier')) {
            return $user->getUserIdentifier();
        }

        return null;
    }

    /**
     * Get username.
     */
    private function getUsername($user): ?string
    {
        if (method_exists($user, 'getUsername')) {
            return $user->getUsername();
        }

        if (method_exists($user, 'getUserIdentifier')) {
            return $user->getUserIdentifier();
        }

        return null;
    }

    /**
     * Serialize value for storage.
     */
    private function serializeValue($value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        try {
            return $this->serializer->serialize($value, 'json');
        } catch (\Exception $e) {
            return '[Serialization Error: ' . $e->getMessage() . ']';
        }
    }

    /**
     * Get field type.
     */
    private function getFieldType($oldValue, $newValue): ?string
    {
        $value = $newValue ?? $oldValue;

        if (null === $value) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_string($value)) {
            return 'string';
        }

        if ($value instanceof \DateTimeInterface) {
            return 'datetime';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_object($value)) {
            return 'object';
        }

        return 'unknown';
    }

    /**
     * Check if values are equal.
     */
    private function valuesAreEqual($oldValue, $newValue): bool
    {
        if ($oldValue === $newValue) {
            return true;
        }

        // Handle DateTime comparison
        if ($oldValue instanceof \DateTimeInterface && $newValue instanceof \DateTimeInterface) {
            return $oldValue->getTimestamp() === $newValue->getTimestamp();
        }

        // Handle array comparison
        if (is_array($oldValue) && is_array($newValue)) {
            return json_encode($oldValue) === json_encode($newValue);
        }

        return false;
    }

    /**
     * Get audit statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_logs' => $this->auditLogRepository->count([]),
            'total_changes' => $this->auditChangeRepository->count([]),
            'audited_entities' => count($this->entityConfigRepository->findEnabledConfigurations()),
            'recent_activity' => $this->auditLogRepository->getRecentActivity(10),
        ];
    }

    /**
     * Clean up old audit data.
     */
    public function cleanup(int $retentionDays): int
    {
        $cutoffDate = new \DateTime("-{$retentionDays} days");

        $deletedChanges = $this->auditChangeRepository->deleteOlderThan($cutoffDate);
        $deletedLogs = $this->auditLogRepository->deleteOlderThan($cutoffDate);

        $this->logger->info('Audit cleanup completed', [
            'retention_days' => $retentionDays,
            'deleted_logs' => $deletedLogs,
            'deleted_changes' => $deletedChanges,
        ]);

        return $deletedLogs + $deletedChanges;
    }
}
