---
layout: default
title: Quick Start
permalink: /docs/quick-start/
nav_order: 3
---

# Quick Start Guide

Get up and running with the Symfony Audit Bundle in just a few minutes.

## Step 1: Mark Entities for Auditing

Add the `#[Auditable]` attribute to any entity you want to audit:

```php
<?php

namespace App\Entity;

use BenMacha\AuditBundle\Attribute\Auditable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Auditable]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    // ... getters and setters
}
```

## Step 2: Exclude Sensitive Fields (Optional)

Use the `#[IgnoreAudit]` attribute to exclude sensitive fields from auditing:

```php
<?php

namespace App\Entity;

use BenMacha\AuditBundle\Attribute\Auditable;
use BenMacha\AuditBundle\Attribute\IgnoreAudit;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Auditable]
class User
{
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[IgnoreAudit]
    private string $password;

    // ... rest of the entity
}
```

## Step 3: Start Using Your Application

That's it! The bundle will automatically track changes to your audited entities:

```php
<?php

// In your controller or service
public function updateUser(User $user): void
{
    $user->setName('New Name');
    $user->setEmail('new@example.com');
    
    $this->entityManager->flush();
    
    // Audit entry is automatically created!
}
```

## Step 4: View Audit History

### Using the Web Interface

Visit `/audit` in your browser to see the web interface:

- View all audit entries
- Filter by entity type, action, or date
- See detailed change information
- Rollback changes with one click

### Using the REST API

Query audit data programmatically:

```bash
# Get all audit entries
curl http://your-app.com/api/audit

# Get audit entries for a specific entity
curl http://your-app.com/api/audit?entity=User&entityId=123

# Get audit entries within a date range
curl "http://your-app.com/api/audit?from=2023-01-01&to=2023-12-31"
```

### Using the Service

Access audit data in your code:

```php
<?php

use BenMacha\AuditBundle\Service\AuditService;

class MyController
{
    public function __construct(
        private AuditService $auditService
    ) {}

    public function getUserHistory(int $userId): array
    {
        return $this->auditService->getAuditHistory(
            entityClass: User::class,
            entityId: $userId
        );
    }

    public function rollbackUser(int $userId, int $auditId): void
    {
        $this->auditService->rollback($auditId);
    }
}
```

## Common Use Cases

### 1. User Activity Tracking

```php
#[Auditable]
class User
{
    // Track login times, profile changes, etc.
}
```

### 2. Product Inventory Management

```php
#[Auditable]
class Product
{
    #[ORM\Column(type: 'integer')]
    private int $stock;
    
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $price;
    
    // Track stock changes and price updates
}
```

### 3. Order Processing

```php
#[Auditable]
class Order
{
    #[ORM\Column(type: 'string')]
    private string $status;
    
    // Track order status changes
}
```

## Next Steps

- [Configuration]({{ site.baseurl }}/docs/configuration/) - Customize the bundle behavior
- [API Reference]({{ site.baseurl }}/docs/api/) - Complete API documentation
- [Advanced Usage]({{ site.baseurl }}/docs/advanced/) - Advanced features and customization
- [Troubleshooting]({{ site.baseurl }}/docs/troubleshooting/) - Common issues and solutions