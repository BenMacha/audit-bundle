<?php

namespace BenMacha\AuditBundle\Attribute;

use Attribute;

/**
 * Provides additional context information for audit logs.
 *
 * This attribute can be applied to entity classes, properties, or methods to add
 * contextual information that will be included in audit logs.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AuditContext
{
    /**
     * @var string Context key/name
     */
    public string $key;

    /**
     * @var mixed Context value
     */
    public mixed $value;

    /**
     * @var string|null Context category for grouping
     */
    public ?string $category;

    /**
     * @var string Context type (static, dynamic, computed)
     */
    public string $type;

    /**
     * @var string|null Method or property to call for dynamic values
     */
    public ?string $source;

    /**
     * @var array<string> Operations for which this context should be included
     */
    public array $operations;

    /**
     * @var bool Whether this context should be exported
     */
    public bool $exportable;

    /**
     * @var bool Whether this context contains sensitive information
     */
    public bool $sensitive;

    /**
     * @var int Priority for context ordering (higher = more important)
     */
    public int $priority;

    /**
     * @var string|null Description of what this context represents
     */
    public ?string $description;

    /**
     * Constructor.
     *
     * @param string        $key         Context key/name
     * @param mixed         $value       Context value (for static contexts)
     * @param string|null   $category    Context category for grouping
     * @param string        $type        Context type (static, dynamic, computed)
     * @param string|null   $source      Method or property to call for dynamic values
     * @param array<string> $operations  Operations for which this context should be included
     * @param bool          $exportable  Whether this context should be exported
     * @param bool          $sensitive   Whether this context contains sensitive information
     * @param int           $priority    Priority for context ordering
     * @param string|null   $description Description of what this context represents
     */
    public function __construct(
        string $key,
        mixed $value = null,
        ?string $category = null,
        string $type = 'static',
        ?string $source = null,
        array $operations = ['create', 'update', 'delete'],
        bool $exportable = true,
        bool $sensitive = false,
        int $priority = 0,
        ?string $description = null
    ) {
        $this->key = $key;
        $this->value = $value;
        $this->category = $category;
        $this->type = $type;
        $this->source = $source;
        $this->operations = $operations;
        $this->exportable = $exportable;
        $this->sensitive = $sensitive;
        $this->priority = $priority;
        $this->description = $description;
    }

    /**
     * Check if this context should be included for a specific operation.
     *
     * @param string $operation The operation to check
     */
    public function isIncludedForOperation(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }

    /**
     * Get the context value, resolving dynamic values if necessary.
     *
     * @param object|null $entity The entity instance for dynamic resolution
     */
    public function getValue(?object $entity = null): mixed
    {
        return match ($this->type) {
            'static' => $this->value,
            'dynamic' => $this->resolveDynamicValue($entity),
            'computed' => $this->computeValue($entity),
            default => $this->value,
        };
    }

    /**
     * Resolve a dynamic value from the entity.
     */
    private function resolveDynamicValue(?object $entity): mixed
    {
        if (!$entity || !$this->source) {
            return $this->value;
        }

        // Try to call method first
        if (method_exists($entity, $this->source)) {
            try {
                return $entity->{$this->source}();
            } catch (\Throwable) {
                return $this->value;
            }
        }

        // Try to access property
        if (property_exists($entity, $this->source)) {
            try {
                return $entity->{$this->source};
            } catch (\Throwable) {
                return $this->value;
            }
        }

        return $this->value;
    }

    /**
     * Compute a value based on the entity state.
     */
    private function computeValue(?object $entity): mixed
    {
        if (!$entity) {
            return $this->value;
        }

        // Handle common computed values
        return match ($this->key) {
            'entity_class' => get_class($entity),
            'entity_id' => $this->getEntityId($entity),
            'entity_string' => (string) $entity,
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            default => $this->value,
        };
    }

    /**
     * Get the entity ID if available.
     */
    private function getEntityId(object $entity): mixed
    {
        // Try common ID property names
        $idProperties = ['id', 'getId', 'uuid', 'getUuid'];

        foreach ($idProperties as $property) {
            if (method_exists($entity, $property)) {
                try {
                    return $entity->{$property}();
                } catch (\Throwable) {
                    continue;
                }
            }

            if (property_exists($entity, $property)) {
                try {
                    return $entity->{$property};
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Check if this context is sensitive.
     */
    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    /**
     * Check if this context should be exported.
     */
    public function isExportable(): bool
    {
        return $this->exportable;
    }

    /**
     * Get the context category.
     */
    public function getCategory(): string
    {
        return $this->category ?? 'general';
    }

    /**
     * Get the context priority.
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the configuration as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'category' => $this->category,
            'type' => $this->type,
            'source' => $this->source,
            'operations' => $this->operations,
            'exportable' => $this->exportable,
            'sensitive' => $this->sensitive,
            'priority' => $this->priority,
            'description' => $this->description,
        ];
    }

    /**
     * Create an instance from array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['key'],
            $config['value'] ?? null,
            $config['category'] ?? null,
            $config['type'] ?? 'static',
            $config['source'] ?? null,
            $config['operations'] ?? ['create', 'update', 'delete'],
            $config['exportable'] ?? true,
            $config['sensitive'] ?? false,
            $config['priority'] ?? 0,
            $config['description'] ?? null
        );
    }

    /**
     * Create a static context.
     */
    public static function static(string $key, mixed $value, ?string $category = null): self
    {
        return new self($key, $value, $category, 'static');
    }

    /**
     * Create a dynamic context.
     */
    public static function dynamic(string $key, string $source, ?string $category = null): self
    {
        return new self($key, null, $category, 'dynamic', $source);
    }

    /**
     * Create a computed context.
     */
    public static function computed(string $key, ?string $category = null): self
    {
        return new self($key, null, $category, 'computed');
    }
}
