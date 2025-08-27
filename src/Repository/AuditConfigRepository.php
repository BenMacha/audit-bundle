<?php

namespace BenMacha\AuditBundle\Repository;

use BenMacha\AuditBundle\Entity\AuditConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditConfig>
 */
class AuditConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditConfig::class);
    }

    /**
     * Find the default audit configuration.
     */
    public function findDefault(): ?AuditConfig
    {
        return $this->findOneBy(['name' => 'default']);
    }

    /**
     * Find all enabled configurations.
     */
    public function findEnabled(): array
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('ac.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find configuration by name.
     */
    public function findByName(string $name): ?AuditConfig
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Get or create default configuration.
     */
    public function getOrCreateDefault(): AuditConfig
    {
        $config = $this->findDefault();

        if (!$config) {
            $config = new AuditConfig();
            $config->setName('default')
                ->setEnabled(true)
                ->setRetentionDays(365)
                ->setAsyncProcessing(true);

            $this->getEntityManager()->persist($config);
            $this->getEntityManager()->flush();
        }

        return $config;
    }

    /**
     * Update configuration settings.
     */
    public function updateSettings(AuditConfig $config, array $settings): void
    {
        foreach ($settings as $key => $value) {
            $config->setSetting($key, $value);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Get configuration statistics.
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('ac');

        return [
            'total' => $qb->select('COUNT(ac.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'enabled' => $qb->select('COUNT(ac.id)')
                ->where('ac.enabled = :enabled')
                ->setParameter('enabled', true)
                ->getQuery()
                ->getSingleScalarResult(),
            'disabled' => $qb->select('COUNT(ac.id)')
                ->where('ac.enabled = :enabled')
                ->setParameter('enabled', false)
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    public function save(AuditConfig $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditConfig $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
