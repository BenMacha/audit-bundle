---
layout: default
title: Installation
permalink: /docs/installation/
nav_order: 2
---

# Installation

This guide will walk you through installing the Symfony Audit Bundle in your Symfony application.

## Requirements

- PHP 8.1 or higher
- Symfony 6.0 or higher
- Doctrine ORM 2.5 or higher

## Installation via Composer

1. Install the bundle using Composer:

```bash
composer require benmacha/audit-bundle
```

2. If you're not using Symfony Flex, you'll need to enable the bundle manually in `config/bundles.php`:

```php
<?php

return [
    // ...
    BenMacha\AuditBundle\AuditBundle::class => ['all' => true],
];
```

## Database Setup

3. Create the audit table by running the migration:

```bash
php bin/console doctrine:migrations:migrate
```

Or if you prefer to create the table manually:

```bash
php bin/console doctrine:schema:update --force
```

## Configuration

4. (Optional) Configure the bundle in `config/packages/audit.yaml`:

```yaml
audit:
    # Enable/disable audit logging
    enabled: true
    
    # Configure which entities to audit
    entities:
        - App\Entity\User
        - App\Entity\Product
    
    # Configure audit table name (default: 'audit_log')
    table_name: 'audit_log'
    
    # Configure maximum number of audit entries to keep
    max_entries: 10000
```

## Verification

5. Verify the installation by checking if the audit table was created:

```bash
php bin/console doctrine:schema:validate
```

You should see that your database schema is in sync.

## Next Steps

- [Quick Start Guide]({{ site.baseurl }}/docs/quick-start/) - Learn how to use the bundle
- [Configuration]({{ site.baseurl }}/docs/configuration/) - Detailed configuration options
- [API Reference]({{ site.baseurl }}/docs/api/) - Complete API documentation