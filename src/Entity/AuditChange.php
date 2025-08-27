<?php

namespace BenMacha\AuditBundle\Entity;

use BenMacha\AuditBundle\Repository\AuditChangeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Audit Change Entity.
 *
 * Stores detailed field-level changes for each audit log entry,
 * tracking old and new values for individual entity properties.
 */
#[ORM\Entity(repositoryClass: AuditChangeRepository::class)]
#[ORM\Table(name: 'audit_change')]
#[ORM\Index(columns: ['field_name'])]
#[ORM\Index(columns: ['audit_log_id'])]
class AuditChange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $fieldName;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $newValue = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $fieldType = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: AuditLog::class, inversedBy: 'changes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AuditLog $auditLog = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): static
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): static
    {
        $this->oldValue = $oldValue;

        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): static
    {
        $this->newValue = $newValue;

        return $this;
    }

    public function getFieldType(): ?string
    {
        return $this->fieldType;
    }

    public function setFieldType(?string $fieldType): static
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMetadataValue(string $key, mixed $value): static
    {
        if (null === $this->metadata) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getAuditLog(): ?AuditLog
    {
        return $this->auditLog;
    }

    public function setAuditLog(?AuditLog $auditLog): static
    {
        $this->auditLog = $auditLog;

        return $this;
    }

    /**
     * Get formatted old value for display.
     */
    public function getFormattedOldValue(): string
    {
        return $this->formatValue($this->oldValue);
    }

    /**
     * Get formatted new value for display.
     */
    public function getFormattedNewValue(): string
    {
        return $this->formatValue($this->newValue);
    }

    /**
     * Format value for display based on field type.
     */
    private function formatValue(?string $value): string
    {
        if (null === $value) {
            return '<null>';
        }

        if ('' === $value) {
            return '<empty>';
        }

        // Handle different field types
        switch ($this->fieldType) {
            case 'datetime':
            case 'datetime_immutable':
                try {
                    $date = new \DateTime($value);

                    return $date->format('Y-m-d H:i:s');
                } catch (\Exception) {
                    return $value;
                }

            case 'date':
            case 'date_immutable':
                try {
                    $date = new \DateTime($value);

                    return $date->format('Y-m-d');
                } catch (\Exception) {
                    return $value;
                }

            case 'boolean':
                return '1' === $value || 'true' === $value ? 'true' : 'false';

            case 'json':
                $decoded = json_decode($value, true);
                if (JSON_ERROR_NONE === json_last_error()) {
                    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }

                return $value;

            case 'text':
                // Truncate long text values
                if (strlen($value) > 100) {
                    return substr($value, 0, 100) . '...';
                }

                return $value;

            default:
                return $value;
        }
    }

    /**
     * Check if the field value has actually changed.
     */
    public function hasChanged(): bool
    {
        return $this->oldValue !== $this->newValue;
    }

    /**
     * Get the change type (added, modified, removed).
     */
    public function getChangeType(): string
    {
        if (null === $this->oldValue && null !== $this->newValue) {
            return 'added';
        }

        if (null !== $this->oldValue && null === $this->newValue) {
            return 'removed';
        }

        return 'modified';
    }

    /**
     * Get change type badge class for UI.
     */
    public function getChangeTypeBadgeClass(): string
    {
        return match ($this->getChangeType()) {
            'added' => 'badge-success',
            'removed' => 'badge-danger',
            'modified' => 'badge-warning',
            default => 'badge-secondary'
        };
    }

    /**
     * Get human-readable field name.
     */
    public function getHumanFieldName(): string
    {
        // Convert camelCase to Title Case
        $name = preg_replace('/(?<!^)[A-Z]/', ' $0', $this->fieldName);

        return ucfirst($name);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s: %s → %s',
            $this->fieldName,
            $this->getFormattedOldValue(),
            $this->getFormattedNewValue()
        );
    }
}
