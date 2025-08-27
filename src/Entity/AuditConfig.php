<?php

namespace BenMacha\AuditBundle\Entity;

use BenMacha\AuditBundle\Repository\AuditConfigRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Audit Configuration Entity.
 *
 * Stores global audit configuration settings that can be
 * managed through the web interface.
 */
#[ORM\Entity(repositoryClass: AuditConfigRepository::class)]
#[ORM\Table(name: 'audit_config')]
#[ORM\HasLifecycleCallbacks]
class AuditConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $settings = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'auditConfig', targetEntity: EntityConfig::class, cascade: ['persist', 'remove'])]
    private Collection $entityConfigs;

    public function __construct()
    {
        $this->entityConfigs = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    /**
     * @return Collection<int, EntityConfig>
     */
    public function getEntityConfigs(): Collection
    {
        return $this->entityConfigs;
    }

    public function addEntityConfig(EntityConfig $entityConfig): static
    {
        if (!$this->entityConfigs->contains($entityConfig)) {
            $this->entityConfigs->add($entityConfig);
            $entityConfig->setAuditConfig($this);
        }

        return $this;
    }

    public function removeEntityConfig(EntityConfig $entityConfig): static
    {
        if ($this->entityConfigs->removeElement($entityConfig)) {
            if ($entityConfig->getAuditConfig() === $this) {
                $entityConfig->setAuditConfig(null);
            }
        }

        return $this;
    }

    /**
     * Get retention days setting.
     */
    public function getRetentionDays(): int
    {
        return $this->getSetting('retention_days', 365);
    }

    /**
     * Set retention days setting.
     */
    public function setRetentionDays(int $days): static
    {
        return $this->setSetting('retention_days', $days);
    }

    /**
     * Check if async processing is enabled.
     */
    public function isAsyncProcessingEnabled(): bool
    {
        return $this->getSetting('async_processing', true);
    }

    /**
     * Set async processing setting.
     */
    public function setAsyncProcessing(bool $enabled): static
    {
        return $this->setSetting('async_processing', $enabled);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
