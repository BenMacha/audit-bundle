<?php

namespace BenMacha\AuditBundle\Tests\Unit\Attribute;

use BenMacha\AuditBundle\Attribute\AuditMetadata;
use PHPUnit\Framework\TestCase;

class AuditMetadataTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $auditMetadata = new AuditMetadata();

        $this->assertNull($auditMetadata->key);
        $this->assertNull($auditMetadata->value);
        $this->assertNull($auditMetadata->type);
        $this->assertFalse($auditMetadata->encrypted);
        $this->assertFalse($auditMetadata->indexed);
        $this->assertNull($auditMetadata->description);
        $this->assertEquals([], $auditMetadata->tags);
        $this->assertNull($auditMetadata->source);
        $this->assertNull($auditMetadata->expiresAt);
    }

    public function testCustomConstructor(): void
    {
        $expiresAt = new \DateTime('2024-12-31');

        $auditMetadata = new AuditMetadata(
            key: 'user_session',
            value: 'abc123',
            type: 'session_id',
            encrypted: true,
            indexed: true,
            description: 'User session identifier',
            tags: ['session', 'security'],
            source: 'authentication_service',
            expiresAt: $expiresAt
        );

        $this->assertEquals('user_session', $auditMetadata->key);
        $this->assertEquals('abc123', $auditMetadata->value);
        $this->assertEquals('session_id', $auditMetadata->type);
        $this->assertTrue($auditMetadata->encrypted);
        $this->assertTrue($auditMetadata->indexed);
        $this->assertEquals('User session identifier', $auditMetadata->description);
        $this->assertEquals(['session', 'security'], $auditMetadata->tags);
        $this->assertEquals('authentication_service', $auditMetadata->source);
        $this->assertEquals($expiresAt, $auditMetadata->expiresAt);
    }

    public function testIsExpired(): void
    {
        $pastDate = new \DateTime('-1 day');
        $futureDate = new \DateTime('+1 day');

        $expiredMetadata = new AuditMetadata(expiresAt: $pastDate);
        $validMetadata = new AuditMetadata(expiresAt: $futureDate);
        $neverExpiresMetadata = new AuditMetadata();

        $this->assertTrue($expiredMetadata->isExpired());
        $this->assertFalse($validMetadata->isExpired());
        $this->assertFalse($neverExpiresMetadata->isExpired());
    }

    public function testHasTag(): void
    {
        $auditMetadata = new AuditMetadata(tags: ['security', 'session', 'user']);

        $this->assertTrue($auditMetadata->hasTag('security'));
        $this->assertTrue($auditMetadata->hasTag('session'));
        $this->assertTrue($auditMetadata->hasTag('user'));
        $this->assertFalse($auditMetadata->hasTag('admin'));
        $this->assertFalse($auditMetadata->hasTag(''));
    }

    public function testHasTagWithEmptyTags(): void
    {
        $auditMetadata = new AuditMetadata(tags: []);

        $this->assertFalse($auditMetadata->hasTag('security'));
        $this->assertFalse($auditMetadata->hasTag('any_tag'));
    }

    public function testAddTag(): void
    {
        $auditMetadata = new AuditMetadata(tags: ['existing']);

        $auditMetadata->addTag('new_tag');

        $this->assertTrue($auditMetadata->hasTag('existing'));
        $this->assertTrue($auditMetadata->hasTag('new_tag'));
        $this->assertEquals(['existing', 'new_tag'], $auditMetadata->tags);
    }

    public function testAddTagDuplicate(): void
    {
        $auditMetadata = new AuditMetadata(tags: ['existing']);

        $auditMetadata->addTag('existing');

        $this->assertEquals(['existing'], $auditMetadata->tags);
    }

    public function testRemoveTag(): void
    {
        $auditMetadata = new AuditMetadata(tags: ['tag1', 'tag2', 'tag3']);

        $auditMetadata->removeTag('tag2');

        $this->assertTrue($auditMetadata->hasTag('tag1'));
        $this->assertFalse($auditMetadata->hasTag('tag2'));
        $this->assertTrue($auditMetadata->hasTag('tag3'));
        $this->assertEquals(['tag1', 'tag3'], array_values($auditMetadata->tags));
    }

    public function testRemoveNonExistentTag(): void
    {
        $auditMetadata = new AuditMetadata(tags: ['tag1', 'tag2']);

        $auditMetadata->removeTag('non_existent');

        $this->assertEquals(['tag1', 'tag2'], $auditMetadata->tags);
    }

    public function testGetFormattedValue(): void
    {
        $stringMetadata = new AuditMetadata(value: 'test_string', type: 'string');
        $intMetadata = new AuditMetadata(value: '123', type: 'integer');
        $boolMetadata = new AuditMetadata(value: 'true', type: 'boolean');
        $jsonMetadata = new AuditMetadata(value: '{"key": "value"}', type: 'json');
        $defaultMetadata = new AuditMetadata(value: 'default_value');

        $this->assertEquals('test_string', $stringMetadata->getFormattedValue());
        $this->assertEquals(123, $intMetadata->getFormattedValue());
        $this->assertTrue($boolMetadata->getFormattedValue());
        $this->assertEquals(['key' => 'value'], $jsonMetadata->getFormattedValue());
        $this->assertEquals('default_value', $defaultMetadata->getFormattedValue());
    }

    public function testGetFormattedValueWithInvalidJson(): void
    {
        $invalidJsonMetadata = new AuditMetadata(value: 'invalid json', type: 'json');

        $this->assertEquals('invalid json', $invalidJsonMetadata->getFormattedValue());
    }

    public function testGetFormattedValueWithBooleanFalse(): void
    {
        $boolMetadata = new AuditMetadata(value: 'false', type: 'boolean');

        $this->assertFalse($boolMetadata->getFormattedValue());
    }

    public function testShouldBeIndexed(): void
    {
        $indexedMetadata = new AuditMetadata(indexed: true);
        $nonIndexedMetadata = new AuditMetadata(indexed: false);

        $this->assertTrue($indexedMetadata->shouldBeIndexed());
        $this->assertFalse($nonIndexedMetadata->shouldBeIndexed());
    }

    public function testShouldBeEncrypted(): void
    {
        $encryptedMetadata = new AuditMetadata(encrypted: true);
        $nonEncryptedMetadata = new AuditMetadata(encrypted: false);

        $this->assertTrue($encryptedMetadata->shouldBeEncrypted());
        $this->assertFalse($nonEncryptedMetadata->shouldBeEncrypted());
    }

    public function testToArray(): void
    {
        $expiresAt = new \DateTime('2024-12-31');

        $auditMetadata = new AuditMetadata(
            key: 'test_key',
            value: 'test_value',
            type: 'test_type',
            encrypted: true,
            indexed: true,
            description: 'Test description',
            tags: ['tag1', 'tag2'],
            source: 'test_source',
            expiresAt: $expiresAt
        );

        $expected = [
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => 'test_type',
            'encrypted' => true,
            'indexed' => true,
            'description' => 'Test description',
            'tags' => ['tag1', 'tag2'],
            'source' => 'test_source',
            'expiresAt' => $expiresAt,
        ];

        $this->assertEquals($expected, $auditMetadata->toArray());
    }

    public function testFromArray(): void
    {
        $expiresAt = new \DateTime('2024-12-31');

        $config = [
            'key' => 'from_array_key',
            'value' => 'from_array_value',
            'type' => 'from_array_type',
            'encrypted' => true,
            'indexed' => true,
            'description' => 'From array description',
            'tags' => ['from_array_tag'],
            'source' => 'from_array_source',
            'expiresAt' => $expiresAt,
        ];

        $auditMetadata = AuditMetadata::fromArray($config);

        $this->assertEquals('from_array_key', $auditMetadata->key);
        $this->assertEquals('from_array_value', $auditMetadata->value);
        $this->assertEquals('from_array_type', $auditMetadata->type);
        $this->assertTrue($auditMetadata->encrypted);
        $this->assertTrue($auditMetadata->indexed);
        $this->assertEquals('From array description', $auditMetadata->description);
        $this->assertEquals(['from_array_tag'], $auditMetadata->tags);
        $this->assertEquals('from_array_source', $auditMetadata->source);
        $this->assertEquals($expiresAt, $auditMetadata->expiresAt);
    }

    public function testFromArrayWithDefaults(): void
    {
        $auditMetadata = AuditMetadata::fromArray([]);

        $this->assertNull($auditMetadata->key);
        $this->assertNull($auditMetadata->value);
        $this->assertNull($auditMetadata->type);
        $this->assertFalse($auditMetadata->encrypted);
        $this->assertFalse($auditMetadata->indexed);
        $this->assertNull($auditMetadata->description);
        $this->assertEquals([], $auditMetadata->tags);
        $this->assertNull($auditMetadata->source);
        $this->assertNull($auditMetadata->expiresAt);
    }
}
