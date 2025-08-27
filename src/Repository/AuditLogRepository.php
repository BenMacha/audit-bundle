<?php

namespace BenMacha\AuditBundle\Repository;

use BenMacha\AuditBundle\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Find audit logs with pagination and filters.
     */
    public function findWithFilters(
        array $filters = [],
        int $page = 1,
        int $limit = 20,
        string $orderBy = 'createdAt',
        string $orderDirection = 'DESC'
    ): Paginator {
        $qb = $this->createQueryBuilder('al')
            ->leftJoin('al.changes', 'ac')
            ->leftJoin('al.entityConfig', 'ec')
            ->addSelect('ac', 'ec');

        $this->applyFilters($qb, $filters);

        $qb->orderBy('al.' . $orderBy, $orderDirection)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), true);
    }

    /**
     * Apply filters to query builder.
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['entityClass'])) {
            $qb->andWhere('al.entityClass = :entityClass')
                ->setParameter('entityClass', $filters['entityClass']);
        }

        if (!empty($filters['entityId'])) {
            $qb->andWhere('al.entityId = :entityId')
                ->setParameter('entityId', $filters['entityId']);
        }

        if (!empty($filters['operation'])) {
            $qb->andWhere('al.operation = :operation')
                ->setParameter('operation', $filters['operation']);
        }

        if (!empty($filters['userId'])) {
            $qb->andWhere('al.userId = :userId')
                ->setParameter('userId', $filters['userId']);
        }

        if (!empty($filters['username'])) {
            $qb->andWhere('al.username LIKE :username')
                ->setParameter('username', '%' . $filters['username'] . '%');
        }

        if (!empty($filters['ipAddress'])) {
            $qb->andWhere('al.ipAddress = :ipAddress')
                ->setParameter('ipAddress', $filters['ipAddress']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('al.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('al.createdAt <= :dateTo')
                ->setParameter('dateTo', $filters['dateTo']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'al.entityClass LIKE :search',
                    'al.entityId LIKE :search',
                    'al.username LIKE :search',
                    'al.ipAddress LIKE :search'
                )
            )->setParameter('search', '%' . $filters['search'] . '%');
        }
    }

    /**
     * Find logs for specific entity.
     */
    public function findByEntity(string $entityClass, string $entityId, int $limit = 50): array
    {
        return $this->createQueryBuilder('al')
            ->leftJoin('al.changes', 'ac')
            ->addSelect('ac')
            ->where('al.entityClass = :entityClass')
            ->andWhere('al.entityId = :entityId')
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityId', $entityId)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs by user.
     */
    public function findByUser(string $userId, int $limit = 100): array
    {
        return $this->createQueryBuilder('al')
            ->where('al.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get audit statistics.
     */
    public function getStatistics(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('al');

        if ($from) {
            $qb->andWhere('al.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('al.createdAt <= :to')
                ->setParameter('to', $to);
        }

        $total = (clone $qb)->select('COUNT(al.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $byOperation = (clone $qb)
            ->select('al.operation, COUNT(al.id) as count')
            ->groupBy('al.operation')
            ->getQuery()
            ->getResult();

        $byEntity = (clone $qb)
            ->select('al.entityClass, COUNT(al.id) as count')
            ->groupBy('al.entityClass')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $byUser = (clone $qb)
            ->select('al.username, COUNT(al.id) as count')
            ->where('al.username IS NOT NULL')
            ->groupBy('al.username')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return [
            'total' => $total,
            'by_operation' => $byOperation,
            'by_entity' => $byEntity,
            'by_user' => $byUser,
        ];
    }

    /**
     * Get daily statistics for chart.
     */
    public function getDailyStatistics(int $days = 30): array
    {
        $from = new \DateTime('-' . $days . ' days');
        $from->setTime(0, 0, 0);

        return $this->createQueryBuilder('al')
            ->select('DATE(al.createdAt) as date, COUNT(al.id) as count')
            ->where('al.createdAt >= :from')
            ->setParameter('from', $from)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent activity.
     */
    public function getRecentActivity(int $limit = 10): array
    {
        return $this->createQueryBuilder('al')
            ->leftJoin('al.changes', 'ac')
            ->addSelect('ac')
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs older than specified date for cleanup.
     */
    public function findOlderThan(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('al')
            ->where('al.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete logs older than specified date.
     */
    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('al')
            ->delete()
            ->where('al.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Get unique entity classes from logs.
     */
    public function getUniqueEntityClasses(): array
    {
        $result = $this->createQueryBuilder('al')
            ->select('DISTINCT al.entityClass')
            ->orderBy('al.entityClass', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'entityClass');
    }

    /**
     * Get unique users from logs.
     */
    public function getUniqueUsers(): array
    {
        $result = $this->createQueryBuilder('al')
            ->select('DISTINCT al.username')
            ->where('al.username IS NOT NULL')
            ->orderBy('al.username', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'username');
    }

    /**
     * Count logs by filters.
     */
    public function countByFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('al')
            ->select('COUNT(al.id)');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function save(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
