---
layout: default
title: Examples
permalink: /examples/
---

# Examples

Practical examples and code snippets for using the Symfony Audit Bundle effectively.

## Quick Start Examples

### Basic Entity Auditing

```php
<?php
// src/Entity/User.php
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
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    // ... getters and setters
}
```

### Configuration Example

```yaml
# config/packages/audit.yaml
audit:
  enabled: true
  retention_days: 365
  async_processing: false
  
  entities:
    App\Entity\User:
      enabled: true
      operations: ['create', 'update', 'delete']
      ignored_fields: ['password', 'lastLogin']
    App\Entity\Product:
      enabled: true
      operations: ['create', 'update']
      async: true
  
  api:
    enabled: true
    rate_limit: 100
    prefix: '/api/audit'
```

## Advanced Usage Examples

### Custom Event Subscriber

```php
<?php
// src/EventSubscriber/AuditSubscriber.php
namespace App\EventSubscriber;

use BenMacha\AuditBundle\Event\PreAuditEvent;
use BenMacha\AuditBundle\Event\PostAuditEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class AuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PreAuditEvent::class => 'onPreAudit',
            PostAuditEvent::class => 'onPostAudit',
        ];
    }

    public function onPreAudit(PreAuditEvent $event): void
    {
        // Add custom metadata
        $event->addMetadata('ip_address', $this->getClientIp());
        $event->addMetadata('user_agent', $this->getUserAgent());
        
        // Conditional auditing
        if ($this->shouldSkipAudit($event->getEntity())) {
            $event->preventDefault();
        }
    }

    public function onPostAudit(PostAuditEvent $event): void
    {
        $auditLog = $event->getAuditLog();
        
        // Send notifications for critical changes
        if ($this->isCriticalChange($auditLog)) {
            $this->sendNotification($auditLog);
        }
    }
}
```

### Using Audit Attributes

```php
<?php
// src/Entity/Product.php
namespace App\Entity;

use BenMacha\AuditBundle\Attribute\Auditable;
use BenMacha\AuditBundle\Attribute\AuditSensitive;
use BenMacha\AuditBundle\Attribute\IgnoreAudit;
use BenMacha\AuditBundle\Attribute\AuditContext;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Auditable(operations: ['create', 'update'])]
#[AuditContext(group: 'products', priority: 'high')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[AuditSensitive(reason: 'Financial data')]
    private float $price;

    #[ORM\Column(type: 'datetime')]
    #[IgnoreAudit]
    private \DateTime $lastViewed;

    // ... getters and setters
}
```

## API Usage Examples

### Fetching Audit Data

```bash
# Get all audit entries
curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://your-app.com/api/audit

# Get audit entries for a specific user
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "http://your-app.com/api/audit?entity=User&entityId=123"

# Get audit entries within date range
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "http://your-app.com/api/audit?from=2023-01-01&to=2023-12-31"
```

### Rollback Example

```bash
# Rollback an entity to a previous state
curl -X POST \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     http://your-app.com/api/audit/123/rollback
```

### JavaScript API Client

```javascript
class AuditApiClient {
    constructor(baseUrl, token) {
        this.baseUrl = baseUrl;
        this.token = token;
    }

    async getAuditEntries(filters = {}) {
        const params = new URLSearchParams(filters);
        const response = await fetch(`${this.baseUrl}/api/audit?${params}`, {
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            }
        });
        return response.json();
    }

    async rollbackEntity(auditId) {
        const response = await fetch(`${this.baseUrl}/api/audit/${auditId}/rollback`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            }
        });
        return response.json();
    }

    async getEntityHistory(entity, entityId) {
        const response = await fetch(`${this.baseUrl}/api/audit/entity/${entity}/${entityId}/history`, {
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            }
        });
        return response.json();
    }
}

// Usage
const client = new AuditApiClient('http://your-app.com', 'your-token');

// Get recent user changes
client.getAuditEntries({ entity: 'User', limit: 10 })
    .then(data => console.log(data));
```

## Service Usage Examples

### Manual Audit Logging

```php
<?php
// src/Service/CustomService.php
namespace App\Service;

use BenMacha\AuditBundle\Service\AuditManager;

class CustomService
{
    public function __construct(
        private AuditManager $auditManager
    ) {}

    public function performCustomOperation(User $user): void
    {
        // Your business logic here
        $user->setStatus('active');
        
        // Manual audit logging
        $this->auditManager->logCustomAction(
            entity: $user,
            action: 'status_change',
            changes: ['status' => ['inactive', 'active']],
            metadata: ['reason' => 'Admin approval']
        );
    }
}
```

### Configuration Service

```php
<?php
// src/Controller/AuditConfigController.php
namespace App\Controller;

use BenMacha\AuditBundle\Service\ConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuditConfigController extends AbstractController
{
    public function __construct(
        private ConfigurationService $configService
    ) {}

    #[Route('/admin/audit/config', methods: ['GET'])]
    public function getConfig(): JsonResponse
    {
        $config = $this->configService->getEntityConfigurations();
        return $this->json($config);
    }

    #[Route('/admin/audit/config/{entity}', methods: ['PUT'])]
    public function updateEntityConfig(string $entity): JsonResponse
    {
        $this->configService->updateEntityConfiguration(
            entityClass: $entity,
            enabled: true,
            operations: ['create', 'update', 'delete'],
            ignoredFields: ['password', 'token']
        );
        
        return $this->json(['success' => true]);
    }
}
```

## Testing Examples

### Unit Test Example

```php
<?php
// tests/Unit/Service/AuditManagerTest.php
namespace App\Tests\Unit\Service;

use BenMacha\AuditBundle\Service\AuditManager;
use PHPUnit\Framework\TestCase;

class AuditManagerTest extends TestCase
{
    public function testAuditLogging(): void
    {
        $auditManager = $this->createMock(AuditManager::class);
        
        $auditManager->expects($this->once())
            ->method('logCustomAction')
            ->with(
                $this->isInstanceOf(User::class),
                'test_action',
                ['field' => ['old', 'new']]
            );
        
        // Test your service that uses the audit manager
    }
}
```

## More Examples

For more detailed examples and advanced usage patterns, check out:

- [Complete Usage Guide](../docs/USAGE_GUIDE.html) - Comprehensive usage documentation
- [API Reference](../docs/API.html) - Complete API documentation
- [Attributes Reference](../docs/ATTRIBUTES.html) - Available PHP attributes
- [Contributing Guide](../docs/contributing.html) - How to contribute examples

Have a specific use case? Check our [GitHub Issues](https://github.com/benmacha/audit-bundle/issues) or create a new one!