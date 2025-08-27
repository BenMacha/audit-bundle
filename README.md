# Symfony Audit Bundle

[![CI](https://github.com/benmacha/symfony-audit-bundle/workflows/CI/badge.svg)](https://github.com/benmacha/symfony-audit-bundle/actions)
[![Coverage Status](https://codecov.io/gh/benmacha/symfony-audit-bundle/branch/main/graph/badge.svg)](https://codecov.io/gh/benmacha/symfony-audit-bundle)
[![Latest Stable Version](https://poser.pugx.org/benmacha/symfony-audit-bundle/v/stable)](https://packagist.org/packages/benmacha/symfony-audit-bundle)
[![Total Downloads](https://poser.pugx.org/benmacha/symfony-audit-bundle/downloads)](https://packagist.org/packages/benmacha/symfony-audit-bundle)
[![License](https://poser.pugx.org/benmacha/symfony-audit-bundle/license)](https://packagist.org/packages/benmacha/symfony-audit-bundle)

A comprehensive Symfony bundle for auditing entity changes with rollback functionality, web interface, and REST API.

## Features

- 🔍 **Automatic Entity Tracking**: Track changes to any Doctrine entity
- 🔄 **Rollback Functionality**: Restore entities to previous states
- 🎯 **Flexible Configuration**: Fine-grained control over what gets audited
- 🌐 **Web Interface**: User-friendly web UI for viewing audit logs
- 🚀 **REST API**: Complete API for programmatic access
- 🔒 **Security Integration**: Role-based access control
- 📊 **Performance Optimized**: Asynchronous processing support
- 🎨 **Customizable**: Extensible architecture with events
- 📱 **Responsive Design**: Mobile-friendly interface
- 🔧 **Developer Tools**: Comprehensive debugging and monitoring

## Requirements

- PHP 7.4 - 8.4
- Symfony 5.4 - 7.x
- Doctrine ORM 2.10+
- MySQL 5.7+ / PostgreSQL 10+ / SQLite 3.25+

## Installation

### Step 1: Install the Bundle

```bash
composer require benmacha/symfony-audit-bundle
```

### Step 2: Enable the Bundle

Add the bundle to your `config/bundles.php`:

```php
<?php

return [
    // ...
    BenMacha\AuditBundle\AuditBundle::class => ['all' => true],
];
```

### Step 3: Configure the Bundle

Create `config/packages/audit.yaml`:

```yaml
audit:
    enabled: true
    retention_days: 365
    async_processing: false
    
    # Database configuration
    database:
        connection: default
    
    # Entity-specific configuration
    entities:
        App\Entity\User:
            enabled: true
            operations: ['create', 'update', 'delete']
            ignored_fields: ['password', 'lastLogin']
        App\Entity\Product:
            enabled: true
            operations: ['create', 'update']
    
    # API configuration
    api:
        enabled: true
        rate_limit: 100
        prefix: '/api/audit'
    
    # Security roles
    security:
        admin_role: 'ROLE_ADMIN'
        auditor_role: 'ROLE_AUDITOR'
        developer_role: 'ROLE_DEVELOPER'
    
    # UI configuration
    ui:
        route_prefix: '/admin/audit'
        items_per_page: 25
        show_ip_address: true
```

### Step 4: Update Database Schema

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Step 5: Configure Routes (Optional)

Add to `config/routes.yaml`:

```yaml
audit_bundle:
    resource: '@AuditBundle/Resources/config/routes.yaml'
    prefix: /admin

audit_api:
    resource: '@AuditBundle/Resources/config/api_routes.yaml'
    prefix: /api
```

## Quick Start

### Basic Entity Auditing

Simply add the `#[Auditable]` attribute to your entity:

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
    
    #[ORM\Column(type: 'string')]
    private string $username;
    
    #[ORM\Column(type: 'string')]
    private string $email;
    
    // ... getters and setters
}
```

### Viewing Audit Logs

Access the web interface at `/admin/audit` or use the API:

```bash
# Get all audit logs
curl -X GET /api/audit/logs

# Get logs for specific entity
curl -X GET /api/audit/logs/entity/User/123

# Rollback entity to previous state
curl -X POST /api/audit/rollback/456
```

## Attributes Reference

### `#[Auditable]`

Marks an entity as auditable.

```php
use BenMacha\AuditBundle\Attribute\Auditable;

#[Auditable(
    operations: ['create', 'update', 'delete'], // Operations to track
    ignoredFields: ['password', 'updatedAt'],   // Fields to ignore
    async: true                                 // Process asynchronously
)]
class User
{
    // ...
}
```

**Parameters:**
- `operations`: Array of operations to track (`create`, `update`, `delete`)
- `ignoredFields`: Array of field names to exclude from auditing
- `async`: Whether to process audit logs asynchronously

### `#[IgnoreAudit]`

Excludes specific fields or operations from auditing.

```php
use BenMacha\AuditBundle\Attribute\IgnoreAudit;

class User
{
    #[IgnoreAudit] // Never audit this field
    private string $password;
    
    #[IgnoreAudit(operations: ['update'])] // Don't audit on updates
    private \DateTime $createdAt;
}
```

**Parameters:**
- `operations`: Array of operations to ignore for this field

### `#[AuditSensitive]`

Marks fields as sensitive for special handling.

```php
use BenMacha\AuditBundle\Attribute\AuditSensitive;

class User
{
    #[AuditSensitive(
        encrypt: true,           // Encrypt the value
        mask: true,             // Mask in UI (show as ***)
        hashAlgorithm: 'sha256' // Hash algorithm for encryption
    )]
    private string $socialSecurityNumber;
}
```

**Parameters:**
- `encrypt`: Whether to encrypt the field value
- `mask`: Whether to mask the value in the UI
- `hashAlgorithm`: Algorithm for hashing sensitive data

### `#[AuditMetadata]`

Adds custom metadata to audit logs.

```php
use BenMacha\AuditBundle\Attribute\AuditMetadata;

class User
{
    #[AuditMetadata(
        tags: ['pii', 'gdpr'],           // Custom tags
        indexed: true,                   // Index for searching
        ttl: 2592000,                   // TTL in seconds (30 days)
        customData: ['department' => 'HR'] // Additional metadata
    )]
    private string $email;
}
```

**Parameters:**
- `tags`: Array of custom tags for categorization
- `indexed`: Whether to index this field for searching
- `ttl`: Time-to-live in seconds
- `customData`: Additional custom metadata

### `#[AuditContext]`

Provides additional context for audit operations.

```php
use BenMacha\AuditBundle\Attribute\AuditContext;

#[AuditContext(
    reason: 'User profile update',
    category: 'user_management',
    priority: 'high',
    metadata: ['source' => 'admin_panel']
)]
class User
{
    // ...
}
```

**Parameters:**
- `reason`: Human-readable reason for the change
- `category`: Category for grouping related changes
- `priority`: Priority level (`low`, `medium`, `high`, `critical`)
- `metadata`: Additional context metadata

## Advanced Usage

### Event Subscribers

Listen to audit events for custom processing:

```php
<?php

namespace App\EventSubscriber;

use BenMacha\AuditBundle\Event\AuditEvent;
use BenMacha\AuditBundle\Event\AuditEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuditSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AuditEvents::PRE_AUDIT => 'onPreAudit',
            AuditEvents::POST_AUDIT => 'onPostAudit',
            AuditEvents::ROLLBACK => 'onRollback',
        ];
    }
    
    public function onPreAudit(AuditEvent $event): void
    {
        // Modify audit data before saving
        $auditLog = $event->getAuditLog();
        $auditLog->addMetadata('processed_by', 'custom_subscriber');
    }
    
    public function onPostAudit(AuditEvent $event): void
    {
        // React to audit log creation
        $this->notificationService->sendAuditNotification($event->getAuditLog());
    }
    
    public function onRollback(AuditEvent $event): void
    {
        // Handle rollback operations
        $this->logger->info('Entity rolled back', [
            'entity' => $event->getEntityClass(),
            'id' => $event->getEntityId()
        ]);
    }
}
```

### Manual Audit Logging

Log changes manually when needed:

```php
<?php

namespace App\Service;

use BenMacha\AuditBundle\Service\AuditService;

class UserService
{
    public function __construct(
        private AuditService $auditService
    ) {}
    
    public function updateUserProfile(User $user, array $data): void
    {
        $oldData = $this->extractUserData($user);
        
        // Update user
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        
        // Manual audit logging
        $this->auditService->logEntityChange(
            $user,
            'update',
            $oldData,
            $this->extractUserData($user),
            'Profile updated via API'
        );
    }
}
```

### Custom Rollback Logic

Implement custom rollback behavior:

```php
<?php

namespace App\Service;

use BenMacha\AuditBundle\Service\AuditService;
use BenMacha\AuditBundle\Entity\AuditLog;

class CustomRollbackService
{
    public function __construct(
        private AuditService $auditService
    ) {}
    
    public function rollbackWithValidation(AuditLog $auditLog): bool
    {
        // Custom validation logic
        if (!$this->canRollback($auditLog)) {
            throw new \Exception('Rollback not allowed');
        }
        
        // Perform rollback
        return $this->auditService->rollbackEntity($auditLog);
    }
    
    private function canRollback(AuditLog $auditLog): bool
    {
        // Implement your validation logic
        return $auditLog->getCreatedAt() > new \DateTime('-24 hours');
    }
}
```

### Flushing Audit Data

Manage audit data lifecycle:

```php
<?php

namespace App\Command;

use BenMacha\AuditBundle\Service\AuditCleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AuditCleanupCommand extends Command
{
    protected static $defaultName = 'audit:cleanup';
    
    public function __construct(
        private AuditCleanupService $cleanupService
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Clean up old audit logs
        $deleted = $this->cleanupService->cleanupExpiredLogs();
        $output->writeln("Deleted {$deleted} expired audit logs");
        
        // Optimize audit tables
        $this->cleanupService->optimizeTables();
        $output->writeln('Optimized audit tables');
        
        return Command::SUCCESS;
    }
}
```

### Customizing Views

Override default templates:

1. Create `templates/bundles/AuditBundle/` directory
2. Copy templates from `vendor/benmacha/symfony-audit-bundle/src/Resources/views/`
3. Customize as needed

```twig
{# templates/bundles/AuditBundle/audit/index.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}Custom Audit Logs{% endblock %}

{% block body %}
    <div class="custom-audit-container">
        <h1>My Custom Audit Interface</h1>
        
        {# Include original content with modifications #}
        {% include '@Audit/audit/_table.html.twig' %}
    </div>
{% endblock %}
```

## API Reference

### REST Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/audit/logs` | Get all audit logs |
| GET | `/api/audit/logs/{id}` | Get specific audit log |
| GET | `/api/audit/logs/entity/{class}/{id}` | Get logs for entity |
| POST | `/api/audit/rollback/{id}` | Rollback to audit log |
| DELETE | `/api/audit/logs/{id}` | Delete audit log |
| GET | `/api/audit/stats` | Get audit statistics |

### Query Parameters

- `limit`: Number of results (default: 25)
- `offset`: Offset for pagination (default: 0)
- `entity`: Filter by entity class
- `operation`: Filter by operation type
- `user`: Filter by user ID
- `from`: Start date (ISO 8601)
- `to`: End date (ISO 8601)

### Response Format

```json
{
    "data": [
        {
            "id": 123,
            "entityClass": "App\\Entity\\User",
            "entityId": "456",
            "operation": "update",
            "oldValues": {"email": "old@example.com"},
            "newValues": {"email": "new@example.com"},
            "userId": 789,
            "ipAddress": "192.168.1.1",
            "userAgent": "Mozilla/5.0...",
            "createdAt": "2024-01-15T10:30:00Z",
            "metadata": {"reason": "Profile update"}
        }
    ],
    "total": 1,
    "limit": 25,
    "offset": 0
}
```

## Performance Optimization

### Asynchronous Processing

Enable async processing for high-traffic applications:

```yaml
# config/packages/audit.yaml
audit:
    async_processing: true
    
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            audit: '%env(MESSENGER_TRANSPORT_DSN)%'
        
        routing:
            'BenMacha\AuditBundle\Message\AuditMessage': audit
```

### Database Optimization

1. **Indexing**: The bundle automatically creates optimized indexes
2. **Partitioning**: Consider table partitioning for large datasets
3. **Archiving**: Use the cleanup service to archive old data

### Memory Management

```php
// Batch processing for large operations
$batchSize = 100;
for ($i = 0; $i < $totalRecords; $i += $batchSize) {
    $entities = $repository->findBy([], null, $batchSize, $i);
    
    foreach ($entities as $entity) {
        // Process entity
    }
    
    $entityManager->flush();
    $entityManager->clear(); // Clear memory
}
```

## Security Considerations

### Access Control

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/audit, roles: ROLE_AUDITOR }
        - { path: ^/api/audit, roles: ROLE_API_USER }
```

### Data Protection

1. **Encryption**: Use `#[AuditSensitive]` for sensitive fields
2. **Masking**: Hide sensitive data in UI
3. **Retention**: Configure appropriate retention periods
4. **Access Logging**: All access is automatically logged

## Troubleshooting

### Common Issues

1. **Missing Audit Logs**
   - Check entity has `#[Auditable]` attribute
   - Verify bundle is enabled
   - Check database permissions

2. **Performance Issues**
   - Enable async processing
   - Optimize database indexes
   - Reduce retention period

3. **Memory Issues**
   - Use batch processing
   - Clear entity manager regularly
   - Increase PHP memory limit

### Debug Mode

Enable debug logging:

```yaml
# config/packages/dev/monolog.yaml
monolog:
    handlers:
        audit:
            type: stream
            path: '%kernel.logs_dir%/audit.log'
            level: debug
            channels: ['audit']
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

### Development Setup

```bash
git clone https://github.com/benmacha/symfony-audit-bundle.git
cd symfony-audit-bundle
composer install
php bin/phpunit
```

### Code Quality

```bash
composer run quality  # Run all quality checks
composer run cs-fix   # Fix coding standards
composer run phpstan  # Static analysis
composer run psalm    # Additional static analysis
```

## License

This bundle is released under the MIT License. See [LICENSE](LICENSE) for details.

## Support

- 📖 [Documentation](https://github.com/benmacha/symfony-audit-bundle/wiki)
- 🐛 [Issue Tracker](https://github.com/benmacha/symfony-audit-bundle/issues)
- 💬 [Discussions](https://github.com/benmacha/symfony-audit-bundle/discussions)
- 📧 [Email Support](mailto:ben@example.com)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.