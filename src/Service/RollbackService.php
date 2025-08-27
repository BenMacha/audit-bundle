<?php

namespace BenMacha\AuditBundle\Service;

use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Repository\AuditChangeRepository;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class RollbackService
{
    private EntityManagerInterface $entityManager;
    private AuditLogRepository $auditLogRepository;
    private AuditChangeRepository $auditChangeRepository;
    private Security $security;
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private AuditManager $auditManager;
    private ConfigurationService $configurationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AuditLogRepository $auditLogRepository,
        AuditChangeRepository $auditChangeRepository,
        Security $security,
        RequestStack $requestStack,
        LoggerInterface $logger,
        AuditManager $auditManager,
        ConfigurationService $configurationService
    ) {
        $this->entityManager = $entityManager;
        $this->auditLogRepository = $auditLogRepository;
        $this->auditChangeRepository = $auditChangeRepository;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->auditManager = $auditManager;
        $this->configurationService = $configurationService;
    }

    /**
     * Rollback a single audit log entry.
     */
    public function rollbackAuditLog(AuditLog $auditLog, bool $createRollbackLog = true): bool
    {
        try {
            $this->entityManager->beginTransaction();

            // Validate rollback operation
            if (!$this->canRollback($auditLog)) {
                throw new \InvalidArgumentException('Cannot rollback this audit log entry');
            }

            $success = false;

            switch ($auditLog->getOperation()) {
                case 'INSERT':
                    $success = $this->rollbackInsert($auditLog);
                    break;
                case 'UPDATE':
                    $success = $this->rollbackUpdate($auditLog);
                    break;
                case 'DELETE':
                    $success = $this->rollbackDelete($auditLog);
                    break;
            }

            if ($success && $createRollbackLog) {
                $this->createRollbackLog($auditLog);
            }

            $this->entityManager->commit();

            return $success;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Rollback failed', [
                'audit_log_id' => $auditLog->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Rollback multiple audit log entries.
     */
    public function rollbackMultiple(array $auditLogIds, bool $createRollbackLog = true): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($auditLogIds as $auditLogId) {
            try {
                $auditLog = $this->auditLogRepository->find($auditLogId);
                if (!$auditLog) {
                    $results[$auditLogId] = [
                        'success' => false,
                        'error' => 'Audit log not found',
                    ];
                    ++$failureCount;
                    continue;
                }

                $success = $this->rollbackAuditLog($auditLog, $createRollbackLog);
                $results[$auditLogId] = ['success' => $success];

                if ($success) {
                    ++$successCount;
                } else {
                    ++$failureCount;
                }
            } catch (\Exception $e) {
                $results[$auditLogId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                ++$failureCount;
            }
        }

        return [
            'results' => $results,
            'summary' => [
                'total' => count($auditLogIds),
                'success' => $successCount,
                'failure' => $failureCount,
            ],
        ];
    }

    /**
     * Rollback entity to a specific point in time.
     */
    public function rollbackEntityToDate(string $entityClass, $entityId, \DateTime $targetDate): bool
    {
        // Get all audit logs for this entity after the target date
        $auditLogs = $this->auditLogRepository->createQueryBuilder('al')
            ->where('al.entityClass = :entityClass')
            ->andWhere('al.entityId = :entityId')
            ->andWhere('al.createdAt > :targetDate')
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityId', $entityId)
            ->setParameter('targetDate', $targetDate)
            ->orderBy('al.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($auditLogs)) {
            return true; // Nothing to rollback
        }

        try {
            $this->entityManager->beginTransaction();

            // Rollback in reverse chronological order
            foreach ($auditLogs as $auditLog) {
                $this->rollbackAuditLog($auditLog, false);
            }

            // Create a single rollback log for the entire operation
            $this->createBulkRollbackLog($auditLogs, $targetDate);

            $this->entityManager->commit();

            return true;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Entity rollback to date failed', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'target_date' => $targetDate->format('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if an audit log can be rolled back.
     */
    public function canRollback(AuditLog $auditLog): bool
    {
        // Check if rollback is enabled in configuration
        if (!$this->configurationService->isRollbackEnabled()) {
            return false;
        }

        // Check if entity still exists (for UPDATE and DELETE operations)
        if (in_array($auditLog->getOperation(), ['UPDATE', 'DELETE'])) {
            $entity = $this->findEntity($auditLog->getEntityClass(), $auditLog->getEntityId());
            if (!$entity && 'UPDATE' === $auditLog->getOperation()) {
                return false; // Cannot update a deleted entity
            }
        }

        // Check if entity was already rolled back
        if ($this->isAlreadyRolledBack($auditLog)) {
            return false;
        }

        // Check rollback time limit
        $timeLimit = $this->configurationService->getRollbackTimeLimit();
        if ($timeLimit && $auditLog->getCreatedAt() < new \DateTime("-{$timeLimit} hours")) {
            return false;
        }

        return true;
    }

    /**
     * Get rollback preview for an audit log.
     */
    public function getRollbackPreview(AuditLog $auditLog): array
    {
        $preview = [
            'audit_log_id' => $auditLog->getId(),
            'operation' => $auditLog->getOperation(),
            'entity_class' => $auditLog->getEntityClass(),
            'entity_id' => $auditLog->getEntityId(),
            'can_rollback' => $this->canRollback($auditLog),
            'changes' => [],
            'warnings' => [],
        ];

        // Get changes that will be applied
        $changes = $auditLog->getChanges();
        foreach ($changes as $change) {
            $preview['changes'][] = [
                'field' => $change->getFieldName(),
                'current_value' => $change->getNewValue(),
                'rollback_value' => $change->getOldValue(),
                'field_type' => $change->getFieldType(),
            ];
        }

        // Add warnings
        if ('INSERT' === $auditLog->getOperation()) {
            $preview['warnings'][] = 'This will delete the entity completely';
        }

        if ('DELETE' === $auditLog->getOperation()) {
            $preview['warnings'][] = 'This will restore a deleted entity';
        }

        // Check for potential conflicts
        if ($this->hasConflicts($auditLog)) {
            $preview['warnings'][] = 'There may be conflicts with current data';
        }

        return $preview;
    }

    /**
     * Get rollback history for an entity.
     */
    public function getRollbackHistory(string $entityClass, $entityId): array
    {
        return $this->auditLogRepository->createQueryBuilder('al')
            ->where('al.entityClass = :entityClass')
            ->andWhere('al.entityId = :entityId')
            ->andWhere('al.operation = :operation')
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityId', $entityId)
            ->setParameter('operation', 'ROLLBACK')
            ->orderBy('al.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rollback an INSERT operation (delete the entity).
     */
    private function rollbackInsert(AuditLog $auditLog): bool
    {
        $entity = $this->findEntity($auditLog->getEntityClass(), $auditLog->getEntityId());
        if (!$entity) {
            return false; // Entity already deleted
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Rollback an UPDATE operation (restore old values).
     */
    private function rollbackUpdate(AuditLog $auditLog): bool
    {
        $entity = $this->findEntity($auditLog->getEntityClass(), $auditLog->getEntityId());
        if (!$entity) {
            return false; // Entity was deleted
        }

        $changes = $auditLog->getChanges();
        $reflection = new \ReflectionClass($entity);

        foreach ($changes as $change) {
            $fieldName = $change->getFieldName();
            $oldValue = $this->deserializeValue($change->getOldValue(), $change->getFieldType());

            // Set the old value back
            if ($reflection->hasProperty($fieldName)) {
                $property = $reflection->getProperty($fieldName);
                $property->setAccessible(true);
                $property->setValue($entity, $oldValue);
            } else {
                // Try setter method
                $setter = 'set' . ucfirst($fieldName);
                if (method_exists($entity, $setter)) {
                    $entity->$setter($oldValue);
                }
            }
        }

        $this->entityManager->flush();

        return true;
    }

    /**
     * Rollback a DELETE operation (restore the entity).
     */
    private function rollbackDelete(AuditLog $auditLog): bool
    {
        $entityClass = $auditLog->getEntityClass();
        $oldValues = json_decode($auditLog->getOldValues(), true);

        if (empty($oldValues)) {
            return false;
        }

        // Create new entity instance
        $entity = new $entityClass();
        $reflection = new \ReflectionClass($entity);

        // Restore all old values
        foreach ($oldValues as $fieldName => $value) {
            $deserializedValue = $this->deserializeValue($value['value'], $value['type'] ?? 'string');

            if ($reflection->hasProperty($fieldName)) {
                $property = $reflection->getProperty($fieldName);
                $property->setAccessible(true);
                $property->setValue($entity, $deserializedValue);
            } else {
                $setter = 'set' . ucfirst($fieldName);
                if (method_exists($entity, $setter)) {
                    $entity->$setter($deserializedValue);
                }
            }
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Create a rollback audit log entry.
     */
    private function createRollbackLog(AuditLog $originalAuditLog): void
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $rollbackLog = new AuditLog();
        $rollbackLog->setEntityClass($originalAuditLog->getEntityClass());
        $rollbackLog->setEntityId($originalAuditLog->getEntityId());
        $rollbackLog->setOperation('ROLLBACK');
        $rollbackLog->setUserId($user ? $user->getUserIdentifier() : null);
        $rollbackLog->setUsername($user ? $user->getUserIdentifier() : 'system');
        $rollbackLog->setIpAddress($request ? $request->getClientIp() : null);
        $rollbackLog->setUserAgent($request ? $request->headers->get('User-Agent') : null);
        $rollbackLog->setMetadata(json_encode([
            'original_audit_log_id' => $originalAuditLog->getId(),
            'original_operation' => $originalAuditLog->getOperation(),
            'rollback_reason' => 'Manual rollback',
        ]));
        $rollbackLog->setCreatedAt(new \DateTime());

        $this->entityManager->persist($rollbackLog);
        $this->entityManager->flush();
    }

    /**
     * Create a bulk rollback log for multiple operations.
     */
    private function createBulkRollbackLog(array $auditLogs, \DateTime $targetDate): void
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $firstLog = reset($auditLogs);
        $rollbackLog = new AuditLog();
        $rollbackLog->setEntityClass($firstLog->getEntityClass());
        $rollbackLog->setEntityId($firstLog->getEntityId());
        $rollbackLog->setOperation('BULK_ROLLBACK');
        $rollbackLog->setUserId($user ? $user->getUserIdentifier() : null);
        $rollbackLog->setUsername($user ? $user->getUserIdentifier() : 'system');
        $rollbackLog->setIpAddress($request ? $request->getClientIp() : null);
        $rollbackLog->setUserAgent($request ? $request->headers->get('User-Agent') : null);
        $rollbackLog->setMetadata(json_encode([
            'target_date' => $targetDate->format('Y-m-d H:i:s'),
            'rolled_back_logs' => array_map(fn ($log) => $log->getId(), $auditLogs),
            'rollback_count' => count($auditLogs),
        ]));
        $rollbackLog->setCreatedAt(new \DateTime());

        $this->entityManager->persist($rollbackLog);
        $this->entityManager->flush();
    }

    /**
     * Find entity by class and ID.
     */
    private function findEntity(string $entityClass, $entityId)
    {
        return $this->entityManager->getRepository($entityClass)->find($entityId);
    }

    /**
     * Check if audit log was already rolled back.
     */
    private function isAlreadyRolledBack(AuditLog $auditLog): bool
    {
        $rollbackLog = $this->auditLogRepository->createQueryBuilder('al')
            ->where('al.entityClass = :entityClass')
            ->andWhere('al.entityId = :entityId')
            ->andWhere('al.operation IN (:operations)')
            ->andWhere('al.metadata LIKE :originalId')
            ->setParameter('entityClass', $auditLog->getEntityClass())
            ->setParameter('entityId', $auditLog->getEntityId())
            ->setParameter('operations', ['ROLLBACK', 'BULK_ROLLBACK'])
            ->setParameter('originalId', '%"original_audit_log_id":' . $auditLog->getId() . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $rollbackLog;
    }

    /**
     * Check for potential conflicts.
     */
    private function hasConflicts(AuditLog $auditLog): bool
    {
        // Check if there are newer changes to the same entity
        $newerLogs = $this->auditLogRepository->createQueryBuilder('al')
            ->where('al.entityClass = :entityClass')
            ->andWhere('al.entityId = :entityId')
            ->andWhere('al.createdAt > :createdAt')
            ->andWhere('al.id != :id')
            ->setParameter('entityClass', $auditLog->getEntityClass())
            ->setParameter('entityId', $auditLog->getEntityId())
            ->setParameter('createdAt', $auditLog->getCreatedAt())
            ->setParameter('id', $auditLog->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $newerLogs;
    }

    /**
     * Deserialize value based on type.
     */
    private function deserializeValue($value, string $type)
    {
        if (null === $value) {
            return null;
        }

        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'float':
            case 'decimal':
                return (float) $value;
            case 'boolean':
                return (bool) $value;
            case 'datetime':
                return new \DateTime($value);
            case 'date':
                return new \DateTime($value);
            case 'array':
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
}
