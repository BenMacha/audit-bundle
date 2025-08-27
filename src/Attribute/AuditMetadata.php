<?php

namespace BenMacha\AuditBundle\Attribute;

use Attribute;

/**
 * Adds custom metadata to audit logs.
 *
 * This attribute can be applied to entity classes or properties to include
 * additional metadata in audit logs for better tracking and analysis.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class AuditMetadata
{
    /**
     * @var array<string, mixed> Custom metadata key-value pairs
     */
    public array $metadata;

    /**
     * @var string|null Category for grouping metadata
     */
    public ?string $category;

    /**
     * @var int Priority for metadata processing (higher = more important)
     */
    public int $priority;

    /**
     * @var bool Whether this metadata should be included in exports
     */
    public bool $exportable;

    /**
     * @var bool Whether this metadata contains sensitive information
     */
    public bool $sensitive;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $metadata   Custom metadata key-value pairs
     * @param string|null          $category   Category for grouping metadata
     * @param int                  $priority   Priority for metadata processing
     * @param bool                 $exportable Whether this metadata should be included in exports
     * @param bool                 $sensitive  Whether this metadata contains sensitive information
     */
    public function __construct(
        array $metadata = [],
        ?string $category = null,
        int $priority = 0,
        bool $exportable = true,
        bool $sensitive = false
    ) {
        $this->metadata = $metadata;
        $this->category = $category;
        $this->priority = $priority;
        $this->exportable = $exportable;
        $this->sensitive = $sensitive;
    }

    /**
     * Get a specific metadata value.
     *
     * @param string $key     The metadata key
     * @param mixed  $default Default value if key doesn't exist
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set a metadata value.
     *
     * @param string $key   The metadata key
     * @param mixed  $value The metadata value
     */
    public function set(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Check if a metadata key exists.
     *
     * @param string $key The metadata key
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Get all metadata keys.
     *
     * @return array<string>
     */
    public function getKeys(): array
    {
        return array_keys($this->metadata);
    }

    /**
     * Merge with another metadata instance.
     */
    public function merge(AuditMetadata $other): self
    {
        $merged = new self(
            array_merge($this->metadata, $other->metadata),
            $this->category ?? $other->category,
            max($this->priority, $other->priority),
            $this->exportable && $other->exportable,
            $this->sensitive || $other->sensitive
        );

        return $merged;
    }

    /**
     * Get exportable metadata (excluding sensitive data if needed).
     *
     * @param bool $includeSensitive Whether to include sensitive metadata
     *
     * @return array<string, mixed>
     */
    public function getExportableMetadata(bool $includeSensitive = false): array
    {
        if (!$this->exportable) {
            return [];
        }

        if ($this->sensitive && !$includeSensitive) {
            return [];
        }

        return $this->metadata;
    }

    /**
     * Get the configuration as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'metadata' => $this->metadata,
            'category' => $this->category,
            'priority' => $this->priority,
            'exportable' => $this->exportable,
            'sensitive' => $this->sensitive,
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
            $config['metadata'] ?? [],
            $config['category'] ?? null,
            $config['priority'] ?? 0,
            $config['exportable'] ?? true,
            $config['sensitive'] ?? false
        );
    }
}
