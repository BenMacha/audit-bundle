# Audit Bundle Attributes Reference

This document provides comprehensive documentation for all attributes available in the Symfony Audit Bundle.

## Table of Contents

- [Auditable](#auditable)
- [IgnoreAudit](#ignoreaudit)
- [AuditSensitive](#auditsensitive)
- [AuditMetadata](#auditmetadata)
- [AuditContext](#auditcontext)
- [Best Practices](#best-practices)
- [Examples](#examples)

## Auditable

The `#[Auditable]` attribute is the primary attribute that enables audit tracking for an entity.

### Syntax

```php
#[Auditable(
    operations?: array,
    ignoredFields?: array,
    async?: bool
)]
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `operations` | `array` | `['create', 'update', 'delete']` | Operations to track |
| `ignoredFields` | `array` | `[]` | Fields to exclude from auditing |
| `async` | `bool` | `false` | Process audit logs asynchronously |

### Usage Examples

#### Basic Usage

```php
use BenMacha\AuditBundle\Attribute\Auditable;

#[Auditable]
class User
{
    // All operations (create, update, delete) will be tracked
    // All fields will be audited
}
```

#### Specific Operations

```php
#[Auditable(operations: ['create', 'update'])]
class Product
{
    // Only create and update operations will be tracked
    // Delete operations will be ignored
}
```

#### Ignored Fields

```php
#[Auditable(ignoredFields: ['password', 'lastLogin', 'updatedAt'])]
class User
{
    private string $username;     // Will be audited
    private string $email;        // Will be audited
    private string $password;     // Will NOT be audited
    private ?\DateTime $lastLogin; // Will NOT be audited
    private \DateTime $updatedAt;  // Will NOT be audited
}
```

#### Asynchronous Processing

```php
#[Auditable(async: true)]
class LargeEntity
{
    // Audit logs will be processed asynchronously
    // Useful for high-traffic entities to improve performance
}
```

#### Combined Configuration

```php
#[Auditable(
    operations: ['create', 'update'],
    ignoredFields: ['internalId', 'cache'],
    async: true
)]
class OptimizedEntity
{
    // Only create/update tracked
    // Specific fields ignored
    // Processed asynchronously
}
```

### When to Use

- **Always required** for entities you want to audit
- Use `operations` to limit tracking to specific operations
- Use `ignoredFields` for sensitive or irrelevant fields
- Use `async: true` for high-traffic entities

---

## IgnoreAudit

The `#[IgnoreAudit]` attribute excludes specific fields or operations from audit tracking.

### Syntax

```php
#[IgnoreAudit(operations?: array)]
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `operations` | `array` | `[]` | Specific operations to ignore (if empty, ignores all) |

### Usage Examples

#### Complete Field Exclusion

```php
class User
{
    private string $username;  // Will be audited
    
    #[IgnoreAudit]
    private string $password;  // Will NEVER be audited
    
    #[IgnoreAudit]
    private string $tempToken; // Will NEVER be audited
}
```

#### Operation-Specific Exclusion

```php
class Article
{
    private string $title;        // Always audited
    
    #[IgnoreAudit(operations: ['update'])]
    private \DateTime $createdAt; // Audited on create/delete, not update
    
    #[IgnoreAudit(operations: ['create', 'update'])]
    private int $viewCount;       // Only audited on delete
}
```

#### Multiple Operations

```php
class Statistics
{
    #[IgnoreAudit(operations: ['create', 'update'])]
    private int $hitCount;        // Only track when deleted
    
    #[IgnoreAudit(operations: ['delete'])]
    private \DateTime $lastAccess; // Don't track on deletion
}
```

### When to Use

- **Sensitive data** that should never be logged
- **Frequently changing fields** that would create noise
- **Calculated fields** that don't need tracking
- **Temporary fields** used for processing

---

## AuditSensitive

The `#[AuditSensitive]` attribute marks fields as sensitive, enabling special handling like encryption or masking.

### Syntax

```php
#[AuditSensitive(
    encrypt?: bool,
    mask?: bool,
    hashAlgorithm?: string
)]
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `encrypt` | `bool` | `false` | Encrypt the field value in audit logs |
| `mask` | `bool` | `false` | Mask the value in UI (show as ***) |
| `hashAlgorithm` | `string` | `'sha256'` | Algorithm for hashing sensitive data |

### Usage Examples

#### Basic Masking

```php
class User
{
    #[AuditSensitive(mask: true)]
    private string $socialSecurityNumber; // Shows as *** in UI
    
    #[AuditSensitive(mask: true)]
    private string $creditCardNumber;     // Shows as *** in UI
}
```

#### Encryption

```php
class Patient
{
    #[AuditSensitive(encrypt: true)]
    private string $medicalRecord;        // Encrypted in database
    
    #[AuditSensitive(encrypt: true, hashAlgorithm: 'sha512')]
    private string $geneticData;          // Encrypted with SHA-512
}
```

#### Combined Protection

```php
class Employee
{
    #[AuditSensitive(encrypt: true, mask: true)]
    private string $salary;               // Encrypted AND masked
    
    #[AuditSensitive(encrypt: true, mask: true, hashAlgorithm: 'sha256')]
    private string $personalNotes;        // Full protection
}
```

### Security Considerations

- **Encryption keys** must be properly managed
- **Masked fields** are still stored in plaintext (use with encrypt)
- **Hash algorithms** should be cryptographically secure
- **Performance impact** of encryption should be considered

### When to Use

- **PII (Personally Identifiable Information)**
- **Financial data**
- **Medical records**
- **Legal documents**
- **Any sensitive business data**

---

## AuditMetadata

The `#[AuditMetadata]` attribute adds custom metadata to audit logs for enhanced categorization and searching.

### Syntax

```php
#[AuditMetadata(
    tags?: array,
    indexed?: bool,
    ttl?: int,
    customData?: array
)]
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `tags` | `array` | `[]` | Custom tags for categorization |
| `indexed` | `bool` | `false` | Whether to index this field for searching |
| `ttl` | `int` | `null` | Time-to-live in seconds |
| `customData` | `array` | `[]` | Additional custom metadata |

### Usage Examples

#### Tagging

```php
class User
{
    #[AuditMetadata(tags: ['pii', 'gdpr'])]
    private string $email;                // Tagged for GDPR compliance
    
    #[AuditMetadata(tags: ['sensitive', 'hr'])]
    private string $department;           // Tagged for HR category
}
```

#### Indexing for Search

```php
class Product
{
    #[AuditMetadata(indexed: true)]
    private string $sku;                  // Indexed for fast searching
    
    #[AuditMetadata(indexed: true, tags: ['inventory'])]
    private int $quantity;                // Indexed and tagged
}
```

#### TTL (Time-to-Live)

```php
class Session
{
    #[AuditMetadata(ttl: 86400)]         // 24 hours
    private string $sessionId;
    
    #[AuditMetadata(ttl: 2592000)]       // 30 days
    private \DateTime $lastActivity;
}
```

#### Custom Data

```php
class Order
{
    #[AuditMetadata(
        tags: ['financial', 'order'],
        customData: [
            'department' => 'sales',
            'region' => 'north_america',
            'compliance_level' => 'high'
        ]
    )]
    private float $totalAmount;
}
```

#### Complex Metadata

```php
class Document
{
    #[AuditMetadata(
        tags: ['legal', 'contract', 'confidential'],
        indexed: true,
        ttl: 31536000,                    // 1 year
        customData: [
            'classification' => 'confidential',
            'retention_policy' => 'legal_hold',
            'access_level' => 'restricted'
        ]
    )]
    private string $contractText;
}
```

### When to Use

- **Compliance requirements** (GDPR, HIPAA, etc.)
- **Data classification**
- **Search optimization**
- **Retention policies**
- **Business categorization**

---

## AuditContext

The `#[AuditContext]` attribute provides additional context for audit operations at the entity level.

### Syntax

```php
#[AuditContext(
    reason?: string,
    category?: string,
    priority?: string,
    metadata?: array
)]
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `reason` | `string` | `null` | Human-readable reason for changes |
| `category` | `string` | `null` | Category for grouping related changes |
| `priority` | `string` | `'medium'` | Priority level (low, medium, high, critical) |
| `metadata` | `array` | `[]` | Additional context metadata |

### Usage Examples

#### Basic Context

```php
#[AuditContext(
    reason: 'User profile management',
    category: 'user_operations'
)]
class User
{
    // All changes will include this context
}
```

#### Priority Levels

```php
#[AuditContext(
    reason: 'Financial transaction processing',
    category: 'financial',
    priority: 'critical'
)]
class Transaction
{
    // High-priority audit logging
}
```

#### Rich Metadata

```php
#[AuditContext(
    reason: 'Patient data management',
    category: 'healthcare',
    priority: 'high',
    metadata: [
        'compliance' => 'HIPAA',
        'data_classification' => 'PHI',
        'retention_years' => 7,
        'access_restrictions' => 'medical_staff_only'
    ]
)]
class Patient
{
    // Comprehensive context for healthcare compliance
}
```

#### Business Process Context

```php
#[AuditContext(
    reason: 'Inventory management operations',
    category: 'supply_chain',
    priority: 'medium',
    metadata: [
        'business_unit' => 'logistics',
        'process_type' => 'automated',
        'sla_requirement' => '99.9%'
    ]
)]
class Inventory
{
    // Business process context
}
```

### When to Use

- **Compliance documentation**
- **Business process tracking**
- **Priority-based monitoring**
- **Contextual reporting**
- **Audit trail documentation**

---

## Best Practices

### 1. Attribute Combination

```php
#[Auditable(operations: ['create', 'update'])]
#[AuditContext(
    reason: 'Customer data management',
    category: 'crm',
    priority: 'high'
)]
class Customer
{
    #[AuditMetadata(tags: ['pii'], indexed: true)]
    private string $email;
    
    #[AuditSensitive(encrypt: true, mask: true)]
    #[AuditMetadata(tags: ['sensitive', 'financial'])]
    private string $creditScore;
    
    #[IgnoreAudit]
    private string $temporaryToken;
}
```

### 2. Performance Optimization

```php
// For high-traffic entities
#[Auditable(
    async: true,
    ignoredFields: ['lastAccess', 'hitCount', 'cache']
)]
class HighTrafficEntity
{
    // Only audit important fields asynchronously
}
```

### 3. Compliance-Ready Setup

```php
#[Auditable]
#[AuditContext(
    reason: 'GDPR compliance data processing',
    category: 'gdpr',
    priority: 'critical'
)]
class GDPREntity
{
    #[AuditSensitive(encrypt: true)]
    #[AuditMetadata(
        tags: ['pii', 'gdpr'],
        ttl: 2592000,  // 30 days
        customData: ['gdpr_category' => 'personal_data']
    )]
    private string $personalData;
}
```

### 4. Security-First Approach

```php
class SecuritySensitiveEntity
{
    #[AuditSensitive(encrypt: true, mask: true)]
    #[IgnoreAudit(operations: ['update'])]  // Only log creation
    private string $apiKey;
    
    #[AuditSensitive(mask: true)]
    #[AuditMetadata(tags: ['auth', 'security'])]
    private string $passwordHash;
}
```

---

## Examples

### E-commerce Product

```php
#[Auditable(operations: ['create', 'update'])]
#[AuditContext(
    reason: 'Product catalog management',
    category: 'ecommerce',
    priority: 'medium'
)]
class Product
{
    #[AuditMetadata(indexed: true, tags: ['catalog'])]
    private string $sku;
    
    #[AuditMetadata(tags: ['pricing'])]
    private float $price;
    
    #[AuditSensitive(mask: true)]
    #[AuditMetadata(tags: ['financial', 'cost'])]
    private float $cost;
    
    #[IgnoreAudit]
    private int $viewCount;
    
    #[IgnoreAudit(operations: ['update'])]
    private \DateTime $createdAt;
}
```

### Healthcare Patient Record

```php
#[Auditable]
#[AuditContext(
    reason: 'Patient health record management',
    category: 'healthcare',
    priority: 'critical',
    metadata: ['compliance' => 'HIPAA']
)]
class Patient
{
    #[AuditMetadata(indexed: true, tags: ['identifier'])]
    private string $patientId;
    
    #[AuditSensitive(encrypt: true)]
    #[AuditMetadata(
        tags: ['phi', 'medical'],
        ttl: 220752000,  // 7 years
        customData: ['hipaa_category' => 'protected_health_info']
    )]
    private string $medicalHistory;
    
    #[AuditSensitive(encrypt: true, mask: true)]
    #[AuditMetadata(tags: ['pii', 'sensitive'])]
    private string $socialSecurityNumber;
    
    #[IgnoreAudit]
    private \DateTime $lastLoginTime;
}
```

### Financial Transaction

```php
#[Auditable(async: true)]  // High volume, process async
#[AuditContext(
    reason: 'Financial transaction processing',
    category: 'finance',
    priority: 'critical',
    metadata: [
        'compliance' => 'SOX',
        'audit_requirement' => 'mandatory'
    ]
)]
class Transaction
{
    #[AuditMetadata(indexed: true, tags: ['transaction', 'reference'])]
    private string $transactionId;
    
    #[AuditSensitive(encrypt: true)]
    #[AuditMetadata(
        tags: ['financial', 'amount'],
        customData: ['currency_sensitive' => true]
    )]
    private float $amount;
    
    #[AuditMetadata(indexed: true, tags: ['account'])]
    private string $accountNumber;
    
    #[IgnoreAudit(operations: ['update'])]  // Immutable after creation
    private \DateTime $processedAt;
}
```

These examples demonstrate how to combine multiple attributes effectively for different business domains while maintaining security, compliance, and performance considerations.