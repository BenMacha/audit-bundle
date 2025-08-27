# API Documentation

The Symfony Audit Bundle provides a comprehensive API for programmatic access to audit functionality. This document covers all available services, methods, and their usage.

## Table of Contents

- [Core Services](#core-services)
- [Repository Methods](#repository-methods)
- [Event System](#event-system)
- [Configuration API](#configuration-api)
- [Utility Classes](#utility-classes)
- [REST API Endpoints](#rest-api-endpoints)

## Core Services

### AuditManager

The main service for managing audit operations.

```php
namespace BenMacha\AuditBundle\Service;

class AuditManager
{
    /**
     * Enable audit logging globally
     */
    public function enable(): void
    
    /**
     * Disable audit logging globally
     */
    public function disable(): void
    
    /**
     * Check if audit logging is enabled
     */
    public function isEnabled(): bool
    
    /**
     * Log an audit entry manually
     * 
     * @param object $entity The entity being audited
     * @param string $operation The operation (create, update, delete)
     * @param array $changes Array of field changes
     * @param \DateTime|null $timestamp Optional timestamp
     * @param array $context Additional context data
     */
    public function logManual(
        object $entity,
        string $operation,
        array $changes = [],
        ?\DateTime $timestamp = null,
        array $context = []
    ): void
    
    /**
     * Log a bulk operation
     * 
     * @param string $entityClass The entity class name
     * @param string $operation The bulk operation type
     * @param array $metadata Operation metadata
     */
    public function logBulkOperation(
        string $entityClass,
        string $operation,
        array $metadata = []
    ): void
    
    /**
     * Flush pending audit logs
     */
    public function flush(): void
    
    /**
     * Get audit statistics
     * 
     * @param \DateTime|null $from Start date
     * @param \DateTime|null $to End date
     * @return array Statistics data
     */
    public function getStatistics(?\DateTime $from = null, ?\DateTime $to = null): array
    
    /**
     * Rollback an entity to a previous state
     * 
     * @param object $entity The entity to rollback
     * @param int $auditLogId The audit log ID to rollback to
     * @param bool $preview Whether to preview changes without applying
     * @return array Rollback result
     */
    public function rollback(object $entity, int $auditLogId, bool $preview = false): array
}
```

#### Usage Examples

```php
use BenMacha\AuditBundle\Service\AuditManager;

// Basic usage
$auditManager = $container->get(AuditManager::class);

// Manual logging
$user = new User();
$user->setName('John Doe');
$auditManager->logManual($user, 'create', ['name' => ['old' => null, 'new' => 'John Doe']]);

// Bulk operation logging
$auditManager->logBulkOperation('App\\Entity\\Product', 'bulk_update', ['count' => 100]);

// Disable auditing temporarily
$auditManager->disable();
// ... perform operations without auditing
$auditManager->enable();

// Get statistics
$stats = $auditManager->getStatistics(
    new \DateTime('-30 days'),
    new \DateTime()
);
```

### ConfigurationService

Service for managing audit configuration.

```php
namespace BenMacha\AuditBundle\Service;

class ConfigurationService
{
    /**
     * Check if auditing is globally enabled
     */
    public function isGloballyEnabled(): bool
    
    /**
     * Check if auditing is enabled for a specific entity
     * 
     * @param string $entityClass The entity class name
     */
    public function isAuditEnabled(string $entityClass): bool
    
    /**
     * Get auditable entities
     * 
     * @return array List of auditable entity classes
     */
    public function getAuditableEntities(): array
    
    /**
     * Get audit configuration for an entity
     * 
     * @param string $entityClass The entity class name
     * @return array Configuration data
     */
    public function getEntityConfig(string $entityClass): array
    
    /**
     * Check if asynchronous processing is enabled
     */
    public function isAsyncEnabled(): bool
    
    /**
     * Get retention period in days
     */
    public function getRetentionDays(): int
    
    /**
     * Get ignored fields for an entity
     * 
     * @param string $entityClass The entity class name
     * @return array List of ignored field names
     */
    public function getIgnoredFields(string $entityClass): array
    
    /**
     * Get sensitive fields for an entity
     * 
     * @param string $entityClass The entity class name
     * @return array List of sensitive field names
     */
    public function getSensitiveFields(string $entityClass): array
}
```

#### Usage Examples

```php
use BenMacha\AuditBundle\Service\ConfigurationService;

$configService = $container->get(ConfigurationService::class);

// Check if entity is auditable
if ($configService->isAuditEnabled('App\\Entity\\User')) {
    // Entity is auditable
}

// Get all auditable entities
$entities = $configService->getAuditableEntities();

// Get entity-specific configuration
$config = $configService->getEntityConfig('App\\Entity\\Product');

// Get ignored fields
$ignoredFields = $configService->getIgnoredFields('App\\Entity\\User');
```

### MetadataCollector

Service for collecting entity metadata.

```php
namespace BenMacha\AuditBundle\Service;

class MetadataCollector
{
    /**
     * Collect metadata for an entity
     * 
     * @param object $entity The entity
     * @return array Metadata array
     */
    public function collectMetadata(object $entity): array
    
    /**
     * Get field metadata for an entity class
     * 
     * @param string $entityClass The entity class name
     * @return array Field metadata
     */
    public function getFieldMetadata(string $entityClass): array
    
    /**
     * Check if a field should be audited
     * 
     * @param string $entityClass The entity class name
     * @param string $fieldName The field name
     * @return bool Whether the field should be audited
     */
    public function shouldAuditField(string $entityClass, string $fieldName): bool
    
    /**
     * Check if a field is sensitive
     * 
     * @param string $entityClass The entity class name
     * @param string $fieldName The field name
     * @return bool Whether the field is sensitive
     */
    public function isSensitiveField(string $entityClass, string $fieldName): bool
}
```

## Repository Methods

### AuditLogRepository

Repository for querying audit logs.

```php
namespace BenMacha\AuditBundle\Repository;

class AuditLogRepository extends ServiceEntityRepository
{
    /**
     * Find audit logs for a specific entity
     * 
     * @param string $entityClass The entity class name
     * @param mixed $entityId The entity ID
     * @param int $limit Maximum number of results
     * @return AuditLog[] Array of audit logs
     */
    public function findByEntity(string $entityClass, $entityId, int $limit = 50): array
    
    /**
     * Find audit logs by user
     * 
     * @param mixed $userId The user ID
     * @param int $limit Maximum number of results
     * @return AuditLog[] Array of audit logs
     */
    public function findByUser($userId, int $limit = 50): array
    
    /**
     * Find audit logs within a date range
     * 
     * @param \DateTime $from Start date
     * @param \DateTime $to End date
     * @param int $limit Maximum number of results
     * @return AuditLog[] Array of audit logs
     */
    public function findByDateRange(\DateTime $from, \DateTime $to, int $limit = 100): array
    
    /**
     * Find recent audit logs
     * 
     * @param int $limit Maximum number of results
     * @return AuditLog[] Array of recent audit logs
     */
    public function findRecent(int $limit = 20): array
    
    /**
     * Get audit statistics
     * 
     * @param \DateTime|null $from Start date
     * @param \DateTime|null $to End date
     * @return array Statistics data
     */
    public function getStatistics(?\DateTime $from = null, ?\DateTime $to = null): array
    
    /**
     * Count audit logs by operation
     * 
     * @param \DateTime|null $from Start date
     * @param \DateTime|null $to End date
     * @return array Operation counts
     */
    public function countByOperation(?\DateTime $from = null, ?\DateTime $to = null): array
    
    /**
     * Find audit logs with advanced filtering
     * 
     * @param array $criteria Filter criteria
     * @param array $orderBy Order by fields
     * @param int $limit Maximum number of results
     * @param int $offset Result offset
     * @return AuditLog[] Array of audit logs
     */
    public function findWithFilters(
        array $criteria = [],
        array $orderBy = ['createdAt' => 'DESC'],
        int $limit = 50,
        int $offset = 0
    ): array
    
    /**
     * Clean up old audit logs
     * 
     * @param int $retentionDays Number of days to retain
     * @return int Number of deleted records
     */
    public function cleanup(int $retentionDays): int
}
```

#### Usage Examples

```php
use BenMacha\AuditBundle\Repository\AuditLogRepository;

$repository = $entityManager->getRepository(AuditLog::class);

// Find logs for a specific entity
$logs = $repository->findByEntity('App\\Entity\\User', 123);

// Find logs by user
$userLogs = $repository->findByUser(456);

// Find logs in date range
$logs = $repository->findByDateRange(
    new \DateTime('-7 days'),
    new \DateTime()
);

// Get statistics
$stats = $repository->getStatistics();

// Advanced filtering
$logs = $repository->findWithFilters([
    'operation' => 'update',
    'entity' => 'App\\Entity\\Product'
], ['createdAt' => 'DESC'], 25, 0);
```

### AuditChangeRepository

Repository for querying audit changes.

```php
namespace BenMacha\AuditBundle\Repository;

class AuditChangeRepository extends ServiceEntityRepository
{
    /**
     * Find changes for a specific audit log
     * 
     * @param int $auditLogId The audit log ID
     * @return AuditChange[] Array of changes
     */
    public function findByAuditLog(int $auditLogId): array
    
    /**
     * Find changes for a specific field
     * 
     * @param string $entityClass The entity class name
     * @param mixed $entityId The entity ID
     * @param string $fieldName The field name
     * @return AuditChange[] Array of changes
     */
    public function findByField(string $entityClass, $entityId, string $fieldName): array
    
    /**
     * Get field history
     * 
     * @param string $entityClass The entity class name
     * @param mixed $entityId The entity ID
     * @param string $fieldName The field name
     * @param int $limit Maximum number of results
     * @return array Field history
     */
    public function getFieldHistory(
        string $entityClass,
        $entityId,
        string $fieldName,
        int $limit = 10
    ): array
}
```

## Event System

### AuditEvent

The main event class for audit operations.

```php
namespace BenMacha\AuditBundle\Event;

class AuditEvent extends Event
{
    public const PRE_AUDIT = 'audit.pre_audit';
    public const POST_AUDIT = 'audit.post_audit';
    public const PRE_ROLLBACK = 'audit.pre_rollback';
    public const POST_ROLLBACK = 'audit.post_rollback';
    
    /**
     * Get the entity being audited
     */
    public function getEntity(): object
    
    /**
     * Get the operation type
     */
    public function getOperation(): string
    
    /**
     * Get the changes array
     */
    public function getChanges(): array
    
    /**
     * Set the changes array
     */
    public function setChanges(array $changes): void
    
    /**
     * Get the audit context
     */
    public function getContext(): array
    
    /**
     * Set the audit context
     */
    public function setContext(array $context): void
    
    /**
     * Skip the audit operation
     */
    public function skipAudit(): void
    
    /**
     * Check if audit should be skipped
     */
    public function shouldSkipAudit(): bool
    
    /**
     * Get the user performing the operation
     */
    public function getUser(): ?UserInterface
    
    /**
     * Get the timestamp
     */
    public function getTimestamp(): \DateTime
}
```

### Event Subscribers

```php
use BenMacha\AuditBundle\Event\AuditEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomAuditSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AuditEvent::PRE_AUDIT => 'onPreAudit',
            AuditEvent::POST_AUDIT => 'onPostAudit',
            AuditEvent::PRE_ROLLBACK => 'onPreRollback',
            AuditEvent::POST_ROLLBACK => 'onPostRollback',
        ];
    }
    
    public function onPreAudit(AuditEvent $event): void
    {
        // Modify audit data before saving
        $changes = $event->getChanges();
        // ... modify changes
        $event->setChanges($changes);
    }
    
    public function onPostAudit(AuditEvent $event): void
    {
        // React to audit log creation
        $entity = $event->getEntity();
        $operation = $event->getOperation();
        
        // Send notifications, update caches, etc.
    }
    
    public function onPreRollback(AuditEvent $event): void
    {
        // Validate rollback operation
        if (!$this->canRollback($event->getEntity())) {
            $event->skipAudit();
        }
    }
    
    public function onPostRollback(AuditEvent $event): void
    {
        // React to successful rollback
        $this->notifyRollback($event->getEntity());
    }
}
```

## Configuration API

### Runtime Configuration

```php
use BenMacha\AuditBundle\Service\ConfigurationService;

// Get configuration service
$config = $container->get(ConfigurationService::class);

// Check global settings
$isEnabled = $config->isGloballyEnabled();
$isAsync = $config->isAsyncEnabled();
$retentionDays = $config->getRetentionDays();

// Entity-specific settings
$entityConfig = $config->getEntityConfig('App\\Entity\\User');
$ignoredFields = $config->getIgnoredFields('App\\Entity\\User');
$sensitiveFields = $config->getSensitiveFields('App\\Entity\\User');
```

### Dynamic Configuration

```php
use BenMacha\AuditBundle\Entity\AuditConfig;

// Create dynamic configuration
$auditConfig = new AuditConfig();
$auditConfig->setEntity('App\\Entity\\CustomEntity');
$auditConfig->setEnabled(true);
$auditConfig->setOperations(['create', 'update']);
$auditConfig->setIgnoredFields(['tempField', 'cacheData']);

$entityManager->persist($auditConfig);
$entityManager->flush();
```

## Utility Classes

### AuditHelper

```php
namespace BenMacha\AuditBundle\Util;

class AuditHelper
{
    /**
     * Format audit changes for display
     * 
     * @param array $changes The changes array
     * @return array Formatted changes
     */
    public static function formatChanges(array $changes): array
    
    /**
     * Mask sensitive data
     * 
     * @param mixed $value The value to mask
     * @param string $maskChar The masking character
     * @return string Masked value
     */
    public static function maskSensitiveData($value, string $maskChar = '*'): string
    
    /**
     * Generate audit summary
     * 
     * @param array $changes The changes array
     * @return string Summary text
     */
    public static function generateSummary(array $changes): string
    
    /**
     * Calculate change percentage
     * 
     * @param array $changes The changes array
     * @param int $totalFields Total number of fields
     * @return float Change percentage
     */
    public static function calculateChangePercentage(array $changes, int $totalFields): float
}
```

## REST API Endpoints

The bundle provides REST API endpoints for accessing audit data.

### Authentication

All API endpoints require authentication. Use the configured security settings:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api/audit
            stateless: true
            jwt: ~
```

### Endpoints

#### GET /api/audit/logs

Retrieve audit logs with optional filtering.

**Parameters:**
- `entity` (string): Filter by entity class
- `entityId` (mixed): Filter by entity ID
- `operation` (string): Filter by operation type
- `user` (mixed): Filter by user ID
- `from` (datetime): Start date filter
- `to` (datetime): End date filter
- `limit` (int): Maximum results (default: 50)
- `offset` (int): Result offset (default: 0)

**Response:**
```json
{
    "data": [
        {
            "id": 123,
            "entity": "App\\Entity\\User",
            "entityId": "456",
            "operation": "update",
            "userId": "789",
            "ipAddress": "192.168.1.1",
            "userAgent": "Mozilla/5.0...",
            "createdAt": "2024-01-15T10:30:00Z",
            "changes": [
                {
                    "field": "email",
                    "oldValue": "old@example.com",
                    "newValue": "new@example.com"
                }
            ]
        }
    ],
    "total": 1,
    "limit": 50,
    "offset": 0
}
```

#### GET /api/audit/logs/{id}

Retrieve a specific audit log.

**Response:**
```json
{
    "id": 123,
    "entity": "App\\Entity\\User",
    "entityId": "456",
    "operation": "update",
    "userId": "789",
    "ipAddress": "192.168.1.1",
    "userAgent": "Mozilla/5.0...",
    "createdAt": "2024-01-15T10:30:00Z",
    "changes": [
        {
            "field": "email",
            "oldValue": "old@example.com",
            "newValue": "new@example.com"
        }
    ]
}
```

#### GET /api/audit/entity/{entityClass}/{entityId}

Retrieve audit logs for a specific entity.

**Response:**
```json
{
    "entity": "App\\Entity\\User",
    "entityId": "456",
    "logs": [
        {
            "id": 123,
            "operation": "update",
            "userId": "789",
            "createdAt": "2024-01-15T10:30:00Z",
            "changes": [...]
        }
    ]
}
```

#### GET /api/audit/statistics

Retrieve audit statistics.

**Parameters:**
- `from` (datetime): Start date
- `to` (datetime): End date

**Response:**
```json
{
    "totalLogs": 1500,
    "operationCounts": {
        "create": 500,
        "update": 800,
        "delete": 200
    },
    "entityCounts": {
        "App\\Entity\\User": 600,
        "App\\Entity\\Product": 900
    },
    "dailyActivity": [
        {
            "date": "2024-01-15",
            "count": 45
        }
    ]
}
```

#### POST /api/audit/rollback/{id}

Rollback an entity to a previous state.

**Request Body:**
```json
{
    "preview": false,
    "reason": "Data correction"
}
```

**Response:**
```json
{
    "success": true,
    "changes": {
        "email": {
            "from": "new@example.com",
            "to": "old@example.com"
        }
    },
    "rollbackLogId": 124
}
```

### Error Responses

All endpoints return consistent error responses:

```json
{
    "error": {
        "code": "AUDIT_001",
        "message": "Entity not found",
        "details": {
            "entity": "App\\Entity\\User",
            "id": "999"
        }
    }
}
```

### Rate Limiting

API endpoints are rate-limited based on configuration:

```yaml
# config/packages/audit.yaml
audit:
    api:
        rate_limit: 100  # requests per minute
```

## Error Codes

| Code | Description |
|------|-------------|
| AUDIT_001 | Entity not found |
| AUDIT_002 | Invalid operation |
| AUDIT_003 | Access denied |
| AUDIT_004 | Invalid date range |
| AUDIT_005 | Rollback not allowed |
| AUDIT_006 | Rate limit exceeded |
| AUDIT_007 | Invalid entity class |
| AUDIT_008 | Audit log not found |

## Examples

### Complete Integration Example

```php
use BenMacha\AuditBundle\Service\AuditManager;
use BenMacha\AuditBundle\Service\ConfigurationService;
use BenMacha\AuditBundle\Repository\AuditLogRepository;

class UserService
{
    public function __construct(
        private AuditManager $auditManager,
        private ConfigurationService $configService,
        private AuditLogRepository $auditLogRepository,
        private EntityManagerInterface $entityManager
    ) {}
    
    public function updateUser(User $user, array $data): void
    {
        // Check if auditing is enabled
        if (!$this->configService->isAuditEnabled(User::class)) {
            // Handle non-audited update
            $this->updateUserData($user, $data);
            return;
        }
        
        // Capture changes for manual logging if needed
        $originalData = $this->captureUserData($user);
        
        // Update user
        $this->updateUserData($user, $data);
        
        // Manual audit logging with context
        $changes = $this->calculateChanges($originalData, $data);
        $this->auditManager->logManual(
            $user,
            'update',
            $changes,
            new \DateTime(),
            ['source' => 'api', 'batch' => false]
        );
    }
    
    public function getUserAuditHistory(User $user): array
    {
        return $this->auditLogRepository->findByEntity(
            User::class,
            $user->getId(),
            100
        );
    }
    
    public function rollbackUser(User $user, int $auditLogId): bool
    {
        try {
            $result = $this->auditManager->rollback($user, $auditLogId);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            // Log error and return false
            return false;
        }
    }
    
    private function captureUserData(User $user): array
    {
        return [
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ];
    }
    
    private function updateUserData(User $user, array $data): void
    {
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
    
    private function calculateChanges(array $original, array $new): array
    {
        $changes = [];
        
        foreach ($new as $field => $value) {
            if (isset($original[$field]) && $original[$field] !== $value) {
                $changes[$field] = [
                    'old' => $original[$field],
                    'new' => $value
                ];
            }
        }
        
        return $changes;
    }
}
```

### Batch Operations Example

```php
use BenMacha\AuditBundle\Service\AuditManager;

class ProductBatchService
{
    public function __construct(
        private AuditManager $auditManager,
        private EntityManagerInterface $entityManager
    ) {}
    
    public function bulkUpdatePrices(array $products, float $multiplier): void
    {
        // Disable automatic auditing for performance
        $this->auditManager->disable();
        
        $updatedCount = 0;
        $changes = [];
        
        try {
            foreach ($products as $product) {
                $oldPrice = $product->getPrice();
                $newPrice = $oldPrice * $multiplier;
                
                $product->setPrice($newPrice);
                $this->entityManager->persist($product);
                
                $changes[] = [
                    'id' => $product->getId(),
                    'oldPrice' => $oldPrice,
                    'newPrice' => $newPrice
                ];
                
                $updatedCount++;
                
                // Flush in batches
                if ($updatedCount % 100 === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            }
            
            // Final flush
            $this->entityManager->flush();
            
            // Re-enable auditing
            $this->auditManager->enable();
            
            // Log bulk operation
            $this->auditManager->logBulkOperation(
                'App\\Entity\\Product',
                'bulk_price_update',
                [
                    'count' => $updatedCount,
                    'multiplier' => $multiplier,
                    'changes' => $changes
                ]
            );
            
        } catch (\Exception $e) {
            // Re-enable auditing on error
            $this->auditManager->enable();
            throw $e;
        }
    }
}
```

### Event-Driven Audit Processing

```php
use BenMacha\AuditBundle\Event\AuditEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;

class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {}
    
    public static function getSubscribedEvents(): array
    {
        return [
            AuditEvent::POST_AUDIT => [
                ['onSecurityAudit', 100], // High priority
                ['onGeneralAudit', 0],    // Normal priority
            ],
        ];
    }
    
    public function onSecurityAudit(AuditEvent $event): void
    {
        $entity = $event->getEntity();
        $operation = $event->getOperation();
        $changes = $event->getChanges();
        
        // Monitor sensitive operations
        if ($entity instanceof User) {
            $this->handleUserAudit($event);
        } elseif ($entity instanceof AdminUser) {
            $this->handleAdminAudit($event);
        }
        
        // Monitor privilege escalations
        if (isset($changes['roles'])) {
            $this->handleRoleChange($event);
        }
    }
    
    public function onGeneralAudit(AuditEvent $event): void
    {
        // Log all audit events
        $this->logger->info('Audit event processed', [
            'entity' => get_class($event->getEntity()),
            'operation' => $event->getOperation(),
            'user' => $event->getUser()?->getUserIdentifier(),
            'timestamp' => $event->getTimestamp()->format('c')
        ]);
    }
    
    private function handleUserAudit(AuditEvent $event): void
    {
        $changes = $event->getChanges();
        
        // Alert on password changes
        if (isset($changes['password'])) {
            $this->sendSecurityAlert(
                'Password changed for user: ' . $event->getEntity()->getEmail(),
                $event
            );
        }
        
        // Alert on email changes
        if (isset($changes['email'])) {
            $this->sendSecurityAlert(
                'Email changed for user',
                $event
            );
        }
    }
    
    private function handleAdminAudit(AuditEvent $event): void
    {
        // All admin changes are critical
        $this->sendSecurityAlert(
            'Admin user modified: ' . $event->getOperation(),
            $event
        );
    }
    
    private function handleRoleChange(AuditEvent $event): void
    {
        $changes = $event->getChanges();
        $roleChange = $changes['roles'];
        
        $oldRoles = $roleChange['old'] ?? [];
        $newRoles = $roleChange['new'] ?? [];
        
        $addedRoles = array_diff($newRoles, $oldRoles);
        $removedRoles = array_diff($oldRoles, $newRoles);
        
        if (!empty($addedRoles) || !empty($removedRoles)) {
            $this->sendSecurityAlert(
                'Role change detected',
                $event,
                [
                    'added' => $addedRoles,
                    'removed' => $removedRoles
                ]
            );
        }
    }
    
    private function sendSecurityAlert(string $message, AuditEvent $event, array $extra = []): void
    {
        // Implementation depends on your notification system
        $this->logger->warning($message, [
            'entity' => get_class($event->getEntity()),
            'operation' => $event->getOperation(),
            'user' => $event->getUser()?->getUserIdentifier(),
            'ip' => $event->getContext()['ip'] ?? 'unknown',
            'extra' => $extra
        ]);
    }
}
```

## Performance Considerations

### Optimizing Queries

```php
// Use specific queries instead of loading full entities
$repository = $entityManager->getRepository(AuditLog::class);

// Good: Select only needed fields
$qb = $repository->createQueryBuilder('a')
    ->select('a.id, a.operation, a.createdAt, a.entity, a.entityId')
    ->where('a.entity = :entity')
    ->andWhere('a.entityId = :entityId')
    ->setParameter('entity', User::class)
    ->setParameter('entityId', $userId)
    ->orderBy('a.createdAt', 'DESC')
    ->setMaxResults(50);

$results = $qb->getQuery()->getArrayResult();

// Bad: Loading full entities when not needed
$logs = $repository->findBy([
    'entity' => User::class,
    'entityId' => $userId
], ['createdAt' => 'DESC'], 50);
```

### Caching Strategies

```php
use Symfony\Contracts\Cache\CacheInterface;

class CachedAuditService
{
    public function __construct(
        private AuditLogRepository $repository,
        private CacheInterface $cache
    ) {}
    
    public function getEntityAuditSummary(string $entityClass, $entityId): array
    {
        $cacheKey = sprintf('audit_summary_%s_%s', 
            str_replace('\\', '_', $entityClass), 
            $entityId
        );
        
        return $this->cache->get($cacheKey, function() use ($entityClass, $entityId) {
            $logs = $this->repository->findByEntity($entityClass, $entityId, 10);
            
            return [
                'total_changes' => count($logs),
                'last_modified' => $logs[0]?->getCreatedAt(),
                'operations' => array_count_values(
                    array_map(fn($log) => $log->getOperation(), $logs)
                )
            ];
        }, 300); // Cache for 5 minutes
    }
}
```

## Testing the API

### Unit Tests

```php
use PHPUnit\Framework\TestCase;
use BenMacha\AuditBundle\Service\AuditManager;

class AuditManagerTest extends TestCase
{
    private AuditManager $auditManager;
    
    protected function setUp(): void
    {
        // Setup test dependencies
        $this->auditManager = $this->createAuditManager();
    }
    
    public function testManualLogging(): void
    {
        $entity = new User();
        $entity->setName('Test User');
        
        $this->auditManager->logManual(
            $entity,
            'create',
            ['name' => ['old' => null, 'new' => 'Test User']]
        );
        
        // Assert audit log was created
        $this->assertTrue(true); // Replace with actual assertions
    }
    
    public function testBulkOperationLogging(): void
    {
        $this->auditManager->logBulkOperation(
            'App\\Entity\\Product',
            'bulk_update',
            ['count' => 100]
        );
        
        // Assert bulk operation was logged
        $this->assertTrue(true); // Replace with actual assertions
    }
}
```

### Integration Tests

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuditIntegrationTest extends KernelTestCase
{
    public function testEntityAuditingFlow(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $auditRepository = $entityManager->getRepository(AuditLog::class);
        
        // Create and persist entity
        $user = new User();
        $user->setName('Integration Test User');
        $user->setEmail('test@example.com');
        
        $entityManager->persist($user);
        $entityManager->flush();
        
        // Check audit log was created
        $auditLogs = $auditRepository->findByEntity(
            User::class,
            $user->getId()
        );
        
        $this->assertCount(1, $auditLogs);
        $this->assertEquals('create', $auditLogs[0]->getOperation());
    }
}
```

This comprehensive API documentation provides all the necessary information for developers to effectively use and integrate the Symfony Audit Bundle into their applications.