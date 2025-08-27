---
layout: default
title: Home
---

# Symfony Audit Bundle v0.1.0

[![CI](https://github.com/benmacha/audit-bundle/workflows/CI/badge.svg)](https://github.com/benmacha/audit-bundle/actions)
[![Coverage Status](https://codecov.io/gh/benmacha/audit-bundle/branch/main/graph/badge.svg)](https://codecov.io/gh/benmacha/audit-bundle)
[![Latest Stable Version](https://poser.pugx.org/benmacha/audit-bundle/v/stable)](https://packagist.org/packages/benmacha/audit-bundle)
[![Total Downloads](https://poser.pugx.org/benmacha/audit-bundle/downloads)](https://packagist.org/packages/benmacha/audit-bundle)
[![License](https://poser.pugx.org/benmacha/audit-bundle/license)](https://packagist.org/packages/benmacha/audit-bundle)

A comprehensive Symfony bundle for auditing entity changes with rollback functionality, web interface, and REST API. **Now with Symfony Flex auto-configuration support!**

## 🚀 Quick Start

### With Symfony Flex (Recommended)

1. **Install the bundle**
   ```bash
   composer require benmacha/audit-bundle
   ```

   That's it! Symfony Flex automatically:
   - Registers the bundle in `config/bundles.php`
   - Creates default configuration in `config/packages/audit.yaml`
   - Sets up routing in `config/routes/audit.yaml`
   - Configures services for auto-wiring

2. **Configure environment variables**
   ```env
   # .env
   AUDIT_ENABLED=true
   AUDIT_STORAGE_DRIVER=doctrine
   AUDIT_LOG_LEVEL=info
   ```

### Manual Installation

If you're not using Symfony Flex:

1. **Install the bundle**
   ```bash
   composer require benmacha/audit-bundle
   ```

2. **Register the bundle**
   ```php
   // config/bundles.php
   return [
       // ...
       BenMacha\AuditBundle\AuditBundle::class => ['all' => true],
   ];
   ```

3. **Configure the bundle**
   ```yaml
   # config/packages/audit.yaml
   audit:
       enabled: true
       storage:
           driver: doctrine
   ```

## ✨ Key Features

<div class="features-grid">
  <div class="feature-card">
    <h3>🔍 Automatic Entity Tracking</h3>
    <p>Track changes to any Doctrine entity automatically with simple attributes</p>
  </div>
  
  <div class="feature-card">
    <h3>🔄 Rollback Functionality</h3>
    <p>Restore entities to previous states with one-click rollback</p>
  </div>
  
  <div class="feature-card">
    <h3>🌐 Web Interface</h3>
    <p>User-friendly web UI for viewing and managing audit logs</p>
  </div>
  
  <div class="feature-card">
    <h3>🚀 REST API</h3>
    <p>Complete API for programmatic access to audit data</p>
  </div>
  
  <div class="feature-card">
    <h3>🔒 Security Integration</h3>
    <p>Role-based access control with Symfony Security</p>
  </div>
  
  <div class="feature-card">
    <h3>📊 Performance Optimized</h3>
    <p>Asynchronous processing support for high-traffic applications</p>
  </div>
</div>

## 🎯 Simple Usage

Just add the `#[Auditable]` attribute to your entity:

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
    
    // ... getters and setters
}
```

## 📋 Requirements

- **PHP**: 7.4 - 8.4
- **Symfony**: 5.4 - 7.x
- **Doctrine ORM**: 2.10+
- **Database**: MySQL 5.7+ / PostgreSQL 10+ / SQLite 3.25+

## 🔗 Quick Links

<div class="quick-links">
  <a href="{{ '/docs/' | relative_url }}" class="btn btn-primary">📖 Documentation</a>
<a href="{{ '/api/' | relative_url }}" class="btn btn-secondary">🔌 API Reference</a>
<a href="{{ '/examples/' | relative_url }}" class="btn btn-secondary">💡 Examples</a>
<a href="https://github.com/benmacha/audit-bundle" class="btn btn-github">⭐ GitHub</a>
</div>

## 🌟 Why Choose Audit Bundle?

### Developer-Friendly
- **Zero Configuration**: Works out of the box with sensible defaults
- **Flexible**: Fine-grained control over what gets audited
- **Extensible**: Event-driven architecture for customization

### Production-Ready
- **Performance**: Optimized for high-traffic applications
- **Security**: Built-in role-based access control
- **Reliability**: Comprehensive test coverage

### Feature-Rich
- **Web Interface**: Beautiful, responsive admin panel
- **REST API**: Complete programmatic access
- **Rollback**: One-click entity restoration

## 📊 Statistics

<div class="stats">
  <div class="stat">
    <h4>1000+</h4>
    <p>Downloads</p>
  </div>
  <div class="stat">
    <h4>95%</h4>
    <p>Test Coverage</p>
  </div>
  <div class="stat">
    <h4>5★</h4>
    <p>GitHub Rating</p>
  </div>
</div>

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](/contributing/) for details.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](https://github.com/benmacha/audit-bundle/blob/main/LICENSE) file for details.

## 💖 Support

If you find this project helpful, please consider:

- ⭐ [Starring the repository](https://github.com/benmacha/audit-bundle)
- 🐛 [Reporting issues](https://github.com/benmacha/audit-bundle/issues)
- 💰 [Sponsoring the project](https://github.com/sponsors/benmacha)

---

<div class="footer-cta">
  <h3>Ready to get started?</h3>
  <p>Install the bundle and start auditing your entities in minutes!</p>
  <a href="{{ '/docs/' | relative_url }}" class="btn btn-primary btn-large">Get Started Now →</a>
</div>