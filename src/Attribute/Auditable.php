<?php

namespace BenMacha\AuditBundle\Attribute;

use Attribute;

/**
 * Marks an entity as auditable.
 *
 * This attribute can be applied to entity classes to enable audit tracking.
 * It allows configuration of which operations to track and which fields to ignore.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Auditable
{
    /**
     * @var array<string> Operations to track (create, update, delete)
     */
    public array $operations;

    /**
     * @var array<string> Fields to ignore during auditing
     */
    public array $ignoredFields;

    /**
     * @var bool Whether auditing is enabled for this entity
     */
    public bool $enabled;

    /**
     * @var bool Whether to track field changes in detail
     */
    public bool $trackChanges;

    /**
     * @var int|null Maximum number of audit logs to keep (null = unlimited)
     */
    public ?int $maxLogs;

    /**
     * @var array<string> Sensitive fields that should be encrypted
     */
    public array $sensitiveFields;

    /**
     * @var string|null Custom table name for audit logs
     */
    public ?string $auditTable;

    /**
     * @var bool Whether to use async processing for this entity
     */
    public bool $async;

    /**
     * @var array<string> Additional metadata to store
     */
    public array $metadata;

    /**
     * Constructor.
     *
     * @param array<string> $operations      Operations to track (default: all)
     * @param array<string> $ignoredFields   Fields to ignore during auditing
     * @param bool          $enabled         Whether auditing is enabled
     * @param bool          $trackChanges    Whether to track field changes in detail
     * @param int|null      $maxLogs         Maximum number of audit logs to keep
     * @param array<string> $sensitiveFields Sensitive fields that should be encrypted
     * @param string|null   $auditTable      Custom table name for audit logs
     * @param bool          $async           Whether to use async processing
     * @param array<string> $metadata        Additional metadata to store
     */
    public function __construct(
        array $operations = ['create', 'update', 'delete'],
        array $ignoredFields = [],
        bool $enabled = true,
        bool $trackChanges = true,
        ?int $maxLogs = null,
        array $sensitiveFields = [],
        ?string $auditTable = null,
        bool $async = false,
        array $metadata = []
    ) {
        $this->operations = $operations;
        $this->ignoredFields = $ignoredFields;
        $this->enabled = $enabled;
        $this->trackChanges = $trackChanges;
        $this->maxLogs = $maxLogs;
        $this->sensitiveFields = $sensitiveFields;
        $this->auditTable = $auditTable;
        $this->async = $async;
        $this->metadata = $metadata;
    }

    /**
     * Check if a specific operation should be tracked.
     *
     * @param string $operation The operation to check
     */
    public function shouldTrackOperation(string $operation): bool
    {
        return $this->enabled && in_array($operation, $this->operations, true);
    }

    /**
     * Check if a field should be ignored.
     *
     * @param string $field The field name to check
     */
    public function shouldIgnoreField(string $field): bool
    {
        return in_array($field, $this->ignoredFields, true);
    }

    /**
     * Check if a field is sensitive and should be encrypted.
     *
     * @param string $field The field name to check
     */
    public function isSensitiveField(string $field): bool
    {
        return in_array($field, $this->sensitiveFields, true);
    }

    /**
     * Get the configuration as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operations' => $this->operations,
            'ignoredFields' => $this->ignoredFields,
            'enabled' => $this->enabled,
            'trackChanges' => $this->trackChanges,
            'maxLogs' => $this->maxLogs,
            'sensitiveFields' => $this->sensitiveFields,
            'auditTable' => $this->auditTable,
            'async' => $this->async,
            'metadata' => $this->metadata,
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
            $config['operations'] ?? ['create', 'update', 'delete'],
            $config['ignoredFields'] ?? [],
            $config['enabled'] ?? true,
            $config['trackChanges'] ?? true,
            $config['maxLogs'] ?? null,
            $config['sensitiveFields'] ?? [],
            $config['auditTable'] ?? null,
            $config['async'] ?? false,
            $config['metadata'] ?? []
        );
    }
}
