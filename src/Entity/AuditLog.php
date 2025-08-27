<?php

namespace BenMacha\AuditBundle\Entity;

use BenMacha\AuditBundle\Repository\AuditLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Audit Log Entity.
 *
 * Stores audit records for each tracked database operation
 * including user information, IP address, and operation details.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['entity_class'])]
#[ORM\Index(columns: ['entity_id'])]
#[ORM\Index(columns: ['operation'])]
#[ORM\Index(columns: ['created_at'])]
#[ORM\Index(columns: ['user_id'])]
class AuditLog
{
    public const OPERATION_INSERT = 'INSERT';
    public const OPERATION_UPDATE = 'UPDATE';
    public const OPERATION_DELETE = 'DELETE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $entityClass;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $entityId;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::OPERATION_INSERT, self::OPERATION_UPDATE, self::OPERATION_DELETE])]
    private string $operation;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $userId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $username = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    #[Assert\Ip]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldValues = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newValues = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: EntityConfig::class, inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EntityConfig $entityConfig = null;

    #[ORM\OneToMany(mappedBy: 'auditLog', targetEntity: AuditChange::class, cascade: ['persist', 'remove'])]
    private Collection $changes;

    public function __construct()
    {
        $this->changes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function setOldValues(?array $oldValues): static
    {
        $this->oldValues = $oldValues;

        return $this;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function setNewValues(?array $newValues): static
    {
        $this->newValues = $newValues;

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

    public function getEntityConfig(): ?EntityConfig
    {
        return $this->entityConfig;
    }

    public function setEntityConfig(?EntityConfig $entityConfig): static
    {
        $this->entityConfig = $entityConfig;

        return $this;
    }

    /**
     * @return Collection<int, AuditChange>
     */
    public function getChanges(): Collection
    {
        return $this->changes;
    }

    public function addChange(AuditChange $change): static
    {
        if (!$this->changes->contains($change)) {
            $this->changes->add($change);
            $change->setAuditLog($this);
        }

        return $this;
    }

    public function removeChange(AuditChange $change): static
    {
        if ($this->changes->removeElement($change)) {
            if ($change->getAuditLog() === $this) {
                $change->setAuditLog(null);
            }
        }

        return $this;
    }

    /**
     * Set user information from UserInterface.
     */
    public function setUser(?UserInterface $user): static
    {
        if ($user) {
            $this->setUserId((string) $user->getUserIdentifier());
            $this->setUsername($user->getUserIdentifier());
        } else {
            $this->setUserId(null);
            $this->setUsername(null);
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
     * Get changed fields count.
     */
    public function getChangedFieldsCount(): int
    {
        return $this->changes->count();
    }

    /**
     * Check if this is an insert operation.
     */
    public function isInsert(): bool
    {
        return self::OPERATION_INSERT === $this->operation;
    }

    /**
     * Check if this is an update operation.
     */
    public function isUpdate(): bool
    {
        return self::OPERATION_UPDATE === $this->operation;
    }

    /**
     * Check if this is a delete operation.
     */
    public function isDelete(): bool
    {
        return self::OPERATION_DELETE === $this->operation;
    }

    /**
     * Get operation badge class for UI.
     */
    public function getOperationBadgeClass(): string
    {
        return match ($this->operation) {
            self::OPERATION_INSERT => 'badge-success',
            self::OPERATION_UPDATE => 'badge-warning',
            self::OPERATION_DELETE => 'badge-danger',
            default => 'badge-secondary'
        };
    }

    public function __toString(): string
    {
        return sprintf(
            '%s %s #%s at %s',
            $this->operation,
            $this->getShortClassName(),
            $this->entityId,
            $this->createdAt->format('Y-m-d H:i:s')
        );
    }
}
