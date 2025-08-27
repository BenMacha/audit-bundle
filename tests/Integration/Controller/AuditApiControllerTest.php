<?php

namespace BenMacha\AuditBundle\Tests\Integration\Controller;

use BenMacha\AuditBundle\Controller\Api\AuditLogApiController;
use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Service\AuditManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuditApiControllerTest extends TestCase
{
    private AuditLogApiController $controller;
    private AuditManager|MockObject $auditManager;
    private EntityManagerInterface|MockObject $entityManager;
    private AuditLogRepository|MockObject $auditLogRepository;
    private SerializerInterface|MockObject $serializer;
    private ValidatorInterface|MockObject $validator;
    private Security|MockObject $security;

    protected function setUp(): void
    {
        $this->auditManager = $this->createMock(AuditManager::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->auditLogRepository = $this->createMock(AuditLogRepository::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->controller = new AuditLogApiController(
            $this->entityManager,
            $this->auditLogRepository,
            $this->auditManager,
            $this->serializer,
            $this->validator
        );
    }

    public function testGetAuditLogsWithDefaultParameters(): void
    {
        $request = new Request();

        $auditLogs = [
            $this->createAuditLog(1, 'App\\Entity\\User', '1', 'create'),
            $this->createAuditLog(2, 'App\\Entity\\Product', '2', 'update'),
        ];

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AuditLog::class)
            ->willReturn($this->createRepositoryMock($auditLogs, 2));

        $response = $this->controller->getAuditLogs($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals(1, $responseData['pagination']['current_page']);
        $this->assertEquals(1, $responseData['pagination']['total_pages']);
        $this->assertEquals(2, $responseData['pagination']['total_items']);
        $this->assertEquals(20, $responseData['pagination']['items_per_page']);
    }

    public function testGetAuditLogsWithCustomParameters(): void
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

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AuditLog::class)
            ->willReturn($this->createRepositoryMock($auditLogs, 15, 10, 10));

        $response = $this->controller->getAuditLogs($request);

        $responseData = json_decode($response->getContent(), true);
        $this->assertCount(1, $responseData['data']);
        $this->assertEquals(2, $responseData['pagination']['current_page']);
        $this->assertEquals(2, $responseData['pagination']['total_pages']);
        $this->assertEquals(15, $responseData['pagination']['total_items']);
        $this->assertEquals(10, $responseData['pagination']['items_per_page']);
    }

    public function testGetAuditLogsWithInvalidPage(): void
    {
        $request = new Request(['page' => 'invalid']);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AuditLog::class)
            ->willReturn($this->createRepositoryMock([], 0));

        $response = $this->controller->getAuditLogs($request);

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals(1, $responseData['pagination']['current_page']);
    }

    public function testGetAuditLogsWithInvalidLimit(): void
    {
        $request = new Request(['limit' => 'invalid']);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AuditLog::class)
            ->willReturn($this->createRepositoryMock([], 0));

        $response = $this->controller->getAuditLogs($request);

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals(20, $responseData['pagination']['items_per_page']);
    }

    public function testGetAuditLogById(): void
    {
        $auditLog = $this->createAuditLog(1, 'App\\Entity\\User', '1', 'create');

        $repository = $this->createRepositoryMock([$auditLog]);
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($auditLog);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $response = $this->controller->getAuditLog(1);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals(1, $responseData['id']);
        $this->assertEquals('App\\Entity\\User', $responseData['entity_class']);
        $this->assertEquals('1', $responseData['entity_id']);
        $this->assertEquals('create', $responseData['operation']);
    }

    public function testGetAuditLogByIdNotFound(): void
    {
        $repository = $this->createRepositoryMock([]);
        $repository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $response = $this->controller->getAuditLog(999);

        $this->assertEquals(404, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Audit log not found', $responseData['error']);
    }

    public function testGetEntityAuditLogs(): void
    {
        $request = new Request();
        $entityClass = 'App\\Entity\\User';
        $entityId = '123';

        $auditLogs = [
            $this->createAuditLog(1, $entityClass, $entityId, 'create'),
            $this->createAuditLog(2, $entityClass, $entityId, 'update'),
        ];

        $repository = $this->createRepositoryMock($auditLogs);
        $repository->expects($this->once())
            ->method('findByEntity')
            ->with($entityClass, $entityId, null, null)
            ->willReturn($auditLogs);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $response = $this->controller->getEntityAuditLogs($entityClass, $entityId, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertCount(2, $responseData);
        $this->assertEquals($entityClass, $responseData[0]['entity_class']);
        $this->assertEquals($entityId, $responseData[0]['entity_id']);
    }

    public function testGetEntityAuditLogsWithLimitAndOffset(): void
    {
        $request = new Request(['limit' => '5', 'offset' => '10']);
        $entityClass = 'App\\Entity\\Product';
        $entityId = '456';

        $auditLogs = [
            $this->createAuditLog(11, $entityClass, $entityId, 'update'),
        ];

        $repository = $this->createRepositoryMock($auditLogs);
        $repository->expects($this->once())
            ->method('findByEntity')
            ->with($entityClass, $entityId, 5, 10)
            ->willReturn($auditLogs);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $response = $this->controller->getEntityAuditLogs($entityClass, $entityId, $request);

        $responseData = json_decode($response->getContent(), true);
        $this->assertCount(1, $responseData);
    }

    public function testRollbackEntitySuccessful(): void
    {
        $auditLog = $this->createAuditLog(1, 'App\\Entity\\User', '1', 'update');

        $repository = $this->createRepositoryMock([$auditLog]);
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($auditLog);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $this->auditManager->expects($this->once())
            ->method('rollbackEntity')
            ->with($auditLog)
            ->willReturn(true);

        $response = $this->controller->rollbackEntity(1);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Entity rolled back successfully', $responseData['message']);
    }

    public function testRollbackEntityFailed(): void
    {
        $auditLog = $this->createAuditLog(1, 'App\\Entity\\User', '1', 'create');

        $repository = $this->createRepositoryMock([$auditLog]);
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($auditLog);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $this->auditManager->expects($this->once())
            ->method('rollbackEntity')
            ->with($auditLog)
            ->willReturn(false);

        $response = $this->controller->rollbackEntity(1);

        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Failed to rollback entity', $responseData['message']);
    }

    public function testRollbackEntityNotFound(): void
    {
        $repository = $this->createRepositoryMock([]);
        $repository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $response = $this->controller->rollbackEntity(999);

        $this->assertEquals(404, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Audit log not found', $responseData['error']);
    }

    public function testRollbackEntityWithException(): void
    {
        $auditLog = $this->createAuditLog(1, 'App\\Entity\\User', '1', 'update');

        $repository = $this->createRepositoryMock([$auditLog]);
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($auditLog);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $this->auditManager->expects($this->once())
            ->method('rollbackEntity')
            ->with($auditLog)
            ->willThrowException(new \Exception('Database error'));

        $response = $this->controller->rollbackEntity(1);

        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('An error occurred during rollback', $responseData['message']);
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
