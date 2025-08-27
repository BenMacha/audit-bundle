<?php

namespace BenMacha\AuditBundle\EventListener;

use BenMacha\AuditBundle\Attribute\Auditable;
use BenMacha\AuditBundle\Attribute\IgnoreAudit;
use BenMacha\AuditBundle\Service\AuditManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

/**
 * Doctrine Event Listener for Audit Tracking.
 *
 * Listens to entity lifecycle events and triggers audit logging
 * for configured entities.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
class AuditEventListener
{
    private AuditManager $auditManager;
    private array $config;
    private array $pendingRemovals = [];

    public function __construct(AuditManager $auditManager, array $config)
    {
        $this->auditManager = $auditManager;
        $this->config = $config;
    }

    /**
     * Handle entity insertion.
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->shouldAuditEntity($entity)) {
            return;
        }

        $this->auditManager->logEntityChange(
            $entity,
            'insert',
            [],
            $this->getEntitySnapshot($entity, $args->getObjectManager())
        );
    }

    /**
     * Handle entity updates.
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->shouldAuditEntity($entity)) {
            return;
        }

        $changeSet = $args->getEntityChangeSet();
        $filteredChangeSet = $this->filterIgnoredColumns($entity, $changeSet);

        if (empty($filteredChangeSet)) {
            return;
        }

        $this->auditManager->logEntityChange(
            $entity,
            'update',
            $filteredChangeSet,
            $this->getEntitySnapshot($entity, $args->getObjectManager())
        );
    }

    /**
     * Store entity data before removal.
     */
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->shouldAuditEntity($entity)) {
            return;
        }

        // Store entity snapshot before removal
        $entityId = $this->getEntityId($entity, $args->getObjectManager());
        $this->pendingRemovals[$entityId] = [
            'entity' => $entity,
            'snapshot' => $this->getEntitySnapshot($entity, $args->getObjectManager()),
        ];
    }

    /**
     * Handle entity removal.
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->shouldAuditEntity($entity)) {
            return;
        }

        $entityId = $this->getEntityId($entity, $args->getObjectManager());

        if (!isset($this->pendingRemovals[$entityId])) {
            return;
        }

        $pendingData = $this->pendingRemovals[$entityId];

        $this->auditManager->logEntityChange(
            $entity,
            'delete',
            [],
            $pendingData['snapshot']
        );

        // Clean up pending removal data
        unset($this->pendingRemovals[$entityId]);
    }

    /**
     * Check if entity should be audited.
     */
    private function shouldAuditEntity(object $entity): bool
    {
        // Check if auditing is globally enabled
        if (!$this->config['enabled']) {
            return false;
        }

        $entityClass = get_class($entity);
        $reflection = new \ReflectionClass($entityClass);

        // Check for IgnoreAudit attribute
        if (!empty($reflection->getAttributes(IgnoreAudit::class))) {
            return false;
        }

        // Check if entity has Auditable attribute
        $auditableAttributes = $reflection->getAttributes(Auditable::class);
        if (!empty($auditableAttributes)) {
            $auditable = $auditableAttributes[0]->newInstance();

            return $auditable->enabled;
        }

        // Check configuration for this entity
        if (isset($this->config['entities'][$entityClass])) {
            return $this->config['entities'][$entityClass]['enabled'] ?? true;
        }

        // Default behavior - audit all entities unless explicitly disabled
        return true;
    }

    /**
     * Filter out ignored columns from change set.
     */
    private function filterIgnoredColumns(object $entity, array $changeSet): array
    {
        $entityClass = get_class($entity);
        $ignoredColumns = [];

        // Get ignored columns from configuration
        if (isset($this->config['entities'][$entityClass]['ignored_columns'])) {
            $ignoredColumns = array_merge(
                $ignoredColumns,
                $this->config['entities'][$entityClass]['ignored_columns']
            );
        }

        // Get ignored columns from attributes
        $reflection = new \ReflectionClass($entityClass);
        foreach ($reflection->getProperties() as $property) {
            $ignoreAttributes = $property->getAttributes(IgnoreAudit::class);
            if (!empty($ignoreAttributes)) {
                $ignoredColumns[] = $property->getName();
            }
        }

        // Filter change set
        return array_filter(
            $changeSet,
            fn ($column) => !in_array($column, $ignoredColumns),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Get entity snapshot for audit logging.
     */
    private function getEntitySnapshot(object $entity, $entityManager): array
    {
        $metadata = $entityManager->getClassMetadata(get_class($entity));
        $snapshot = [];

        foreach ($metadata->getFieldNames() as $fieldName) {
            $value = $metadata->getFieldValue($entity, $fieldName);
            $snapshot[$fieldName] = $this->serializeValue($value);
        }

        // Include association data
        foreach ($metadata->getAssociationNames() as $associationName) {
            $value = $metadata->getFieldValue($entity, $associationName);
            if (null !== $value) {
                if ($metadata->isSingleValuedAssociation($associationName)) {
                    $snapshot[$associationName] = $this->getEntityId($value, $entityManager);
                } else {
                    $snapshot[$associationName] = array_map(
                        fn ($item) => $this->getEntityId($item, $entityManager),
                        $value->toArray()
                    );
                }
            }
        }

        return $snapshot;
    }

    /**
     * Get entity ID for audit logging.
     */
    private function getEntityId(object $entity, $entityManager): string
    {
        $metadata = $entityManager->getClassMetadata(get_class($entity));
        $identifierValues = $metadata->getIdentifierValues($entity);

        return implode('|', $identifierValues);
    }

    /**
     * Serialize value for storage.
     */
    private function serializeValue($value): mixed
    {
        if ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            return get_class($value) . ':' . spl_object_id($value);
        }

        if (is_resource($value)) {
            return 'resource:' . get_resource_type($value);
        }

        return $value;
    }
}
