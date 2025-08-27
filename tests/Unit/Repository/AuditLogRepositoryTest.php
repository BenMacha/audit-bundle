<?php

namespace BenMacha\AuditBundle\Tests\Unit\Repository;

use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuditLogRepositoryTest extends TestCase
{
    private AuditLogRepository $repository;
    private EntityManagerInterface|MockObject $entityManager;
    private QueryBuilder|MockObject $queryBuilder;
    private Query|MockObject $query;
    private ClassMetadata|MockObject $classMetadata;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);
        $this->classMetadata = $this->createMock(ClassMetadata::class);

        $this->classMetadata->name = AuditLog::class;

        $this->repository = new AuditLogRepository($this->entityManager, $this->classMetadata);
    }

    public function testFindByEntityWithDefaults(): void
    {
        $entityClass = 'App\Entity\User';
        $entityId = '123';

        $this->setupQueryBuilderMock();

        $this->queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->withConsecutive(
                ['a.entityClass = :entityClass'],
                ['a.entityId = :entityId']
            )
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->withConsecutive(
                ['entityClass', $entityClass],
                ['entityId', $entityId]
            )
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('a.createdAt', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->never())
            ->method('setMaxResults');

        $this->queryBuilder->expects($this->never())
            ->method('setFirstResult');

        $expectedResults = [new AuditLog(), new AuditLog()];
        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedResults);

        $result = $this->repository->findByEntity($entityClass, $entityId);

        $this->assertEquals($expectedResults, $result);
    }

    public function testFindByEntityWithLimitAndOffset(): void
    {
        $entityClass = 'App\Entity\Product';
        $entityId = '456';
        $limit = 10;
        $offset = 20;

        $this->setupQueryBuilderMock();

        $this->queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->withConsecutive(
                ['a.entityClass = :entityClass'],
                ['a.entityId = :entityId']
            )
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->withConsecutive(
                ['entityClass', $entityClass],
                ['entityId', $entityId]
            )
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('a.createdAt', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with($limit)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setFirstResult')
            ->with($offset)
            ->willReturnSelf();

        $expectedResults = [new AuditLog()];
        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedResults);

        $result = $this->repository->findByEntity($entityClass, $entityId, $limit, $offset);

        $this->assertEquals($expectedResults, $result);
    }

    public function testFindByEntityWithZeroLimit(): void
    {
        $entityClass = 'App\Entity\Order';
        $entityId = '789';
        $limit = 0;

        $this->setupQueryBuilderMock();

        $this->queryBuilder->expects($this->never())
            ->method('setMaxResults');

        $expectedResults = [];
        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedResults);

        $result = $this->repository->findByEntity($entityClass, $entityId, $limit);

        $this->assertEquals($expectedResults, $result);
    }

    public function testFindByEntityWithNullLimit(): void
    {
        $entityClass = 'App\Entity\Category';
        $entityId = '101';

        $this->setupQueryBuilderMock();

        $this->queryBuilder->expects($this->never())
            ->method('setMaxResults');

        $expectedResults = [new AuditLog(), new AuditLog(), new AuditLog()];
        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedResults);

        $result = $this->repository->findByEntity($entityClass, $entityId, null);

        $this->assertEquals($expectedResults, $result);
    }

    public function testFindByEntityWithStringEntityId(): void
    {
        $entityClass = 'App\Entity\User';
        $entityId = 'uuid-string-123';

        $this->setupQueryBuilderMock();

        $this->queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->withConsecutive(
                ['entityClass', $entityClass],
                ['entityId', $entityId]
            )
            ->willReturnSelf();

        $expectedResults = [new AuditLog()];
        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedResults);

        $result = $this->repository->findByEntity($entityClass, $entityId);

        $this->assertEquals($expectedResults, $result);
    }

    public function testFindByEntityWithEmptyResult(): void
    {
        $entityClass = 'App\Entity\NonExistent';
        $entityId = '999';

        $this->setupQueryBuilderMock();

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn([]);

        $result = $this->repository->findByEntity($entityClass, $entityId);

        $this->assertEquals([], $result);
    }

    public function testFindByEntityWithLargeOffset(): void
    {
        $entityClass = 'App\Entity\Comment';
        $entityId = '555';
        $limit = 5;
        $offset = 1000;

        $this->setupQueryBuilderMock();

        $this->queryBuilder->expects($this->once())
            ->method('setFirstResult')
            ->with($offset)
            ->willReturnSelf();

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn([]);

        $result = $this->repository->findByEntity($entityClass, $entityId, $limit, $offset);

        $this->assertEquals([], $result);
    }

    private function setupQueryBuilderMock(): void
    {
        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('a')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('from')
            ->with(AuditLog::class, 'a')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
    }
}
