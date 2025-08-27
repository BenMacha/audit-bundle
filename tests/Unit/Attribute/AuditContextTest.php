<?php

namespace BenMacha\AuditBundle\Tests\Unit\Attribute;

use BenMacha\AuditBundle\Attribute\AuditContext;
use PHPUnit\Framework\TestCase;

class AuditContextTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $auditContext = new AuditContext();

        $this->assertNull($auditContext->source);
        $this->assertEquals([], $auditContext->tags);
        $this->assertEquals([], $auditContext->metadata);
        $this->assertNull($auditContext->category);
        $this->assertEquals(0, $auditContext->priority);
        $this->assertNull($auditContext->description);
        $this->assertFalse($auditContext->critical);
        $this->assertEquals([], $auditContext->relatedEntities);
    }

    public function testCustomConstructor(): void
    {
        $auditContext = new AuditContext(
            source: 'admin_panel',
            tags: ['user_management', 'security'],
            metadata: ['ip' => '192.168.1.1', 'user_agent' => 'Mozilla/5.0'],
            category: 'user_action',
            priority: 5,
            description: 'User profile update',
            critical: true,
            relatedEntities: ['User', 'Profile']
        );

        $this->assertEquals('admin_panel', $auditContext->source);
        $this->assertEquals(['user_management', 'security'], $auditContext->tags);
        $this->assertEquals(['ip' => '192.168.1.1', 'user_agent' => 'Mozilla/5.0'], $auditContext->metadata);
        $this->assertEquals('user_action', $auditContext->category);
        $this->assertEquals(5, $auditContext->priority);
        $this->assertEquals('User profile update', $auditContext->description);
        $this->assertTrue($auditContext->critical);
        $this->assertEquals(['User', 'Profile'], $auditContext->relatedEntities);
    }

    public function testHasTag(): void
    {
        $auditContext = new AuditContext(tags: ['security', 'user_management', 'admin']);

        $this->assertTrue($auditContext->hasTag('security'));
        $this->assertTrue($auditContext->hasTag('user_management'));
        $this->assertTrue($auditContext->hasTag('admin'));
        $this->assertFalse($auditContext->hasTag('billing'));
        $this->assertFalse($auditContext->hasTag(''));
    }

    public function testHasTagWithEmptyTags(): void
    {
        $auditContext = new AuditContext(tags: []);

        $this->assertFalse($auditContext->hasTag('security'));
        $this->assertFalse($auditContext->hasTag('any_tag'));
    }

    public function testAddTag(): void
    {
        $auditContext = new AuditContext(tags: ['existing']);

        $auditContext->addTag('new_tag');

        $this->assertTrue($auditContext->hasTag('existing'));
        $this->assertTrue($auditContext->hasTag('new_tag'));
        $this->assertEquals(['existing', 'new_tag'], $auditContext->tags);
    }

    public function testAddTagDuplicate(): void
    {
        $auditContext = new AuditContext(tags: ['existing']);

        $auditContext->addTag('existing');

        $this->assertEquals(['existing'], $auditContext->tags);
    }

    public function testRemoveTag(): void
    {
        $auditContext = new AuditContext(tags: ['tag1', 'tag2', 'tag3']);

        $auditContext->removeTag('tag2');

        $this->assertTrue($auditContext->hasTag('tag1'));
        $this->assertFalse($auditContext->hasTag('tag2'));
        $this->assertTrue($auditContext->hasTag('tag3'));
        $this->assertEquals(['tag1', 'tag3'], array_values($auditContext->tags));
    }

    public function testRemoveNonExistentTag(): void
    {
        $auditContext = new AuditContext(tags: ['tag1', 'tag2']);

        $auditContext->removeTag('non_existent');

        $this->assertEquals(['tag1', 'tag2'], $auditContext->tags);
    }

    public function testGetMetadata(): void
    {
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        $auditContext = new AuditContext(metadata: $metadata);

        $this->assertEquals('value1', $auditContext->getMetadata('key1'));
        $this->assertEquals('value2', $auditContext->getMetadata('key2'));
        $this->assertNull($auditContext->getMetadata('non_existent'));
        $this->assertEquals('default', $auditContext->getMetadata('non_existent', 'default'));
    }

    public function testSetMetadata(): void
    {
        $auditContext = new AuditContext();

        $auditContext->setMetadata('new_key', 'new_value');

        $this->assertEquals('new_value', $auditContext->getMetadata('new_key'));
        $this->assertEquals(['new_key' => 'new_value'], $auditContext->metadata);
    }

    public function testSetMetadataOverwrite(): void
    {
        $auditContext = new AuditContext(metadata: ['key' => 'old_value']);

        $auditContext->setMetadata('key', 'new_value');

        $this->assertEquals('new_value', $auditContext->getMetadata('key'));
    }

    public function testIsRelatedToEntity(): void
    {
        $auditContext = new AuditContext(relatedEntities: ['User', 'Profile', 'Order']);

        $this->assertTrue($auditContext->isRelatedToEntity('User'));
        $this->assertTrue($auditContext->isRelatedToEntity('Profile'));
        $this->assertTrue($auditContext->isRelatedToEntity('Order'));
        $this->assertFalse($auditContext->isRelatedToEntity('Product'));
    }

    public function testAddRelatedEntity(): void
    {
        $auditContext = new AuditContext(relatedEntities: ['User']);

        $auditContext->addRelatedEntity('Profile');

        $this->assertTrue($auditContext->isRelatedToEntity('User'));
        $this->assertTrue($auditContext->isRelatedToEntity('Profile'));
        $this->assertEquals(['User', 'Profile'], $auditContext->relatedEntities);
    }

    public function testAddRelatedEntityDuplicate(): void
    {
        $auditContext = new AuditContext(relatedEntities: ['User']);

        $auditContext->addRelatedEntity('User');

        $this->assertEquals(['User'], $auditContext->relatedEntities);
    }

    public function testToArray(): void
    {
        $auditContext = new AuditContext(
            source: 'test_source',
            tags: ['tag1', 'tag2'],
            metadata: ['key' => 'value'],
            category: 'test_category',
            priority: 3,
            description: 'Test description',
            critical: true,
            relatedEntities: ['Entity1', 'Entity2']
        );

        $expected = [
            'source' => 'test_source',
            'tags' => ['tag1', 'tag2'],
            'metadata' => ['key' => 'value'],
            'category' => 'test_category',
            'priority' => 3,
            'description' => 'Test description',
            'critical' => true,
            'relatedEntities' => ['Entity1', 'Entity2'],
        ];

        $this->assertEquals($expected, $auditContext->toArray());
    }

    public function testFromArray(): void
    {
        $config = [
            'source' => 'from_array_source',
            'tags' => ['from_array_tag'],
            'metadata' => ['from_array_key' => 'from_array_value'],
            'category' => 'from_array_category',
            'priority' => 7,
            'description' => 'From array description',
            'critical' => true,
            'relatedEntities' => ['FromArrayEntity'],
        ];

        $auditContext = AuditContext::fromArray($config);

        $this->assertEquals('from_array_source', $auditContext->source);
        $this->assertEquals(['from_array_tag'], $auditContext->tags);
        $this->assertEquals(['from_array_key' => 'from_array_value'], $auditContext->metadata);
        $this->assertEquals('from_array_category', $auditContext->category);
        $this->assertEquals(7, $auditContext->priority);
        $this->assertEquals('From array description', $auditContext->description);
        $this->assertTrue($auditContext->critical);
        $this->assertEquals(['FromArrayEntity'], $auditContext->relatedEntities);
    }

    public function testFromArrayWithDefaults(): void
    {
        $auditContext = AuditContext::fromArray([]);

        $this->assertNull($auditContext->source);
        $this->assertEquals([], $auditContext->tags);
        $this->assertEquals([], $auditContext->metadata);
        $this->assertNull($auditContext->category);
        $this->assertEquals(0, $auditContext->priority);
        $this->assertNull($auditContext->description);
        $this->assertFalse($auditContext->critical);
        $this->assertEquals([], $auditContext->relatedEntities);
    }
}
