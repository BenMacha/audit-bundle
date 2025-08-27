---
layout: default
title: Configuration
permalink: /docs/configuration/
nav_order: 4
---

# Configuration

Customize the Symfony Audit Bundle to fit your application's needs.

## Basic Configuration

Create or edit `config/packages/audit.yaml`:

```yaml
audit:
    # Enable/disable audit logging globally
    enabled: true
    
    # Configure audit table name
    table_name: 'audit_log'
    
    # Maximum number of audit entries to keep (0 = unlimited)
    max_entries: 10000
    
    # Automatically clean up old entries
    auto_cleanup: true
    
    # Clean up entries older than X days
    cleanup_days: 365
```

## Entity Configuration

### Global Entity Settings

```yaml
audit:
    entities:
        # Audit all entities in these namespaces
        namespaces:
            - 'App\Entity\User'
            - 'App\Entity\Product'
        
        # Exclude specific entities
        exclude:
            - 'App\Entity\Log'
            - 'App\Entity\Cache'
```

### Per-Entity Configuration

```yaml
audit:
    entities:
        'App\Entity\User':
            # Enable/disable auditing for this entity
            enabled: true
            
            # Fields to exclude from auditing
            exclude_fields:
                - 'password'
                - 'salt'
                - 'lastLogin'
            
            # Only audit specific fields
            include_fields:
                - 'name'
                - 'email'
                - 'roles'
        
        'App\Entity\Product':
            enabled: true
            # Track only price and stock changes
            include_fields:
                - 'price'
                - 'stock'
```

## Action Configuration

```yaml
audit:
    actions:
        # Track entity creation
        create: true
        
        # Track entity updates
        update: true
        
        # Track entity deletion
        delete: true
        
        # Track entity restoration (if using soft deletes)
        restore: true
```

## User Context Configuration

```yaml
audit:
    user:
        # Automatically detect current user
        auto_detect: true
        
        # User provider service (if not using auto-detection)
        provider: 'app.audit.user_provider'
        
        # Fields to store from user entity
        fields:
            - 'id'
            - 'username'
            - 'email'
        
        # Anonymous user identifier
        anonymous_id: 'anonymous'
```

## Storage Configuration

### Database Storage (Default)

```yaml
audit:
    storage:
        type: 'database'
        
        # Custom entity manager (optional)
        entity_manager: 'audit'
        
        # Batch insert size for performance
        batch_size: 100
```

### File Storage

```yaml
audit:
    storage:
        type: 'file'
        
        # Directory to store audit files
        directory: '%kernel.project_dir%/var/audit'
        
        # File format (json, csv, xml)
        format: 'json'
        
        # Rotate files daily/weekly/monthly
        rotation: 'daily'
```

### Custom Storage

```yaml
audit:
    storage:
        type: 'custom'
        service: 'app.audit.custom_storage'
```

## Performance Configuration

```yaml
audit:
    performance:
        # Enable async processing
        async: true
        
        # Message queue transport
        transport: 'audit_queue'
        
        # Batch processing
        batch_processing: true
        batch_size: 50
        
        # Cache audit metadata
        cache_metadata: true
        cache_ttl: 3600
```

## Security Configuration

```yaml
audit:
    security:
        # Encrypt sensitive data
        encryption:
            enabled: true
            key: '%env(AUDIT_ENCRYPTION_KEY)%'
            algorithm: 'aes-256-gcm'
        
        # Hash user identifiers
        hash_user_ids: true
        
        # IP address tracking
        track_ip: true
        
        # User agent tracking
        track_user_agent: false
```

## Web Interface Configuration

```yaml
audit:
    web_interface:
        # Enable/disable web interface
        enabled: true
        
        # Route prefix
        route_prefix: '/audit'
        
        # Access control
        access_control:
            roles: ['ROLE_ADMIN', 'ROLE_AUDITOR']
        
        # Items per page
        items_per_page: 25
        
        # Date format
        date_format: 'Y-m-d H:i:s'
```

## API Configuration

```yaml
audit:
    api:
        # Enable/disable REST API
        enabled: true
        
        # Route prefix
        route_prefix: '/api/audit'
        
        # Access control
        access_control:
            roles: ['ROLE_API_USER']
        
        # Rate limiting
        rate_limit:
            enabled: true
            requests_per_minute: 60
        
        # Response format
        default_format: 'json'
        
        # Pagination
        max_per_page: 100
        default_per_page: 20
```

## Event Configuration

```yaml
audit:
    events:
        # Custom event listeners
        listeners:
            - 'app.audit.custom_listener'
        
        # Event priorities
        priorities:
            pre_audit: 0
            post_audit: 0
```

## Environment-Specific Configuration

### Development Environment

```yaml
# config/packages/dev/audit.yaml
audit:
    enabled: true
    max_entries: 1000
    auto_cleanup: false
    
    # More verbose logging in development
    debug: true
```

### Production Environment

```yaml
# config/packages/prod/audit.yaml
audit:
    enabled: true
    max_entries: 100000
    auto_cleanup: true
    cleanup_days: 365
    
    # Performance optimizations
    performance:
        async: true
        batch_processing: true
        cache_metadata: true
    
    # Security hardening
    security:
        encryption:
            enabled: true
        hash_user_ids: true
```

### Test Environment

```yaml
# config/packages/test/audit.yaml
audit:
    enabled: false  # Disable auditing in tests
```

## Custom Services

### Custom User Provider

```php
<?php

namespace App\Audit;

use BenMacha\AuditBundle\Provider\UserProviderInterface;
use Symfony\Component\Security\Core\Security;

class CustomUserProvider implements UserProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    public function getCurrentUser(): ?array
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'department' => $user->getDepartment(),
        ];
    }
}
```

### Custom Storage Provider

```php
<?php

namespace App\Audit;

use BenMacha\AuditBundle\Storage\StorageInterface;
use BenMacha\AuditBundle\Model\AuditEntry;

class CustomStorageProvider implements StorageInterface
{
    public function store(AuditEntry $entry): void
    {
        // Custom storage logic (e.g., send to external service)
    }

    public function retrieve(array $criteria): array
    {
        // Custom retrieval logic
    }
}
```

## Validation

Validate your configuration:

```bash
php bin/console debug:config audit
```

Check if the bundle is properly configured:

```bash
php bin/console audit:status
```

## Next Steps

- [API Reference]({{ site.baseurl }}/docs/api/) - Complete API documentation
- [Advanced Usage]({{ site.baseurl }}/docs/advanced/) - Advanced features and customization
- [Performance]({{ site.baseurl }}/docs/performance/) - Optimization tips