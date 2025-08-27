<?php

namespace BenMacha\AuditBundle\Repository;

use BenMacha\AuditBundle\Entity\EntityConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EntityConfig>
 */
class EntityConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityConfig::class);
    }

    /**
     * Find configuration by entity class.
     */
    public function findByEntityClass(string $entityClass): ?EntityConfig
    {
        return $this->findOneBy(['entityClass' => $entityClass]);
    }

    /**
     * Find all enabled entity configurations.
     */
    public function findEnabled(): array
    {
        return $this->createQueryBuilder('ec')
            ->where('ec.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('ec.entityClass', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find configurations that should create tables.
     */
    public function findWithTableCreation(): array
    {
        return $this->createQueryBuilder('ec')
            ->where('ec.enabled = :enabled')
            ->andWhere('ec.createTable = :createTable')
            ->setParameter('enabled', true)
            ->setParameter('createTable', true)
            ->orderBy('ec.entityClass', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get or create configuration for entity class.
     */
    public function getOrCreateForEntity(string $entityClass): EntityConfig
    {
        $config = $this->findByEntityClass($entityClass);

        if (!$config) {
            $config = new EntityConfig();
            $config->setEntityClass($entityClass)
                ->setEnabled(true)
                ->setCreateTable(true);

            $this->getEntityManager()->persist($config);
            $this->getEntityManager()->flush();
        }

        return $config;
    }

    /**
     * Check if entity class is auditable.
     */
    public function isEntityAuditable(string $entityClass): bool
    {
        $config = $this->findByEntityClass($entityClass);

        return $config && $config->isEnabled();
    }

    /**
     * Get ignored columns for entity class.
     */
    public function getIgnoredColumns(string $entityClass): array
    {
        $config = $this->findByEntityClass($entityClass);

        return $config ? $config->getIgnoredColumns() : [];
    }

    /**
     * Check if column should be ignored for entity.
     */
    public function isColumnIgnored(string $entityClass, string $column): bool
    {
        $config = $this->findByEntityClass($entityClass);

        return $config && $config->isColumnIgnored($column);
    }

    /**
     * Get entity configurations with statistics.
     */
    public function findWithStatistics(): array
    {
        return $this->createQueryBuilder('ec')
            ->leftJoin('ec.auditLogs', 'al')
            ->addSelect('COUNT(al.id) as logCount')
            ->groupBy('ec.id')
            ->orderBy('ec.entityClass', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find configurations by audit config.
     */
    public function findByAuditConfig(int $auditConfigId): array
    {
        return $this->createQueryBuilder('ec')
            ->where('ec.auditConfig = :auditConfigId')
            ->setParameter('auditConfigId', $auditConfigId)
            ->orderBy('ec.entityClass', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get configuration statistics.
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('ec');

        return [
            'total' => $qb->select('COUNT(ec.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'enabled' => $qb->select('COUNT(ec.id)')
                ->where('ec.enabled = :enabled')
                ->setParameter('enabled', true)
                ->getQuery()
                ->getSingleScalarResult(),
            'disabled' => $qb->select('COUNT(ec.id)')
                ->where('ec.enabled = :enabled')
                ->setParameter('enabled', false)
                ->getQuery()
                ->getSingleScalarResult(),
            'with_tables' => $qb->select('COUNT(ec.id)')
                ->where('ec.createTable = :createTable')
                ->setParameter('createTable', true)
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    /**
     * Bulk update entity configurations.
     */
    public function bulkUpdate(array $entityClasses, array $settings): int
    {
        $qb = $this->createQueryBuilder('ec')
            ->update()
            ->where('ec.entityClass IN (:entityClasses)')
            ->setParameter('entityClasses', $entityClasses);

        foreach ($settings as $field => $value) {
            $qb->set('ec.' . $field, ':' . $field)
                ->setParameter($field, $value);
        }

        return $qb->getQuery()->execute();
    }

    public function save(EntityConfig $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EntityConfig $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
