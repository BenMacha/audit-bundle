# Audit Bundle Usage Guide

This comprehensive guide covers advanced usage patterns, event handling, customization, and best practices for the Symfony Audit Bundle.

## Table of Contents

- [Basic Setup](#basic-setup)
- [Event Subscription](#event-subscription)
- [Manual Audit Logging](#manual-audit-logging)
- [Flushing Audit Data](#flushing-audit-data)
- [View Customization](#view-customization)
- [Custom Rollback](#custom-rollback)
- [Performance Optimization](#performance-optimization)
- [Security Configuration](#security-configuration)
- [API Usage](#api-usage)
- [Troubleshooting](#troubleshooting)

## Basic Setup

### 1. Bundle Registration

The bundle should be automatically registered if using Symfony Flex. If not, add it manually:

```php
// config/bundles.php
return [
    // ... other bundles
    BenMacha\AuditBundle\AuditBundle::class => ['all' => true],
];
```

### 2. Configuration

Create or update your configuration:

```yaml
# config/packages/audit.yaml
audit:
  enabled: true
  retention_days: 365
  async_processing: false
  database_connection: 'default'
  
  # Entity-specific settings
  entities:
    App\Entity\User:
      enabled: true
      operations: ['create', 'update', 'delete']
      ignored_fields: ['password', 'lastLogin']
    App\Entity\Product:
      enabled: true
      operations: ['create', 'update']
      async: true
  
  # API settings
  api:
    enabled: true
    rate_limit: 100
    prefix: '/api/audit'
  
  # Security roles
  security:
    admin_role: 'ROLE_ADMIN'
    auditor_role: 'ROLE_AUDITOR'
    developer_role: 'ROLE_DEVELOPER'
  
  # UI settings
  ui:
    route_prefix: '/audit'
    items_per_page: 25
    show_ip_address: true
```

### 3. Database Setup

Run the migrations to create audit tables:

```bash
php bin/console doctrine:migrations:migrate
```

## Event Subscription

The audit bundle provides several events you can subscribe to for custom behavior.

### Available Events

```php
use BenMacha\AuditBundle\Event\AuditEvent;
use BenMacha\AuditBundle\Event\PreAuditEvent;
use BenMacha\AuditBundle\Event\PostAuditEvent;
use BenMacha\AuditBundle\Event\AuditFlushEvent;
```

### Creating Event Subscribers

#### 1. Pre-Audit Event Subscriber

```php
// src/EventSubscriber/AuditSubscriber.php
namespace App\EventSubscriber;

use BenMacha\AuditBundle\Event\PreAuditEvent;
use BenMacha\AuditBundle\Event\PostAuditEvent;
use BenMacha\AuditBundle\Event\AuditFlushEvent;
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
            AuditFlushEvent::class => 'onAuditFlush',
        ];
    }

    public function onPreAudit(PreAuditEvent $event): void
    {
        $entity = $event->getEntity();
        $operation = $event->getOperation();
        
        // Add custom metadata
        $event->addMetadata('ip_address', $this->getClientIp());
        $event->addMetadata('user_agent', $this->getUserAgent());
        
        // Conditional auditing
        if ($entity instanceof SensitiveEntity && !$this->shouldAuditSensitive()) {
            $event->preventDefault();
            return;
        }
        
        $this->logger->info('Pre-audit event triggered', [
            'entity' => get_class($entity),
            'operation' => $operation,
        ]);
    }

    public function onPostAudit(PostAuditEvent $event): void
    {
        $auditLog = $event->getAuditLog();
        
        // Send notifications for critical changes
        if ($auditLog->getEntity() === 'App\\Entity\\User' && 
            $auditLog->getOperation() === 'delete') {
            $this->sendCriticalChangeNotification($auditLog);
        }
        
        // Custom logging
        $this->logger->info('Entity audited', [
            'audit_id' => $auditLog->getId(),
            'entity' => $auditLog->getEntity(),
            'operation' => $auditLog->getOperation(),
        ]);
    }

    public function onAuditFlush(AuditFlushEvent $event): void
    {
        $auditLogs = $event->getAuditLogs();
        
        // Batch processing for multiple audit logs
        $this->processBatchAuditLogs($auditLogs);
        
        $this->logger->info('Audit flush completed', [
            'count' => count($auditLogs),
        ]);
    }

    private function getClientIp(): string
    {
        // Implementation to get client IP
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    private function shouldAuditSensitive(): bool
    {
        // Custom logic to determine if sensitive data should be audited
        return true;
    }

    private function sendCriticalChangeNotification(AuditLog $auditLog): void
    {
        // Send email, Slack notification, etc.
    }

    private function processBatchAuditLogs(array $auditLogs): void
    {
        // Custom batch processing logic
    }
}
```

#### 2. Entity-Specific Event Subscriber

```php
// src/EventSubscriber/UserAuditSubscriber.php
namespace App\EventSubscriber;

use App\Entity\User;
use BenMacha\AuditBundle\Event\PreAuditEvent;
use BenMacha\AuditBundle\Event\PostAuditEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Security;

class UserAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security
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
        $entity = $event->getEntity();
        
        if (!$entity instanceof User) {
            return;
        }
        
        // Add user-specific metadata
        $currentUser = $this->security->getUser();
        if ($currentUser) {
            $event->addMetadata('modified_by', $currentUser->getId());
            $event->addMetadata('modified_by_username', $currentUser->getUsername());
        }
        
        // Add role information
        $event->addMetadata('user_roles', $entity->getRoles());
        
        // Prevent auditing of system users
        if ($entity->getUsername() === 'system') {
            $event->preventDefault();
        }
    }

    public function onPostAudit(PostAuditEvent $event): void
    {
        $auditLog = $event->getAuditLog();
        
        if ($auditLog->getEntity() !== User::class) {
            return;
        }
        
        // Log security-relevant changes
        $changes = $auditLog->getChanges();
        if (isset($changes['roles']) || isset($changes['enabled'])) {
            $this->logSecurityChange($auditLog);
        }
    }

    private function logSecurityChange(AuditLog $auditLog): void
    {
        // Custom security logging
    }
}
```

### Event-Driven Workflows

```php
// src/EventSubscriber/WorkflowAuditSubscriber.php
namespace App\EventSubscriber;

use BenMacha\AuditBundle\Event\PostAuditEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class WorkflowAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WorkflowInterface $orderWorkflow
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PostAuditEvent::class => 'onPostAudit',
        ];
    }

    public function onPostAudit(PostAuditEvent $event): void
    {
        $auditLog = $event->getAuditLog();
        
        // Trigger workflow transitions based on audit events
        if ($auditLog->getEntity() === 'App\\Entity\\Order') {
            $this->handleOrderAudit($auditLog);
        }
    }

    private function handleOrderAudit(AuditLog $auditLog): void
    {
        $changes = $auditLog->getChanges();
        
        if (isset($changes['status'])) {
            // Trigger workflow based on status change
            $this->triggerWorkflowTransition($auditLog);
        }
    }

    private function triggerWorkflowTransition(AuditLog $auditLog): void
    {
        // Custom workflow logic
    }
}
```

## Manual Audit Logging

Sometimes you need to create audit logs manually for custom operations.

### Using the Audit Manager

```php
// src/Service/CustomService.php
namespace App\Service;

use BenMacha\AuditBundle\Service\AuditManager;
use App\Entity\User;

class CustomService
{
    public function __construct(
        private AuditManager $auditManager
    ) {}

    public function performCustomOperation(User $user): void
    {
        // Your custom business logic
        $this->doSomethingCustom($user);
        
        // Manual audit logging
        $this->auditManager->logCustomOperation(
            entity: $user,
            operation: 'custom_operation',
            changes: [
                'custom_field' => [
                    'old' => 'old_value',
                    'new' => 'new_value'
                ]
            ],
            metadata: [
                'operation_type' => 'manual',
                'reason' => 'Custom business operation',
                'performed_by' => 'system'
            ]
        );
    }

    public function bulkOperation(array $entities): void
    {
        foreach ($entities as $entity) {
            // Process entity
            $this->processEntity($entity);
            
            // Log each operation
            $this->auditManager->logBulkOperation(
                entity: $entity,
                operation: 'bulk_update',
                batchId: uniqid('bulk_'),
                metadata: [
                    'batch_size' => count($entities),
                    'operation_time' => new \DateTime()
                ]
            );
        }
        
        // Flush all audit logs at once
        $this->auditManager->flush();
    }

    private function doSomethingCustom(User $user): void
    {
        // Custom logic
    }

    private function processEntity($entity): void
    {
        // Processing logic
    }
}
```

### Manual Logging with Context

```php
// src/Controller/AdminController.php
namespace App\Controller;

use BenMacha\AuditBundle\Service\AuditManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    public function __construct(
        private AuditManager $auditManager
    ) {}

    #[Route('/admin/user/{id}/reset-password', methods: ['POST'])]
    public function resetUserPassword(int $id, Request $request): Response
    {
        $user = $this->getUserById($id);
        $oldPasswordHash = $user->getPassword();
        
        // Reset password
        $newPassword = $this->generateRandomPassword();
        $user->setPassword($this->hashPassword($newPassword));
        
        // Manual audit log with admin context
        $this->auditManager->logAdminAction(
            entity: $user,
            operation: 'password_reset',
            adminUser: $this->getUser(),
            reason: $request->get('reason', 'Admin password reset'),
            changes: [
                'password' => [
                    'old' => '[REDACTED]',
                    'new' => '[REDACTED]'
                ]
            ],
            metadata: [
                'admin_ip' => $request->getClientIp(),
                'admin_user_agent' => $request->headers->get('User-Agent'),
                'reset_method' => 'admin_panel',
                'security_level' => 'high'
            ]
        );
        
        $this->entityManager->flush();
        
        return $this->json(['status' => 'success']);
    }

    private function getUserById(int $id): User
    {
        // Get user logic
    }

    private function generateRandomPassword(): string
    {
        // Password generation logic
    }

    private function hashPassword(string $password): string
    {
        // Password hashing logic
    }
}
```

## Flushing Audit Data

The audit bundle provides several ways to manage and flush audit data.

### Automatic Flushing

By default, audit logs are flushed automatically with entity changes:

```yaml
# config/packages/audit.yaml
audit:
  auto_flush: true  # Default behavior
  flush_strategy: 'immediate'  # or 'deferred', 'batch'
```

### Manual Flushing

```php
// src/Service/AuditService.php
namespace App\Service;

use BenMacha\AuditBundle\Service\AuditManager;
use BenMacha\AuditBundle\Service\AuditFlushService;

class AuditService
{
    public function __construct(
        private AuditManager $auditManager,
        private AuditFlushService $flushService
    ) {}

    public function performBatchOperations(): void
    {
        // Disable auto-flush for batch operations
        $this->auditManager->disableAutoFlush();
        
        try {
            // Perform multiple operations
            for ($i = 0; $i < 1000; $i++) {
                $this->performOperation($i);
            }
            
            // Manual flush at the end
            $this->auditManager->flush();
            
        } finally {
            // Re-enable auto-flush
            $this->auditManager->enableAutoFlush();
        }
    }

    public function flushWithConditions(): void
    {
        // Flush only specific entity types
        $this->flushService->flushByEntityType('App\\Entity\\User');
        
        // Flush by operation type
        $this->flushService->flushByOperation('create');
        
        // Flush by date range
        $this->flushService->flushByDateRange(
            new \DateTime('-1 hour'),
            new \DateTime()
        );
    }

    public function scheduleFlush(): void
    {
        // Schedule flush for later (useful for async processing)
        $this->flushService->scheduleFlush([
            'delay' => 300, // 5 minutes
            'priority' => 'low',
            'batch_size' => 100
        ]);
    }

    private function performOperation(int $index): void
    {
        // Operation logic
    }
}
```

### Cleanup and Retention

```php
// src/Command/AuditCleanupCommand.php
namespace App\Command;

use BenMacha\AuditBundle\Service\AuditCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'audit:cleanup',
    description: 'Clean up old audit logs based on retention policy'
)]
class AuditCleanupCommand extends Command
{
    public function __construct(
        private AuditCleanupService $cleanupService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Days to retain', 365)
            ->addOption('entity', 'e', InputOption::VALUE_OPTIONAL, 'Specific entity class')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $entity = $input->getOption('entity');
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');
        
        $cutoffDate = new \DateTime("-{$days} days");
        
        $output->writeln(sprintf(
            'Cleaning up audit logs older than %s (%d days)',
            $cutoffDate->format('Y-m-d H:i:s'),
            $days
        ));
        
        if ($entity) {
            $output->writeln("Filtering by entity: {$entity}");
        }
        
        if ($dryRun) {
            $count = $this->cleanupService->countOldLogs($cutoffDate, $entity);
            $output->writeln("Would delete {$count} audit logs");
            return Command::SUCCESS;
        }
        
        $deleted = $this->cleanupService->cleanupOldLogs(
            $cutoffDate,
            $entity,
            $batchSize
        );
        
        $output->writeln("Deleted {$deleted} audit logs");
        
        return Command::SUCCESS;
    }
}
```

## View Customization

The audit bundle provides flexible view customization options.

### Custom Templates

#### 1. Override Base Template

```twig
{# templates/bundles/AuditBundle/base.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{% block title %}Audit System{% endblock %}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/audit-custom.css') }}" rel="stylesheet">
    {% block stylesheets %}{% endblock %}
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ path('audit_dashboard') }}">Audit System</a>
            <div class="navbar-nav">
                <a class="nav-link" href="{{ path('audit_logs') }}">Audit Logs</a>
                <a class="nav-link" href="{{ path('audit_entities') }}">Entities</a>
                <a class="nav-link" href="{{ path('audit_reports') }}">Reports</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        {% for message in app.flashes('success') %}
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        {% endfor %}
        
        {% for message in app.flashes('error') %}
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        {% endfor %}
        
        {% block content %}{% endblock %}
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    {% block javascripts %}{% endblock %}
</body>
</html>
```

#### 2. Custom Audit Log List

```twig
{# templates/bundles/AuditBundle/audit_log/list.html.twig #}
{% extends '@Audit/base.html.twig' %}

{% block title %}Audit Logs{% endblock %}

{% block content %}
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Audit Logs</h1>
    <div>
        <a href="{{ path('audit_export') }}" class="btn btn-outline-primary">Export</a>
        <a href="{{ path('audit_reports') }}" class="btn btn-primary">Reports</a>
    </div>
</div>

{# Custom filters #}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Entity</label>
                <select name="entity" class="form-select">
                    <option value="">All Entities</option>
                    {% for entity in entities %}
                        <option value="{{ entity }}" {{ app.request.get('entity') == entity ? 'selected' : '' }}>
                            {{ entity|split('\\\\')|last }}
                        </option>
                    {% endfor %}
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Operation</label>
                <select name="operation" class="form-select">
                    <option value="">All Operations</option>
                    <option value="create" {{ app.request.get('operation') == 'create' ? 'selected' : '' }}>Create</option>
                    <option value="update" {{ app.request.get('operation') == 'update' ? 'selected' : '' }}>Update</option>
                    <option value="delete" {{ app.request.get('operation') == 'delete' ? 'selected' : '' }}>Delete</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="{{ app.request.get('date_from') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="{{ app.request.get('date_to') }}">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ path('audit_logs') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

{# Custom audit log table #}
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Entity</th>
                        <th>Operation</th>
                        <th>User</th>
                        <th>Date</th>
                        <th>Changes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {% for log in logs %}
                        <tr class="audit-row" data-entity="{{ log.entity }}" data-operation="{{ log.operation }}">
                            <td>{{ log.id }}</td>
                            <td>
                                <span class="badge bg-info">{{ log.entity|split('\\\\')|last }}</span>
                                <small class="text-muted d-block">ID: {{ log.entityId }}</small>
                            </td>
                            <td>
                                <span class="badge bg-{{ log.operation == 'create' ? 'success' : (log.operation == 'update' ? 'warning' : 'danger') }}">
                                    {{ log.operation|upper }}
                                </span>
                            </td>
                            <td>
                                {% if log.user %}
                                    {{ log.user.username ?? log.user.email ?? 'Unknown' }}
                                {% else %}
                                    <em>System</em>
                                {% endif %}
                            </td>
                            <td>
                                <span title="{{ log.createdAt|date('Y-m-d H:i:s') }}">
                                    {{ log.createdAt|date('M j, Y') }}
                                </span>
                                <small class="text-muted d-block">{{ log.createdAt|date('H:i:s') }}</small>
                            </td>
                            <td>
                                {% if log.changes|length > 0 %}
                                    <button class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#changesModal{{ log.id }}">
                                        {{ log.changes|length }} change(s)
                                    </button>
                                {% else %}
                                    <em>No changes</em>
                                {% endif %}
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ path('audit_log_show', {id: log.id}) }}" class="btn btn-outline-primary">View</a>
                                    {% if log.operation != 'delete' and is_granted('ROLE_ADMIN') %}
                                        <a href="{{ path('audit_log_rollback', {id: log.id}) }}" 
                                           class="btn btn-outline-warning"
                                           onclick="return confirm('Are you sure you want to rollback this change?')">Rollback</a>
                                    {% endif %}
                                </div>
                            </td>
                        </tr>
                        
                        {# Changes Modal #}
                        <div class="modal fade" id="changesModal{{ log.id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Changes for {{ log.entity|split('\\\\')|last }} #{{ log.entityId }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        {% for field, change in log.changes %}
                                            <div class="mb-3">
                                                <strong>{{ field }}:</strong>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <label class="form-label text-danger">Old Value:</label>
                                                        <pre class="bg-light p-2 rounded">{{ change.old ?? 'null' }}</pre>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label text-success">New Value:</label>
                                                        <pre class="bg-light p-2 rounded">{{ change.new ?? 'null' }}</pre>
                                                    </div>
                                                </div>
                                            </div>
                                        {% endfor %}
                                    </div>
                                </div>
                            </div>
                        </div>
                    {% endfor %}
                </tbody>
            </table>
        </div>
        
        {# Pagination #}
        {% if logs.haveToPaginate %}
            <nav class="mt-4">
                {{ knp_pagination_render(logs) }}
            </nav>
        {% endif %}
    </div>
</div>
{% endblock %}

{% block javascripts %}
<script>
// Custom JavaScript for audit log interactions
document.addEventListener('DOMContentLoaded', function() {
    // Real-time filtering
    const entityFilter = document.querySelector('select[name="entity"]');
    const operationFilter = document.querySelector('select[name="operation"]');
    
    if (entityFilter && operationFilter) {
        [entityFilter, operationFilter].forEach(filter => {
            filter.addEventListener('change', function() {
                this.form.submit();
            });
        });
    }
    
    // Highlight recent changes
    const rows = document.querySelectorAll('.audit-row');
    rows.forEach(row => {
        const dateCell = row.querySelector('td:nth-child(5) span');
        if (dateCell) {
            const date = new Date(dateCell.getAttribute('title'));
            const now = new Date();
            const diffHours = (now - date) / (1000 * 60 * 60);
            
            if (diffHours < 1) {
                row.classList.add('table-warning');
            }
        }
    });
});
</script>
{% endblock %}
```

### Custom Controllers

```php
// src/Controller/CustomAuditController.php
namespace App\Controller;

use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Service\AuditReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit')]
#[IsGranted('ROLE_AUDITOR')]
class CustomAuditController extends AbstractController
{
    public function __construct(
        private AuditLogRepository $auditLogRepository,
        private AuditReportService $reportService
    ) {}

    #[Route('/dashboard', name: 'custom_audit_dashboard')]
    public function dashboard(): Response
    {
        $stats = $this->reportService->getDashboardStats();
        
        return $this->render('audit/dashboard.html.twig', [
            'stats' => $stats,
            'recent_logs' => $this->auditLogRepository->findRecent(10),
            'top_entities' => $this->reportService->getTopAuditedEntities(),
            'activity_chart' => $this->reportService->getActivityChartData(),
        ]);
    }

    #[Route('/entity/{entity}', name: 'custom_audit_entity')]
    public function entityAudit(string $entity, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 25;
        
        $logs = $this->auditLogRepository->findByEntityPaginated(
            $entity,
            $page,
            $limit
        );
        
        return $this->render('audit/entity_audit.html.twig', [
            'entity' => $entity,
            'logs' => $logs,
            'entity_stats' => $this->reportService->getEntityStats($entity),
        ]);
    }

    #[Route('/user/{userId}', name: 'custom_audit_user')]
    public function userAudit(int $userId, Request $request): Response
    {
        $logs = $this->auditLogRepository->findByUser($userId);
        
        return $this->render('audit/user_audit.html.twig', [
            'user_id' => $userId,
            'logs' => $logs,
            'user_stats' => $this->reportService->getUserStats($userId),
        ]);
    }

    #[Route('/export', name: 'custom_audit_export')]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $filters = $request->query->all();
        
        $data = $this->reportService->exportAuditLogs($filters, $format);
        
        $response = new Response($data['content']);
        $response->headers->set('Content-Type', $data['mime_type']);
        $response->headers->set('Content-Disposition', 
            'attachment; filename="audit_export.' . $format . '"');
        
        return $response;
    }
}
```

## Custom Rollback

Implement custom rollback functionality for your entities.

### Rollback Service

```php
// src/Service/CustomRollbackService.php
namespace App\Service;

use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Service\RollbackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class CustomRollbackService
{
    public function __construct(
        private RollbackService $rollbackService,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function rollbackWithValidation(AuditLog $auditLog): bool
    {
        // Custom validation before rollback
        if (!$this->canRollback($auditLog)) {
            throw new \Exception('Rollback not allowed for this audit log');
        }
        
        // Perform rollback
        $success = $this->rollbackService->rollback($auditLog);
        
        if ($success) {
            // Log the rollback action
            $this->logRollbackAction($auditLog);
        }
        
        return $success;
    }

    public function rollbackMultiple(array $auditLogs): array
    {
        $results = [];
        
        $this->entityManager->beginTransaction();
        
        try {
            foreach ($auditLogs as $auditLog) {
                $results[$auditLog->getId()] = $this->rollbackWithValidation($auditLog);
            }
            
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
        
        return $results;
    }

    public function previewRollback(AuditLog $auditLog): array
    {
        // Show what would change without actually performing rollback
        $entity = $this->getEntityFromAuditLog($auditLog);
        $changes = $auditLog->getChanges();
        
        $preview = [];
        foreach ($changes as $field => $change) {
            $currentValue = $this->getEntityFieldValue($entity, $field);
            $rollbackValue = $change['old'];
            
            $preview[$field] = [
                'current' => $currentValue,
                'will_become' => $rollbackValue,
                'changed' => $currentValue !== $rollbackValue
            ];
        }
        
        return $preview;
    }

    private function canRollback(AuditLog $auditLog): bool
    {
        // Custom business rules for rollback
        
        // Check user permissions
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return false;
        }
        
        // Check time constraints
        $maxAge = new \DateTime('-24 hours');
        if ($auditLog->getCreatedAt() < $maxAge) {
            return false;
        }
        
        // Check entity-specific rules
        if ($auditLog->getEntity() === 'App\\Entity\\User' && 
            $auditLog->getOperation() === 'delete') {
            return false; // Never rollback user deletions
        }
        
        return true;
    }

    private function logRollbackAction(AuditLog $auditLog): void
    {
        // Create audit log for the rollback action itself
        $this->rollbackService->logRollback(
            $auditLog,
            $this->security->getUser(),
            'Manual rollback performed'
        );
    }

    private function getEntityFromAuditLog(AuditLog $auditLog): ?object
    {
        $entityClass = $auditLog->getEntity();
        $entityId = $auditLog->getEntityId();
        
        return $this->entityManager->getRepository($entityClass)->find($entityId);
    }

    private function getEntityFieldValue(object $entity, string $field): mixed
    {
        $getter = 'get' . ucfirst($field);
        if (method_exists($entity, $getter)) {
            return $entity->$getter();
        }
        
        return null;
    }
}
```

## Performance Optimization

### Asynchronous Processing

Enable asynchronous processing for better performance:

```yaml
# config/packages/audit.yaml
audit:
    async: true
    queue_name: 'audit_queue'
```

### Database Optimization

```php
// src/EventListener/AuditOptimizationListener.php
namespace App\EventListener;

use BenMacha\AuditBundle\Event\AuditEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuditOptimizationListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AuditEvent::PRE_AUDIT => 'onPreAudit',
        ];
    }

    public function onPreAudit(AuditEvent $event): void
    {
        $entity = $event->getEntity();
        
        // Skip auditing for bulk operations
        if ($this->isBulkOperation()) {
            $event->skipAudit();
            return;
        }
        
        // Optimize for specific entities
        if ($entity instanceof \App\Entity\LogEntry) {
            // Don't audit log entries to avoid recursion
            $event->skipAudit();
        }
    }

    private function isBulkOperation(): bool
    {
        // Detect bulk operations and skip auditing
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        foreach ($trace as $frame) {
            if (isset($frame['function']) && 
                in_array($frame['function'], ['flush', 'batchInsert', 'bulkUpdate'])) {
                return true;
            }
        }
        
        return false;
    }
}
```

### Batch Processing

```php
// src/Service/BatchAuditService.php
namespace App\Service;

use BenMacha\AuditBundle\Service\AuditManager;
use Doctrine\ORM\EntityManagerInterface;

class BatchAuditService
{
    private array $pendingAudits = [];
    
    public function __construct(
        private AuditManager $auditManager,
        private EntityManagerInterface $entityManager
    ) {}

    public function addToQueue(object $entity, string $operation, array $changes = []): void
    {
        $this->pendingAudits[] = [
            'entity' => $entity,
            'operation' => $operation,
            'changes' => $changes,
            'timestamp' => new \DateTime()
        ];
    }

    public function processBatch(): void
    {
        if (empty($this->pendingAudits)) {
            return;
        }

        $this->entityManager->beginTransaction();
        
        try {
            foreach ($this->pendingAudits as $audit) {
                $this->auditManager->logManual(
                    $audit['entity'],
                    $audit['operation'],
                    $audit['changes'],
                    $audit['timestamp']
                );
            }
            
            $this->entityManager->flush();
            $this->entityManager->commit();
            
            $this->pendingAudits = [];
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function __destruct()
    {
        // Ensure pending audits are processed
        if (!empty($this->pendingAudits)) {
            $this->processBatch();
        }
    }
}
```

## Security Considerations

### Data Sanitization

```php
// src/EventListener/AuditSecurityListener.php
namespace App\EventListener;

use BenMacha\AuditBundle\Event\AuditEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuditSecurityListener implements EventSubscriberInterface
{
    private array $sensitiveFields = [
        'password',
        'token',
        'secret',
        'apiKey',
        'creditCard',
        'ssn'
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            AuditEvent::PRE_AUDIT => 'sanitizeData',
        ];
    }

    public function sanitizeData(AuditEvent $event): void
    {
        $changes = $event->getChanges();
        $sanitized = [];
        
        foreach ($changes as $field => $change) {
            if ($this->isSensitiveField($field)) {
                $sanitized[$field] = [
                    'old' => $this->maskValue($change['old']),
                    'new' => $this->maskValue($change['new'])
                ];
            } else {
                $sanitized[$field] = $change;
            }
        }
        
        $event->setChanges($sanitized);
    }

    private function isSensitiveField(string $field): bool
    {
        $field = strtolower($field);
        
        foreach ($this->sensitiveFields as $sensitive) {
            if (strpos($field, strtolower($sensitive)) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function maskValue($value): string
    {
        if ($value === null) {
            return 'null';
        }
        
        if (is_string($value) && strlen($value) > 4) {
            return substr($value, 0, 2) . str_repeat('*', strlen($value) - 4) . substr($value, -2);
        }
        
        return '***';
    }
}
```

### Access Control

```php
// src/Security/AuditVoter.php
namespace App\Security;

use BenMacha\AuditBundle\Entity\AuditLog;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class AuditVoter extends Voter
{
    const VIEW = 'view';
    const ROLLBACK = 'rollback';
    const EXPORT = 'export';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::ROLLBACK, self::EXPORT])
            && $subject instanceof AuditLog;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var AuditLog $auditLog */
        $auditLog = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($auditLog, $user);
            case self::ROLLBACK:
                return $this->canRollback($auditLog, $user);
            case self::EXPORT:
                return $this->canExport($auditLog, $user);
        }

        return false;
    }

    private function canView(AuditLog $auditLog, UserInterface $user): bool
    {
        // Admins can view all
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }
        
        // Auditors can view most things
        if (in_array('ROLE_AUDITOR', $user->getRoles())) {
            // But not sensitive user data
            if ($auditLog->getEntity() === 'App\\Entity\\User' && 
                $auditLog->getEntityId() !== $user->getId()) {
                return false;
            }
            return true;
        }
        
        // Users can only view their own data
        if ($auditLog->getEntity() === 'App\\Entity\\User' && 
            $auditLog->getEntityId() === $user->getId()) {
            return true;
        }
        
        return false;
    }

    private function canRollback(AuditLog $auditLog, UserInterface $user): bool
    {
        // Only admins can rollback
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return false;
        }
        
        // Can't rollback deletions
        if ($auditLog->getOperation() === 'delete') {
            return false;
        }
        
        // Can't rollback old changes (24 hours)
        $maxAge = new \DateTime('-24 hours');
        if ($auditLog->getCreatedAt() < $maxAge) {
            return false;
        }
        
        return true;
    }

    private function canExport(AuditLog $auditLog, UserInterface $user): bool
    {
        // Only admins and auditors can export
        return in_array('ROLE_ADMIN', $user->getRoles()) || 
               in_array('ROLE_AUDITOR', $user->getRoles());
    }
}
```

## Troubleshooting

### Common Issues

#### 1. Audit Logs Not Being Created

**Problem**: Changes to entities are not being audited.

**Solutions**:
- Ensure the entity has the `#[Auditable]` attribute
- Check that the bundle is properly registered
- Verify the event listener is configured
- Check for `#[IgnoreAudit]` attributes on fields

```php
// Debug audit configuration
use BenMacha\AuditBundle\Service\ConfigurationService;

$configService = $container->get(ConfigurationService::class);
$isEnabled = $configService->isAuditEnabled('App\\Entity\\YourEntity');
var_dump($isEnabled); // Should be true
```

#### 2. Performance Issues

**Problem**: Application is slow due to audit logging.

**Solutions**:
- Enable asynchronous processing
- Use batch processing for bulk operations
- Add database indexes
- Implement audit data retention

```sql
-- Add indexes for better performance
CREATE INDEX idx_audit_log_entity ON audit_log (entity, entity_id);
CREATE INDEX idx_audit_log_created ON audit_log (created_at);
CREATE INDEX idx_audit_log_user ON audit_log (user_id);
```

#### 3. Memory Issues

**Problem**: High memory usage during bulk operations.

**Solutions**:
- Disable auditing for bulk operations
- Use batch processing
- Clear entity manager periodically

```php
// Disable auditing for bulk operations
use BenMacha\AuditBundle\Service\AuditManager;

$auditManager = $container->get(AuditManager::class);
$auditManager->disable();

// Perform bulk operations
for ($i = 0; $i < 10000; $i++) {
    // ... bulk operations
    
    if ($i % 100 === 0) {
        $entityManager->flush();
        $entityManager->clear();
    }
}

$auditManager->enable();
```

### Debug Commands

```php
// src/Command/AuditDebugCommand.php
namespace App\Command;

use BenMacha\AuditBundle\Service\ConfigurationService;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:debug',
    description: 'Debug audit configuration and status'
)]
class AuditDebugCommand extends Command
{
    public function __construct(
        private ConfigurationService $configService,
        private AuditLogRepository $auditLogRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Audit Bundle Debug Information');
        
        // Configuration status
        $io->section('Configuration');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Global Enabled', $this->configService->isGloballyEnabled() ? 'Yes' : 'No'],
                ['Async Processing', $this->configService->isAsyncEnabled() ? 'Yes' : 'No'],
                ['Retention Days', $this->configService->getRetentionDays()],
            ]
        );
        
        // Entity configuration
        $io->section('Auditable Entities');
        $entities = $this->configService->getAuditableEntities();
        
        if (empty($entities)) {
            $io->warning('No auditable entities found');
        } else {
            $io->listing($entities);
        }
        
        // Recent activity
        $io->section('Recent Activity');
        $recentLogs = $this->auditLogRepository->findRecent(5);
        
        if (empty($recentLogs)) {
            $io->info('No recent audit logs found');
        } else {
            $rows = [];
            foreach ($recentLogs as $log) {
                $rows[] = [
                    $log->getId(),
                    $log->getEntity(),
                    $log->getOperation(),
                    $log->getCreatedAt()->format('Y-m-d H:i:s')
                ];
            }
            
            $io->table(['ID', 'Entity', 'Operation', 'Date'], $rows);
        }
        
        return Command::SUCCESS;
    }
}
```

## Best Practices

### 1. Entity Design

```php
// Good: Specific auditing configuration
#[Auditable(operations: ['create', 'update'])]
class Product
{
    #[AuditSensitive]
    private string $internalNotes;
    
    #[IgnoreAudit]
    private \DateTime $lastAccessed;
    
    #[AuditMetadata(label: 'Product Name', group: 'basic')]
    private string $name;
}

// Bad: No auditing configuration
class Product
{
    private string $name;
    private string $internalNotes;
    private \DateTime $lastAccessed;
}
```

### 2. Performance Considerations

```php
// Good: Batch processing for bulk operations
public function bulkUpdateProducts(array $products): void
{
    $this->auditManager->disable();
    
    foreach ($products as $product) {
        // Update product
        $this->entityManager->persist($product);
    }
    
    $this->entityManager->flush();
    $this->auditManager->enable();
    
    // Log bulk operation manually
    $this->auditManager->logBulkOperation(
        'Product',
        'bulk_update',
        ['count' => count($products)]
    );
}

// Bad: Auditing every single update
public function bulkUpdateProducts(array $products): void
{
    foreach ($products as $product) {
        $this->entityManager->persist($product);
        $this->entityManager->flush(); // Triggers audit for each
    }
}
```

### 3. Security

```php
// Good: Proper data sanitization
#[AuditSensitive(mask: true, hashAlgorithm: 'sha256')]
private string $creditCardNumber;

#[IgnoreAudit]
private string $password;

// Bad: Sensitive data in audit logs
private string $creditCardNumber;
private string $password;
```

### 4. Event Handling

```php
// Good: Specific event handling
class OrderAuditSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AuditEvent::POST_AUDIT => [
                ['onOrderAudit', 10], // High priority
            ],
        ];
    }

    public function onOrderAudit(AuditEvent $event): void
    {
        if (!$event->getEntity() instanceof Order) {
            return;
        }
        
        // Order-specific audit logic
        $this->notifyOrderManagement($event);
    }
}

// Bad: Generic event handling for all entities
class GenericAuditSubscriber implements EventSubscriberInterface
{
    public function onAnyAudit(AuditEvent $event): void
    {
        // This will be called for every audit event
        $this->doSomethingForEveryEntity($event);
    }
}
```

### 5. Testing

```php
// Good: Test audit functionality
class ProductAuditTest extends KernelTestCase
{
    public function testProductCreationIsAudited(): void
    {
        $product = new Product();
        $product->setName('Test Product');
        
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        
        $auditLogs = $this->auditLogRepository->findBy([
            'entity' => Product::class,
            'entityId' => $product->getId(),
            'operation' => 'create'
        ]);
        
        $this->assertCount(1, $auditLogs);
        $this->assertEquals('create', $auditLogs[0]->getOperation());
    }
}
```

## Conclusion

This guide covers the advanced usage patterns and customization options available in the Symfony Audit Bundle. By following these examples and best practices, you can implement a robust audit system that meets your specific requirements while maintaining good performance and security.

For more information, see:
- [Attributes Reference](ATTRIBUTES.md)
- [API Documentation](API.md)
- [Configuration Reference](CONFIGURATION.md)
```