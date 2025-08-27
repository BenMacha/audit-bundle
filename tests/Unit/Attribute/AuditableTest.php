<?php

namespace BenMacha\AuditBundle\Tests\Unit\Attribute;

use BenMacha\AuditBundle\Attribute\Auditable;
use PHPUnit\Framework\TestCase;

class AuditableTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $auditable = new Auditable();

        $this->assertEquals(['create', 'update', 'delete'], $auditable->operations);
        $this->assertEquals([], $auditable->ignoredFields);
        $this->assertTrue($auditable->enabled);
        $this->assertTrue($auditable->trackChanges);
        $this->assertNull($auditable->maxLogs);
        $this->assertEquals([], $auditable->sensitiveFields);
        $this->assertNull($auditable->auditTable);
        $this->assertFalse($auditable->async);
        $this->assertEquals([], $auditable->metadata);
    }

    public function testCustomConstructor(): void
    {
        $auditable = new Auditable(
            operations: ['create', 'update'],
            ignoredFields: ['password', 'token'],
            enabled: false,
            trackChanges: false,
            maxLogs: 100,
            sensitiveFields: ['email', 'phone'],
            auditTable: 'custom_audit',
            async: true,
            metadata: ['version' => '1.0']
        );

        $this->assertEquals(['create', 'update'], $auditable->operations);
        $this->assertEquals(['password', 'token'], $auditable->ignoredFields);
        $this->assertFalse($auditable->enabled);
        $this->assertFalse($auditable->trackChanges);
        $this->assertEquals(100, $auditable->maxLogs);
        $this->assertEquals(['email', 'phone'], $auditable->sensitiveFields);
        $this->assertEquals('custom_audit', $auditable->auditTable);
        $this->assertTrue($auditable->async);
        $this->assertEquals(['version' => '1.0'], $auditable->metadata);
    }

    public function testShouldTrackOperation(): void
    {
        $auditable = new Auditable(operations: ['create', 'update']);

        $this->assertTrue($auditable->shouldTrackOperation('create'));
        $this->assertTrue($auditable->shouldTrackOperation('update'));
        $this->assertFalse($auditable->shouldTrackOperation('delete'));
    }

    public function testShouldTrackOperationWhenDisabled(): void
    {
        $auditable = new Auditable(enabled: false);

        $this->assertFalse($auditable->shouldTrackOperation('create'));
        $this->assertFalse($auditable->shouldTrackOperation('update'));
        $this->assertFalse($auditable->shouldTrackOperation('delete'));
    }

    public function testShouldIgnoreField(): void
    {
        $auditable = new Auditable(ignoredFields: ['password', 'token']);

        $this->assertTrue($auditable->shouldIgnoreField('password'));
        $this->assertTrue($auditable->shouldIgnoreField('token'));
        $this->assertFalse($auditable->shouldIgnoreField('email'));
    }

    public function testIsSensitiveField(): void
    {
        $auditable = new Auditable(sensitiveFields: ['email', 'phone']);

        $this->assertTrue($auditable->isSensitiveField('email'));
        $this->assertTrue($auditable->isSensitiveField('phone'));
        $this->assertFalse($auditable->isSensitiveField('name'));
    }

    public function testToArray(): void
    {
        $auditable = new Auditable(
            operations: ['create'],
            ignoredFields: ['password'],
            enabled: false,
            trackChanges: false,
            maxLogs: 50,
            sensitiveFields: ['email'],
            auditTable: 'test_audit',
            async: true,
            metadata: ['test' => 'value']
        );

        $expected = [
            'operations' => ['create'],
            'ignoredFields' => ['password'],
            'enabled' => false,
            'trackChanges' => false,
            'maxLogs' => 50,
            'sensitiveFields' => ['email'],
            'auditTable' => 'test_audit',
            'async' => true,
            'metadata' => ['test' => 'value'],
        ];

        $this->assertEquals($expected, $auditable->toArray());
    }

    public function testFromArray(): void
    {
        $config = [
            'operations' => ['update'],
            'ignoredFields' => ['secret'],
            'enabled' => false,
            'trackChanges' => false,
            'maxLogs' => 25,
            'sensitiveFields' => ['password'],
            'auditTable' => 'from_array_audit',
            'async' => true,
            'metadata' => ['source' => 'array'],
        ];

        $auditable = Auditable::fromArray($config);

        $this->assertEquals(['update'], $auditable->operations);
        $this->assertEquals(['secret'], $auditable->ignoredFields);
        $this->assertFalse($auditable->enabled);
        $this->assertFalse($auditable->trackChanges);
        $this->assertEquals(25, $auditable->maxLogs);
        $this->assertEquals(['password'], $auditable->sensitiveFields);
        $this->assertEquals('from_array_audit', $auditable->auditTable);
        $this->assertTrue($auditable->async);
        $this->assertEquals(['source' => 'array'], $auditable->metadata);
    }

    public function testFromArrayWithDefaults(): void
    {
        $auditable = Auditable::fromArray([]);

        $this->assertEquals(['create', 'update', 'delete'], $auditable->operations);
        $this->assertEquals([], $auditable->ignoredFields);
        $this->assertTrue($auditable->enabled);
        $this->assertTrue($auditable->trackChanges);
        $this->assertNull($auditable->maxLogs);
        $this->assertEquals([], $auditable->sensitiveFields);
        $this->assertNull($auditable->auditTable);
        $this->assertFalse($auditable->async);
        $this->assertEquals([], $auditable->metadata);
    }
}
