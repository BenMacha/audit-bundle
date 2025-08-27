<?php

namespace BenMacha\AuditBundle\Repository;

use BenMacha\AuditBundle\Entity\AuditChange;
use BenMacha\AuditBundle\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditChange>
 */
class AuditChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditChange::class);
    }

    /**
     * Find changes by audit log.
     */
    public function findByAuditLog(AuditLog $auditLog): array
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.auditLog = :auditLog')
            ->setParameter('auditLog', $auditLog)
            ->orderBy('ac.fieldName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find changes by field name across multiple logs.
     */
    public function findByFieldName(string $fieldName, int $limit = 100): array
    {
        return $this->createQueryBuilder('ac')
            ->leftJoin('ac.auditLog', 'al')
            ->addSelect('al')
            ->where('ac.fieldName = :fieldName')
            ->setParameter('fieldName', $fieldName)
            ->orderBy('ac.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find changes for specific entity and field.
     */
    public function findByEntityAndField(
        string $entityClass,
        string $entityId,
        string $fieldName,
        int $limit = 50
    ): array {
        return $this->createQueryBuilder('ac')
            ->leftJoin('ac.auditLog', 'al')
            ->addSelect('al')
            ->where('al.entityClass = :entityClass')
            ->andWhere('al.entityId = :entityId')
            ->andWhere('ac.fieldName = :fieldName')
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityId', $entityId)
            ->setParameter('fieldName', $fieldName)
            ->orderBy('ac.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get field change statistics.
     */
    public function getFieldStatistics(?string $entityClass = null): array
    {
        $qb = $this->createQueryBuilder('ac')
            ->leftJoin('ac.auditLog', 'al')
            ->select('ac.fieldName, COUNT(ac.id) as changeCount')
            ->groupBy('ac.fieldName')
            ->orderBy('changeCount', 'DESC');

        if ($entityClass) {
            $qb->where('al.entityClass = :entityClass')
                ->setParameter('entityClass', $entityClass);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get most frequently changed fields.
     */
    public function getMostChangedFields(int $limit = 10): array
    {
        return $this->createQueryBuilder('ac')
            ->leftJoin('ac.auditLog', 'al')
            ->select('ac.fieldName, al.entityClass, COUNT(ac.id) as changeCount')
            ->groupBy('ac.fieldName', 'al.entityClass')
            ->orderBy('changeCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find changes with specific old or new values.
     */
    public function findByValue(string $value, bool $searchOldValue = true, bool $searchNewValue = true): array
    {
        $qb = $this->createQueryBuilder('ac')
            ->leftJoin('ac.auditLog', 'al')
            ->addSelect('al');

        $conditions = [];
        if ($searchOldValue) {
            $conditions[] = 'ac.oldValue LIKE :value';
        }
        if ($searchNewValue) {
            $conditions[] = 'ac.newValue LIKE :value';
        }

        if (!empty($conditions)) {
            $qb->where($qb->expr()->orX(...$conditions))
                ->setParameter('value', '%' . $value . '%');
        }

        return $qb->orderBy('ac.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get changes grouped by field type.
     */
    public function getChangesByFieldType(): array
    {
        return $this->createQueryBuilder('ac')
            ->select('ac.fieldType, COUNT(ac.id) as changeCount')
            ->where('ac.fieldType IS NOT NULL')
            ->groupBy('ac.fieldType')
            ->orderBy('changeCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent changes for dashboard.
     */
    public function getRecentChanges(int $limit = 20): array
    {
        return $this->createQueryBuilder('ac')
            ->leftJoin('ac.auditLog', 'al')
            ->addSelect('al')
            ->orderBy('ac.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count changes by date range.
     */
    public function countByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return $this->createQueryBuilder('ac')
            ->select('COUNT(ac.id)')
            ->where('ac.createdAt >= :from')
            ->andWhere('ac.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get unique field names.
     */
    public function getUniqueFieldNames(?string $entityClass = null): array
    {
        $qb = $this->createQueryBuilder('ac')
            ->select('DISTINCT ac.fieldName')
            ->orderBy('ac.fieldName', 'ASC');

        if ($entityClass) {
            $qb->leftJoin('ac.auditLog', 'al')
                ->where('al.entityClass = :entityClass')
                ->setParameter('entityClass', $entityClass);
        }

        $result = $qb->getQuery()->getScalarResult();

        return array_column($result, 'fieldName');
    }

    /**
     * Get field types used in changes.
     */
    public function getUniqueFieldTypes(): array
    {
        $result = $this->createQueryBuilder('ac')
            ->select('DISTINCT ac.fieldType')
            ->where('ac.fieldType IS NOT NULL')
            ->orderBy('ac.fieldType', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'fieldType');
    }

    /**
     * Delete changes older than specified date.
     */
    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('ac')
            ->delete()
            ->where('ac.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Get changes for rollback (in reverse chronological order).
     */
    public function getChangesForRollback(
        string $entityClass,
        string $entityId,
        \DateTimeInterface $rollbackTo
    ): array {
        return $this->createQueryBuilder('ac')
            ->leftJoin('ac.auditLog', 'al')
            ->addSelect('al')
            ->where('al.entityClass = :entityClass')
            ->andWhere('al.entityId = :entityId')
            ->andWhere('ac.createdAt > :rollbackTo')
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityId', $entityId)
            ->setParameter('rollbackTo', $rollbackTo)
            ->orderBy('ac.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(AuditChange $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditChange $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
