<?php

namespace BenMacha\AuditBundle\Tests\Integration\Controller;

use BenMacha\AuditBundle\Controller\AuditLogController;
use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Repository\AuditChangeRepository;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Repository\EntityConfigRepository;
use BenMacha\AuditBundle\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditControllerTest extends TestCase
{
    private AuditLogController $controller;
    private AuditLogRepository|MockObject $auditLogRepository;
    private AuditChangeRepository|MockObject $auditChangeRepository;
    private EntityConfigRepository|MockObject $entityConfigRepository;
    private ConfigurationService|MockObject $configurationService;
    private EntityManagerInterface|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->auditLogRepository = $this->createMock(AuditLogRepository::class);
        $this->auditChangeRepository = $this->createMock(AuditChangeRepository::class);
        $this->entityConfigRepository = $this->createMock(EntityConfigRepository::class);
        $this->configurationService = $this->createMock(ConfigurationService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->controller = new AuditLogController(
            $this->auditLogRepository,
            $this->auditChangeRepository,
            $this->entityConfigRepository,
            $this->configurationService,
            $this->entityManager
        );
    }

    public function testIndexWithDefaultParameters(): void
    {
        $request = new Request();

        $auditLogs = [
            $this->createAuditLog(1, 'App\\Entity\\User', '1', 'create'),
            $this->createAuditLog(2, 'App\\Entity\\Product', '2', 'update'),
        ];

        $this->auditLogRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock($auditLogs, 2));

        $response = $this->controller->index($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexWithCustomParameters(): void
    {
        $request = new Request([
            'page' => '2',
            'limit' => '10',
            'entity_class' => 'App\\Entity\\User',
            'operation' => 'update',
        ]);

        $auditLogs = [
            $this->createAuditLog(3, 'App\\Entity\\User', '3', 'update'),
        ];

        $this->auditLogRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock($auditLogs, 15, 10, 10));

        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowExistingAuditLog(): void
    {
        $auditLog = $this->createAuditLog(1, 'App\\Entity\\User', '1', 'create');

        $this->auditLogRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($auditLog);

        $response = $this->controller->show(1);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowNonExistentAuditLog(): void
    {
        $this->auditLogRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->controller->show(999);
    }

    private function createAuditLog(int $id, string $entityClass, string $entityId, string $operation): AuditLog
    {
        $auditLog = new AuditLog();
        $auditLog->setEntityClass($entityClass);
        $auditLog->setEntityId($entityId);
        $auditLog->setOperation($operation);
        $auditLog->setChanges(['field' => ['old', 'new']]);
        $auditLog->setCreatedAt(new \DateTime());
        $auditLog->setIpAddress('127.0.0.1');

        // Use reflection to set the ID
        $reflection = new \ReflectionClass($auditLog);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($auditLog, $id);

        return $auditLog;
    }

    private function createRepositoryMock(array $auditLogs, ?int $totalCount = null, ?int $limit = null, ?int $offset = null): MockObject
    {
        $repository = $this->createMock(AuditLogRepository::class);

        if (null !== $totalCount) {
            $repository->expects($this->once())
                ->method('createQueryBuilder')
                ->willReturn($this->createQueryBuilderMock($auditLogs, $totalCount, $limit, $offset));
        }

        return $repository;
    }

    private function createQueryBuilderMock(array $auditLogs, int $totalCount, ?int $limit = null, ?int $offset = null): MockObject
    {
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $countQuery = $this->createMock(\Doctrine\ORM\Query::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('setFirstResult')->willReturnSelf();

        $queryBuilder->expects($this->exactly(2))
            ->method('getQuery')
            ->willReturnOnConsecutiveCalls($query, $countQuery);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($auditLogs);

        $countQuery->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn($totalCount);

        return $queryBuilder;
    }
}
