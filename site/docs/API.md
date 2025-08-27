---
layout: default
title: API Reference
permalink: /docs/api/
nav_order: 5
---

# API Reference

Complete reference for the Symfony Audit Bundle REST API and PHP services.

## REST API Endpoints

### Authentication

All API endpoints require authentication. Include your API token in the Authorization header:

```bash
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
     http://your-app.com/api/audit
```

### Get Audit Entries

**GET** `/api/audit`

Retrieve audit entries with optional filtering.

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|----------|
| `entity` | string | Filter by entity class | `User` |
| `entityId` | integer | Filter by entity ID | `123` |
| `action` | string | Filter by action type | `create`, `update`, `delete` |
| `userId` | integer | Filter by user ID | `456` |
| `from` | datetime | Start date filter | `2023-01-01T00:00:00Z` |
| `to` | datetime | End date filter | `2023-12-31T23:59:59Z` |
| `page` | integer | Page number (default: 1) | `2` |
| `limit` | integer | Items per page (default: 20, max: 100) | `50` |
| `sort` | string | Sort field | `createdAt`, `action`, `entity` |
| `order` | string | Sort order | `asc`, `desc` |

#### Example Requests

```bash
# Get all audit entries
curl http://your-app.com/api/audit

# Get audit entries for a specific user
curl "http://your-app.com/api/audit?entity=User&entityId=123"

# Get audit entries within date range
curl "http://your-app.com/api/audit?from=2023-01-01&to=2023-12-31"

# Get paginated results
curl "http://your-app.com/api/audit?page=2&limit=50"
```

#### Response Format

```json
{
  "data": [
    {
      "id": 1,
      "entity": "User",
      "entityId": "123",
      "action": "update",
      "changes": {
        "name": {
          "old": "John Doe",
          "new": "Jane Doe"
        },
        "email": {
          "old": "john@example.com",
          "new": "jane@example.com"
        }
      },
      "user": {
        "id": 456,
        "username": "admin"
      },
      "ip": "192.168.1.1",
      "userAgent": "Mozilla/5.0...",
      "createdAt": "2023-06-15T10:30:00Z"
    }
  ],
  "meta": {
    "total": 150,
    "page": 1,
    "limit": 20,
    "pages": 8
  }
}
```

### Get Single Audit Entry

**GET** `/api/audit/{id}`

Retrieve a specific audit entry by ID.

```bash
curl http://your-app.com/api/audit/123
```

#### Response Format

```json
{
  "id": 123,
  "entity": "User",
  "entityId": "456",
  "action": "create",
  "changes": {
    "name": {
      "old": null,
      "new": "John Doe"
    },
    "email": {
      "old": null,
      "new": "john@example.com"
    }
  },
  "user": {
    "id": 1,
    "username": "admin"
  },
  "ip": "192.168.1.1",
  "userAgent": "Mozilla/5.0...",
  "createdAt": "2023-06-15T10:30:00Z"
}
```

### Rollback Entity

**POST** `/api/audit/{id}/rollback`

Rollback an entity to a previous state using an audit entry.

```bash
curl -X POST \
     -H "Content-Type: application/json" \
     http://your-app.com/api/audit/123/rollback
```

#### Response Format

```json
{
  "success": true,
  "message": "Entity successfully rolled back",
  "rollbackAuditId": 124
}
```

### Get Entity History

**GET** `/api/audit/entity/{entity}/{id}/history`

Get complete audit history for a specific entity.

```bash
curl http://your-app.com/api/audit/entity/User/123/history
```

#### Response Format

```json
{
  "entity": "User",
  "entityId": "123",
  "history": [
    {
      "id": 1,
      "action": "create",
      "changes": {...},
      "user": {...},
      "createdAt": "2023-06-15T10:30:00Z"
    },
    {
      "id": 2,
      "action": "update",
      "changes": {...},
      "user": {...},
      "createdAt": "2023-06-16T14:20:00Z"
    }
  ]
}
```

### Get Statistics

**GET** `/api/audit/stats`

Get audit statistics and metrics.

```bash
curl http://your-app.com/api/audit/stats
```

#### Response Format

```json
{
  "totalEntries": 1500,
  "entriesThisMonth": 250,
  "topEntities": [
    {"entity": "User", "count": 800},
    {"entity": "Product", "count": 500},
    {"entity": "Order", "count": 200}
  ],
  "topActions": [
    {"action": "update", "count": 900},
    {"action": "create", "count": 400},
    {"action": "delete", "count": 200}
  ],
  "topUsers": [
    {"user": "admin", "count": 600},
    {"user": "manager", "count": 400},
    {"user": "user1", "count": 300}
  ]
}
```

## PHP Service API

### AuditService

The main service for interacting with audit data programmatically.

```php
<?php

use BenMacha\AuditBundle\Service\AuditService;

class MyController
{
    public function __construct(
        private AuditService $auditService
    ) {}
}
```

#### Methods

##### getAuditHistory()

Get audit history for an entity.

```php
public function getAuditHistory(
    string $entityClass,
    int|string $entityId,
    array $options = []
): array
```

**Parameters:**
- `$entityClass`: The entity class name
- `$entityId`: The entity ID
- `$options`: Optional filters (limit, offset, actions, etc.)

**Example:**

```php
$history = $this->auditService->getAuditHistory(
    entityClass: User::class,
    entityId: 123,
    options: [
        'limit' => 10,
        'actions' => ['update', 'delete']
    ]
);
```

##### findAuditEntries()

Find audit entries with custom criteria.

```php
public function findAuditEntries(array $criteria = []): array
```

**Example:**

```php
$entries = $this->auditService->findAuditEntries([
    'entity' => 'User',
    'action' => 'update',
    'from' => new \DateTime('-1 month'),
    'to' => new \DateTime(),
    'limit' => 50
]);
```

##### rollback()

Rollback an entity to a previous state.

```php
public function rollback(int $auditId): bool
```

**Example:**

```php
$success = $this->auditService->rollback(123);
```

##### getStatistics()

Get audit statistics.

```php
public function getStatistics(array $filters = []): array
```

**Example:**

```php
$stats = $this->auditService->getStatistics([
    'from' => new \DateTime('-1 year'),
    'entities' => ['User', 'Product']
]);
```

### AuditManager

Low-level service for audit operations.

```php
<?php

use BenMacha\AuditBundle\Manager\AuditManager;

class MyService
{
    public function __construct(
        private AuditManager $auditManager
    ) {}
}
```

#### Methods

##### createAuditEntry()

Manually create an audit entry.

```php
public function createAuditEntry(
    object $entity,
    string $action,
    array $changes = [],
    array $context = []
): void
```

**Example:**

```php
$this->auditManager->createAuditEntry(
    entity: $user,
    action: 'custom_action',
    changes: ['status' => ['old' => 'active', 'new' => 'suspended']],
    context: ['reason' => 'Policy violation']
);
```

##### isAuditable()

Check if an entity is auditable.

```php
public function isAuditable(object $entity): bool
```

**Example:**

```php
if ($this->auditManager->isAuditable($user)) {
    // Entity is auditable
}
```

### Event System

Listen to audit events for custom processing.

#### Available Events

- `audit.pre_create`: Before creating an audit entry
- `audit.post_create`: After creating an audit entry
- `audit.pre_rollback`: Before rolling back an entity
- `audit.post_rollback`: After rolling back an entity

#### Event Listener Example

```php
<?php

namespace App\EventListener;

use BenMacha\AuditBundle\Event\AuditEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'audit.post_create')]
class AuditEventListener
{
    public function onAuditCreate(AuditEvent $event): void
    {
        $auditEntry = $event->getAuditEntry();
        
        // Custom logic after audit entry creation
        if ($auditEntry->getAction() === 'delete') {
            // Send notification for deletions
            $this->notificationService->sendDeletionAlert($auditEntry);
        }
    }
}
```

## Error Handling

### HTTP Status Codes

- `200 OK`: Successful request
- `400 Bad Request`: Invalid parameters
- `401 Unauthorized`: Authentication required
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: Server error

### Error Response Format

```json
{
  "error": {
    "code": "INVALID_PARAMETER",
    "message": "The 'entity' parameter is required",
    "details": {
      "parameter": "entity",
      "expected": "string",
      "received": "null"
    }
  }
}
```

## Rate Limiting

API endpoints are rate-limited to prevent abuse:

- **Default limit**: 60 requests per minute per API key
- **Headers included in response**:
  - `X-RateLimit-Limit`: Request limit
  - `X-RateLimit-Remaining`: Remaining requests
  - `X-RateLimit-Reset`: Reset timestamp

## Pagination

All list endpoints support pagination:

- **Default page size**: 20 items
- **Maximum page size**: 100 items
- **Pagination metadata** included in response

## Next Steps

- [Advanced Usage]({{ site.baseurl }}/docs/advanced/) - Advanced features and customization
- [Performance]({{ site.baseurl }}/docs/performance/) - Optimization tips
- [Troubleshooting]({{ site.baseurl }}/docs/troubleshooting/) - Common issues and solutions