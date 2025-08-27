<?php

namespace BenMacha\AuditBundle\Tests\Unit\Attribute;

use BenMacha\AuditBundle\Attribute\AuditSensitive;
use PHPUnit\Framework\TestCase;

class AuditSensitiveTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $auditSensitive = new AuditSensitive();

        $this->assertEquals('***', $auditSensitive->mask);
        $this->assertTrue($auditSensitive->hashValue);
        $this->assertEquals('sha256', $auditSensitive->hashAlgorithm);
        $this->assertFalse($auditSensitive->storeOriginal);
        $this->assertEquals([], $auditSensitive->allowedRoles);
        $this->assertNull($auditSensitive->reason);
    }

    public function testCustomConstructor(): void
    {
        $auditSensitive = new AuditSensitive(
            mask: '[HIDDEN]',
            hashValue: false,
            hashAlgorithm: 'md5',
            storeOriginal: true,
            allowedRoles: ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
            reason: 'Contains PII data'
        );

        $this->assertEquals('[HIDDEN]', $auditSensitive->mask);
        $this->assertFalse($auditSensitive->hashValue);
        $this->assertEquals('md5', $auditSensitive->hashAlgorithm);
        $this->assertTrue($auditSensitive->storeOriginal);
        $this->assertEquals(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], $auditSensitive->allowedRoles);
        $this->assertEquals('Contains PII data', $auditSensitive->reason);
    }

    public function testMaskValue(): void
    {
        $auditSensitive = new AuditSensitive(mask: '[REDACTED]');

        $this->assertEquals('[REDACTED]', $auditSensitive->maskValue('sensitive data'));
        $this->assertEquals('[REDACTED]', $auditSensitive->maskValue(''));
        $this->assertEquals('[REDACTED]', $auditSensitive->maskValue(null));
    }

    public function testHashValueMethod(): void
    {
        $auditSensitive = new AuditSensitive(hashValue: true, hashAlgorithm: 'sha256');

        $value = 'test value';
        $hashedValue = $auditSensitive->hashValueMethod($value);

        $this->assertEquals(hash('sha256', $value), $hashedValue);
        $this->assertNotEquals($value, $hashedValue);
    }

    public function testHashValueMethodWithDifferentAlgorithm(): void
    {
        $auditSensitive = new AuditSensitive(hashValue: true, hashAlgorithm: 'md5');

        $value = 'test value';
        $hashedValue = $auditSensitive->hashValueMethod($value);

        $this->assertEquals(hash('md5', $value), $hashedValue);
    }

    public function testCanViewOriginal(): void
    {
        $auditSensitive = new AuditSensitive(allowedRoles: ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);

        $this->assertTrue($auditSensitive->canViewOriginal(['ROLE_ADMIN']));
        $this->assertTrue($auditSensitive->canViewOriginal(['ROLE_SUPER_ADMIN']));
        $this->assertTrue($auditSensitive->canViewOriginal(['ROLE_USER', 'ROLE_ADMIN']));
        $this->assertFalse($auditSensitive->canViewOriginal(['ROLE_USER']));
        $this->assertFalse($auditSensitive->canViewOriginal([]));
    }

    public function testCanViewOriginalWithEmptyAllowedRoles(): void
    {
        $auditSensitive = new AuditSensitive(allowedRoles: []);

        $this->assertFalse($auditSensitive->canViewOriginal(['ROLE_ADMIN']));
        $this->assertFalse($auditSensitive->canViewOriginal(['ROLE_USER']));
        $this->assertFalse($auditSensitive->canViewOriginal([]));
    }

    public function testProcessValue(): void
    {
        $auditSensitive = new AuditSensitive(
            mask: '[HIDDEN]',
            hashValue: true,
            storeOriginal: true
        );

        $value = 'sensitive data';
        $userRoles = ['ROLE_USER'];

        $result = $auditSensitive->processValue($value, $userRoles);

        $expected = [
            'masked' => '[HIDDEN]',
            'hashed' => hash('sha256', $value),
            'original' => null, // Not stored because user doesn't have permission
        ];

        $this->assertEquals($expected, $result);
    }

    public function testProcessValueWithPermission(): void
    {
        $auditSensitive = new AuditSensitive(
            mask: '[HIDDEN]',
            hashValue: true,
            storeOriginal: true,
            allowedRoles: ['ROLE_ADMIN']
        );

        $value = 'sensitive data';
        $userRoles = ['ROLE_ADMIN'];

        $result = $auditSensitive->processValue($value, $userRoles);

        $expected = [
            'masked' => '[HIDDEN]',
            'hashed' => hash('sha256', $value),
            'original' => $value,
        ];

        $this->assertEquals($expected, $result);
    }

    public function testToArray(): void
    {
        $auditSensitive = new AuditSensitive(
            mask: '[TEST]',
            hashValue: false,
            hashAlgorithm: 'md5',
            storeOriginal: true,
            allowedRoles: ['ROLE_TEST'],
            reason: 'Test reason'
        );

        $expected = [
            'mask' => '[TEST]',
            'hashValue' => false,
            'hashAlgorithm' => 'md5',
            'storeOriginal' => true,
            'allowedRoles' => ['ROLE_TEST'],
            'reason' => 'Test reason',
        ];

        $this->assertEquals($expected, $auditSensitive->toArray());
    }

    public function testFromArray(): void
    {
        $config = [
            'mask' => '[FROM_ARRAY]',
            'hashValue' => false,
            'hashAlgorithm' => 'md5',
            'storeOriginal' => true,
            'allowedRoles' => ['ROLE_FROM_ARRAY'],
            'reason' => 'From array reason',
        ];

        $auditSensitive = AuditSensitive::fromArray($config);

        $this->assertEquals('[FROM_ARRAY]', $auditSensitive->mask);
        $this->assertFalse($auditSensitive->hashValue);
        $this->assertEquals('md5', $auditSensitive->hashAlgorithm);
        $this->assertTrue($auditSensitive->storeOriginal);
        $this->assertEquals(['ROLE_FROM_ARRAY'], $auditSensitive->allowedRoles);
        $this->assertEquals('From array reason', $auditSensitive->reason);
    }

    public function testFromArrayWithDefaults(): void
    {
        $auditSensitive = AuditSensitive::fromArray([]);

        $this->assertEquals('***', $auditSensitive->mask);
        $this->assertTrue($auditSensitive->hashValue);
        $this->assertEquals('sha256', $auditSensitive->hashAlgorithm);
        $this->assertFalse($auditSensitive->storeOriginal);
        $this->assertEquals([], $auditSensitive->allowedRoles);
        $this->assertNull($auditSensitive->reason);
    }
}
