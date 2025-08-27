<?php

namespace BenMacha\AuditBundle\Tests\Unit\Service;

use BenMacha\AuditBundle\Attribute\Auditable;
use BenMacha\AuditBundle\Attribute\IgnoreAudit;
use BenMacha\AuditBundle\Service\ConfigurationService;
use Doctrine\Common\Annotations\Reader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase
{
    private ConfigurationService $configurationService;
    private Reader|MockObject $annotationReader;
    private array $config;

    protected function setUp(): void
    {
        $this->annotationReader = $this->createMock(Reader::class);
        $this->config = [
            'entities' => [
                'App\\Entity\\User' => [
                    'enabled' => true,
                    'operations' => ['create', 'update'],
                    'ignored_fields' => ['password'],
                ],
                'App\\Entity\\Product' => [
                    'enabled' => false,
                ],
            ],
            'global' => [
                'enabled' => true,
                'operations' => ['create', 'update', 'delete'],
                'ignored_fields' => [],
            ],
        ];

        $this->configurationService = new ConfigurationService(
            $this->annotationReader,
            $this->config
        );
    }

    public function testIsEntityAuditableWithConfiguredEntity(): void
    {
        $entity = new class {
            public $id = 1;
        };

        // Mock the class name to match our config
        $reflection = new \ReflectionClass($entity);
        $className = 'App\\Entity\\User';

        // We need to test with a real class name, so let's create a mock entity
        $userEntity = $this->createMockEntity('App\\Entity\\User');

        $result = $this->configurationService->isEntityAuditable($userEntity);

        $this->assertTrue($result);
    }

    public function testIsEntityAuditableWithDisabledEntity(): void
    {
        $productEntity = $this->createMockEntity('App\\Entity\\Product');

        $result = $this->configurationService->isEntityAuditable($productEntity);

        $this->assertFalse($result);
    }

    public function testIsEntityAuditableWithAttributeEnabled(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $auditableAttribute = new Auditable(enabled: true);

        $this->annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->willReturn($auditableAttribute);

        $result = $this->configurationService->isEntityAuditable($entity);

        $this->assertTrue($result);
    }

    public function testIsEntityAuditableWithAttributeDisabled(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $auditableAttribute = new Auditable(enabled: false);

        $this->annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->willReturn($auditableAttribute);

        $result = $this->configurationService->isEntityAuditable($entity);

        $this->assertFalse($result);
    }

    public function testIsEntityAuditableWithGlobalDefault(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $this->annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->willReturn(null);

        $result = $this->configurationService->isEntityAuditable($entity);

        $this->assertTrue($result); // Global config has enabled: true
    }

    public function testShouldTrackOperationWithConfiguredEntity(): void
    {
        $userEntity = $this->createMockEntity('App\\Entity\\User');

        $this->assertTrue($this->configurationService->shouldTrackOperation($userEntity, 'create'));
        $this->assertTrue($this->configurationService->shouldTrackOperation($userEntity, 'update'));
        $this->assertFalse($this->configurationService->shouldTrackOperation($userEntity, 'delete'));
    }

    public function testShouldTrackOperationWithAttribute(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $auditableAttribute = new Auditable(operations: ['create', 'delete']);

        $this->annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->willReturn($auditableAttribute);

        $this->assertTrue($this->configurationService->shouldTrackOperation($entity, 'create'));
        $this->assertFalse($this->configurationService->shouldTrackOperation($entity, 'update'));
        $this->assertTrue($this->configurationService->shouldTrackOperation($entity, 'delete'));
    }

    public function testGetIgnoredFieldsWithConfiguredEntity(): void
    {
        $userEntity = $this->createMockEntity('App\\Entity\\User');

        $result = $this->configurationService->getIgnoredFields($userEntity);

        $this->assertEquals(['password'], $result);
    }

    public function testGetIgnoredFieldsWithAttribute(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $auditableAttribute = new Auditable(ignoredFields: ['secret', 'token']);

        $this->annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->willReturn($auditableAttribute);

        $result = $this->configurationService->getIgnoredFields($entity);

        $this->assertEquals(['secret', 'token'], $result);
    }

    public function testGetIgnoredFieldsWithPropertyAttribute(): void
    {
        $entity = new class {
            public $id = 1;
            public $password = 'secret';
            public $email = 'test@example.com';
        };

        $ignoreAuditAttribute = new IgnoreAudit();

        $this->annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->willReturn(null);

        $this->annotationReader->expects($this->exactly(3))
            ->method('getPropertyAnnotation')
            ->willReturnMap([
                [new \ReflectionProperty($entity, 'id'), IgnoreAudit::class, null],
                [new \ReflectionProperty($entity, 'password'), IgnoreAudit::class, $ignoreAuditAttribute],
                [new \ReflectionProperty($entity, 'email'), IgnoreAudit::class, null],
            ]);

        $result = $this->configurationService->getIgnoredFields($entity);

        $this->assertContains('password', $result);
        $this->assertNotContains('id', $result);
        $this->assertNotContains('email', $result);
    }

    public function testGetEntityConfigurationWithExistingConfig(): void
    {
        $userEntity = $this->createMockEntity('App\\Entity\\User');

        $result = $this->configurationService->getEntityConfiguration($userEntity);

        $expected = [
            'enabled' => true,
            'operations' => ['create', 'update'],
            'ignored_fields' => ['password'],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetEntityConfigurationWithGlobalDefault(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $this->annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->willReturn(null);

        $result = $this->configurationService->getEntityConfiguration($entity);

        $expected = [
            'enabled' => true,
            'operations' => ['create', 'update', 'delete'],
            'ignored_fields' => [],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetEntityConfigurationWithAttribute(): void
    {
        $entity = new class {
            public $id = 1;
        };

        $auditableAttribute = new Auditable(
            enabled: true,
            operations: ['create'],
            ignoredFields: ['secret']
        );

        $this->annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->willReturn($auditableAttribute);

        $result = $this->configurationService->getEntityConfiguration($entity);

        $this->assertTrue($result['enabled']);
        $this->assertEquals(['create'], $result['operations']);
        $this->assertEquals(['secret'], $result['ignored_fields']);
    }

    public function testIsFieldIgnoredWithIgnoreAuditAttribute(): void
    {
        $entity = new class {
            public $password = 'secret';
        };

        $ignoreAuditAttribute = new IgnoreAudit();

        $this->annotationReader->expects($this->once())
            ->method('getPropertyAnnotation')
            ->willReturn($ignoreAuditAttribute);

        $result = $this->configurationService->isFieldIgnored($entity, 'password', 'update');

        $this->assertTrue($result);
    }

    public function testIsFieldIgnoredWithSpecificOperation(): void
    {
        $entity = new class {
            public $field = 'value';
        };

        $ignoreAuditAttribute = new IgnoreAudit(
            operations: ['delete'],
            always: false
        );

        $this->annotationReader->expects($this->exactly(2))
            ->method('getPropertyAnnotation')
            ->willReturn($ignoreAuditAttribute);

        $this->assertFalse($this->configurationService->isFieldIgnored($entity, 'field', 'update'));
        $this->assertTrue($this->configurationService->isFieldIgnored($entity, 'field', 'delete'));
    }

    private function createMockEntity(string $className): object
    {
        return new class($className) {
            private string $className;

            public function __construct(string $className)
            {
                $this->className = $className;
            }

            public function __toString(): string
            {
                return $this->className;
            }
        };
    }
}
