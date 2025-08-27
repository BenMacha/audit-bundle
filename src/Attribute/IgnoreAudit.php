<?php

namespace BenMacha\AuditBundle\Attribute;

use Attribute;

/**
 * Marks a property or field to be ignored during auditing.
 *
 * This attribute can be applied to entity properties to exclude them
 * from audit tracking, even if the entity is marked as auditable.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class IgnoreAudit
{
    /**
     * @var array<string> Operations for which this field should be ignored
     */
    public array $operations;

    /**
     * @var string|null Reason for ignoring this field (for documentation)
     */
    public ?string $reason;

    /**
     * @var bool Whether to completely ignore this field in all operations
     */
    public bool $always;

    /**
     * @var bool Whether this field contains sensitive data
     */
    public bool $sensitive;

    /**
     * Constructor.
     *
     * @param array<string> $operations Operations for which to ignore this field (default: all)
     * @param string|null   $reason     Reason for ignoring this field
     * @param bool          $always     Whether to always ignore this field
     * @param bool          $sensitive  Whether this field contains sensitive data
     */
    public function __construct(
        array $operations = ['create', 'update', 'delete'],
        ?string $reason = null,
        bool $always = true,
        bool $sensitive = false
    ) {
        $this->operations = $operations;
        $this->reason = $reason;
        $this->always = $always;
        $this->sensitive = $sensitive;
    }

    /**
     * Check if this field should be ignored for a specific operation.
     *
     * @param string $operation The operation to check
     */
    public function shouldIgnoreForOperation(string $operation): bool
    {
        return $this->always || in_array($operation, $this->operations, true);
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
            'reason' => $this->reason,
            'always' => $this->always,
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
            $config['operations'] ?? ['create', 'update', 'delete'],
            $config['reason'] ?? null,
            $config['always'] ?? true,
            $config['sensitive'] ?? false
        );
    }
}
