<?php

namespace BenMacha\AuditBundle\Entity;

use BenMacha\AuditBundle\Repository\EntityConfigRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entity Configuration.
 *
 * Stores audit configuration for specific entity classes,
 * including which columns to ignore and table settings.
 */
#[ORM\Entity(repositoryClass: EntityConfigRepository::class)]
#[ORM\Table(name: 'audit_entity_config')]
#[ORM\UniqueConstraint(name: 'entity_class_unique', columns: ['entity_class'])]
#[ORM\HasLifecycleCallbacks]
class EntityConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $entityClass;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $ignoredColumns = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $createTable = true;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $tableName = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $settings = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: AuditConfig::class, inversedBy: 'entityConfigs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AuditConfig $auditConfig = null;

    #[ORM\OneToMany(mappedBy: 'entityConfig', targetEntity: AuditLog::class)]
    private Collection $auditLogs;

    public function __construct()
    {
        $this->auditLogs = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function setEntityClass(string $entityClass): static
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getIgnoredColumns(): ?array
    {
        return $this->ignoredColumns ?? [];
    }

    public function setIgnoredColumns(?array $ignoredColumns): static
    {
        $this->ignoredColumns = $ignoredColumns;

        return $this;
    }

    public function addIgnoredColumn(string $column): static
    {
        if (null === $this->ignoredColumns) {
            $this->ignoredColumns = [];
        }

        if (!in_array($column, $this->ignoredColumns, true)) {
            $this->ignoredColumns[] = $column;
        }

        return $this;
    }

    public function removeIgnoredColumn(string $column): static
    {
        if (null !== $this->ignoredColumns) {
            $this->ignoredColumns = array_values(array_filter(
                $this->ignoredColumns,
                fn ($col) => $col !== $column
            ));
        }

        return $this;
    }

    public function isColumnIgnored(string $column): bool
    {
        return in_array($column, $this->getIgnoredColumns(), true);
    }

    public function shouldCreateTable(): bool
    {
        return $this->createTable;
    }

    public function setCreateTable(bool $createTable): static
    {
        $this->createTable = $createTable;

        return $this;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    public function setTableName(?string $tableName): static
    {
        $this->tableName = $tableName;

        return $this;
    }

    public function getSettings(): ?array
    {
        return $this->settings;
    }

    public function setSettings(?array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, mixed $value): static
    {
        if (null === $this->settings) {
            $this->settings = [];
        }
        $this->settings[$key] = $value;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getAuditConfig(): ?AuditConfig
    {
        return $this->auditConfig;
    }

    public function setAuditConfig(?AuditConfig $auditConfig): static
    {
        $this->auditConfig = $auditConfig;

        return $this;
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function addAuditLog(AuditLog $auditLog): static
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
            $auditLog->setEntityConfig($this);
        }

        return $this;
    }

    public function removeAuditLog(AuditLog $auditLog): static
    {
        if ($this->auditLogs->removeElement($auditLog)) {
            if ($auditLog->getEntityConfig() === $this) {
                $auditLog->setEntityConfig(null);
            }
        }

        return $this;
    }

    /**
     * Get the short class name for display purposes.
     */
    public function getShortClassName(): string
    {
        $parts = explode('\\', $this->entityClass);

        return end($parts);
    }

    /**
     * Get the effective table name for audit data.
     */
    public function getEffectiveTableName(): string
    {
        if ($this->tableName) {
            return $this->tableName;
        }

        $shortName = $this->getShortClassName();

        return 'audit_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }

    public function __toString(): string
    {
        return $this->getShortClassName();
    }
}
