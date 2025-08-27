<?php

namespace BenMacha\AuditBundle\Tests\Integration\EventListener;

use BenMacha\AuditBundle\EventListener\AuditEventListener;
use BenMacha\AuditBundle\Service\AuditManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuditEventListenerTest extends TestCase
{
    private AuditEventListener $auditEventListener;
    private AuditManager|MockObject $auditManager;
    private EntityManagerInterface|MockObject $entityManager;
    private UnitOfWork|MockObject $unitOfWork;

    protected function setUp(): void
    {
        $this->auditManager = $this->createMock(AuditManager::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->unitOfWork = $this->createMock(UnitOfWork::class);

        $this->entityManager->method('getUnitOfWork')->willReturn($this->unitOfWork);

        $this->auditEventListener = new AuditEventListener($this->auditManager, []);
    }

    public function testPostPersist(): void
    {
        $entity = new class {
            public $id = 1;
            public $name = 'Test Entity';
        };

        $args = new LifecycleEventArgs($entity, $this->entityManager);

        $this->auditManager->expects($this->once())
            ->method('logEntityChange')
            ->with(
                $entity,
                'create',
                []
            );

        $this->auditEventListener->postPersist($args);
    }

    public function testPreUpdate(): void
    {
        $entity = new class {
            public $id = 1;
            public $name = 'Updated Name';
            public $email = 'updated@test.com';
        };

        $changeSet = [
            'name' => ['Old Name', 'Updated Name'],
            'email' => ['old@test.com', 'updated@test.com'],
        ];

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getEntity')->willReturn($entity);
        $args->method('getEntityChangeSet')->willReturn($changeSet);

        $this->auditManager->expects($this->once())
            ->method('logEntityChange')
            ->with(
                $entity,
                'update',
                $changeSet
            );

        $this->auditEventListener->preUpdate($args);
    }

    public function testPreRemove(): void
    {
        $entity = new class {
            public $id = 1;
            public $name = 'To Be Deleted';
        };

        $args = new LifecycleEventArgs($entity, $this->entityManager);

        $this->auditManager->expects($this->once())
            ->method('logEntityChange')
            ->with(
                $entity,
                'delete',
                []
            );

        $this->auditEventListener->preRemove($args);
    }

    public function testPostPersistWithException(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $args = new LifecycleEventArgs($entity, $this->entityManager);

        $this->auditManager->expects($this->once())
            ->method('logEntityChange')
            ->willThrowException(new \Exception('Audit logging failed'));

        // The listener should not throw exceptions, it should handle them gracefully
        $this->expectNotToPerformAssertions();

        $this->auditEventListener->postPersist($args);
    }

    public function testPreUpdateWithException(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getEntity')->willReturn($entity);
        $args->method('getEntityChangeSet')->willReturn([]);

        $this->auditManager->expects($this->once())
            ->method('logEntityChange')
            ->willThrowException(new \Exception('Audit logging failed'));

        // The listener should not throw exceptions, it should handle them gracefully
        $this->expectNotToPerformAssertions();

        $this->auditEventListener->preUpdate($args);
    }

    public function testPreRemoveWithException(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $args = new LifecycleEventArgs($entity, $this->entityManager);

        $this->auditManager->expects($this->once())
            ->method('logEntityChange')
            ->willThrowException(new \Exception('Audit logging failed'));

        // The listener should not throw exceptions, it should handle them gracefully
        $this->expectNotToPerformAssertions();

        $this->auditEventListener->preRemove($args);
    }

    public function testGetSubscribedEvents(): void
    {
        $subscribedEvents = AuditEventListener::getSubscribedEvents();

        $expectedEvents = [
            'postPersist',
            'preUpdate',
            'preRemove',
        ];

        $this->assertEquals($expectedEvents, $subscribedEvents);
    }

    public function testPostPersistWithComplexEntity(): void
    {
        $entity = new class {
            public $id = 1;
            public $name = 'Complex Entity';
            public $metadata = ['key' => 'value'];
            public $createdAt;

            public function __construct()
            {
                $this->createdAt = new \DateTime();
            }
        };

        $args = new LifecycleEventArgs($entity, $this->entityManager);

        $this->auditManager->expects($this->once())
            ->method('logEntityChange')
            ->with(
                $this->identicalTo($entity),
                'create',
                []
            );

        $this->auditEventListener->postPersist($args);
    }

    public function testPreUpdateWithEmptyChangeSet(): void
    {
        $entity = new class {
            public $id = 1;
            public $name = 'Unchanged Entity';
        };

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getEntity')->willReturn($entity);
        $args->method('getEntityChangeSet')->willReturn([]);

        $this->auditManager->expects($this->once())
            ->method('logEntityChange')
            ->with(
                $entity,
                'update',
                []
            );

        $this->auditEventListener->preUpdate($args);
    }

    public function testPreUpdateWithSingleFieldChange(): void
    {
        $entity = new class {
            public $id = 1;
            public $status = 'active';
        };

        $changeSet = [
            'status' => ['inactive', 'active'],
        ];

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getEntity')->willReturn($entity);
        $args->method('getEntityChangeSet')->willReturn($changeSet);

        $this->auditManager->expects($this->once())
            ->method('logEntityChange')
            ->with(
                $entity,
                'update',
                $changeSet
            );

        $this->auditEventListener->preUpdate($args);
    }
}
