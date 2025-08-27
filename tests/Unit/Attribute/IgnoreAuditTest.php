<?php

namespace BenMacha\AuditBundle\Tests\Unit\Attribute;

use BenMacha\AuditBundle\Attribute\IgnoreAudit;
use PHPUnit\Framework\TestCase;

class IgnoreAuditTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $ignoreAudit = new IgnoreAudit();

        $this->assertEquals(['create', 'update', 'delete'], $ignoreAudit->operations);
        $this->assertNull($ignoreAudit->reason);
        $this->assertTrue($ignoreAudit->always);
        $this->assertFalse($ignoreAudit->sensitive);
    }

    public function testCustomConstructor(): void
    {
        $ignoreAudit = new IgnoreAudit(
            operations: ['update', 'delete'],
            reason: 'Contains sensitive data',
            always: false,
            sensitive: true
        );

        $this->assertEquals(['update', 'delete'], $ignoreAudit->operations);
        $this->assertEquals('Contains sensitive data', $ignoreAudit->reason);
        $this->assertFalse($ignoreAudit->always);
        $this->assertTrue($ignoreAudit->sensitive);
    }

    public function testShouldIgnoreForOperationWhenAlways(): void
    {
        $ignoreAudit = new IgnoreAudit(always: true);

        $this->assertTrue($ignoreAudit->shouldIgnoreForOperation('create'));
        $this->assertTrue($ignoreAudit->shouldIgnoreForOperation('update'));
        $this->assertTrue($ignoreAudit->shouldIgnoreForOperation('delete'));
        $this->assertTrue($ignoreAudit->shouldIgnoreForOperation('custom'));
    }

    public function testShouldIgnoreForOperationWhenNotAlways(): void
    {
        $ignoreAudit = new IgnoreAudit(
            operations: ['update', 'delete'],
            always: false
        );

        $this->assertFalse($ignoreAudit->shouldIgnoreForOperation('create'));
        $this->assertTrue($ignoreAudit->shouldIgnoreForOperation('update'));
        $this->assertTrue($ignoreAudit->shouldIgnoreForOperation('delete'));
        $this->assertFalse($ignoreAudit->shouldIgnoreForOperation('custom'));
    }

    public function testToArray(): void
    {
        $ignoreAudit = new IgnoreAudit(
            operations: ['create'],
            reason: 'Test reason',
            always: false,
            sensitive: true
        );

        $expected = [
            'operations' => ['create'],
            'reason' => 'Test reason',
            'always' => false,
            'sensitive' => true,
        ];

        $this->assertEquals($expected, $ignoreAudit->toArray());
    }

    public function testFromArray(): void
    {
        $config = [
            'operations' => ['update'],
            'reason' => 'From array reason',
            'always' => false,
            'sensitive' => true,
        ];

        $ignoreAudit = IgnoreAudit::fromArray($config);

        $this->assertEquals(['update'], $ignoreAudit->operations);
        $this->assertEquals('From array reason', $ignoreAudit->reason);
        $this->assertFalse($ignoreAudit->always);
        $this->assertTrue($ignoreAudit->sensitive);
    }

    public function testFromArrayWithDefaults(): void
    {
        $ignoreAudit = IgnoreAudit::fromArray([]);

        $this->assertEquals(['create', 'update', 'delete'], $ignoreAudit->operations);
        $this->assertNull($ignoreAudit->reason);
        $this->assertTrue($ignoreAudit->always);
        $this->assertFalse($ignoreAudit->sensitive);
    }
}
