# Contributing to Symfony Audit Bundle

First off, thank you for considering contributing to the Symfony Audit Bundle! It's people like you that make this project a great tool for the Symfony community.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Issue Reporting](#issue-reporting)
- [Documentation](#documentation)
- [Community](#community)

## Code of Conduct

This project and everyone participating in it is governed by our Code of Conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to [contact@benmacha.tn](mailto:contact@benmacha.tn).

### Our Pledge

We pledge to make participation in our project a harassment-free experience for everyone, regardless of age, body size, disability, ethnicity, gender identity and expression, level of experience, nationality, personal appearance, race, religion, or sexual identity and orientation.

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git
- A Symfony application for testing (optional but recommended)

### Quick Start

1. Fork the repository on GitHub
2. Clone your fork locally
3. Install dependencies
4. Create a feature branch
5. Make your changes
6. Run tests and quality checks
7. Submit a pull request

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples to demonstrate the steps**
- **Describe the behavior you observed and what behavior you expected**
- **Include screenshots if applicable**
- **Provide your environment details** (PHP version, Symfony version, etc.)

### Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- **A clear and descriptive title**
- **A detailed description of the proposed enhancement**
- **Explain why this enhancement would be useful**
- **Provide examples of how the enhancement would be used**
- **Consider the scope and complexity of the change**

### Contributing Code

We welcome code contributions! Here are some areas where you can help:

- **Bug fixes**
- **New features**
- **Performance improvements**
- **Documentation improvements**
- **Test coverage improvements**
- **Code quality improvements**

## Development Setup

### 1. Fork and Clone

```bash
# Fork the repository on GitHub, then clone your fork
git clone https://github.com/YOUR_USERNAME/audit-bundle.git
cd audit-bundle
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Set Up Development Environment

```bash
# Copy environment file
cp .env.example .env

# Edit .env with your database configuration
# DATABASE_URL="mysql://user:password@127.0.0.1:3306/audit_test"
```

### 4. Set Up Database (for testing)

```bash
# Create test database
php bin/console doctrine:database:create --env=test

# Run migrations
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

### 5. Verify Setup

```bash
# Run tests to verify everything is working
composer test

# Run quality checks
composer quality
```

## Coding Standards

We follow strict coding standards to maintain code quality and consistency.

### PHP Standards

- **PSR-12** coding standard
- **PHPStan level 8** static analysis
- **Symfony coding standards**
- **PHP 8.1+** features and syntax

### Code Style

```bash
# Check code style
composer cs-check

# Fix code style automatically
composer cs-fix
```

### Static Analysis

```bash
# Run PHPStan analysis
composer phpstan
```

### Code Quality Rules

1. **Use type declarations** for all parameters and return types
2. **Write self-documenting code** with clear variable and method names
3. **Add PHPDoc blocks** for complex methods and classes
4. **Follow SOLID principles**
5. **Keep methods small and focused** (max 20-30 lines)
6. **Use dependency injection** instead of static calls
7. **Handle exceptions appropriately**
8. **Avoid deep nesting** (max 3-4 levels)

### Example Code Style

```php
<?php

declare(strict_types=1);

namespace BenMacha\AuditBundle\Service;

use BenMacha\AuditBundle\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AuditService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Creates an audit log entry for the given entity change.
     */
    public function createAuditLog(
        string $entityClass,
        mixed $entityId,
        string $operation,
        array $changes = []
    ): AuditLog {
        $auditLog = new AuditLog();
        $auditLog->setEntityClass($entityClass);
        $auditLog->setEntityId((string) $entityId);
        $auditLog->setOperation($operation);
        $auditLog->setChanges($changes);
        $auditLog->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();

        $this->logger->info('Audit log created', [
            'entity_class' => $entityClass,
            'entity_id' => $entityId,
            'operation' => $operation,
        ]);

        return $auditLog;
    }
}
```

## Testing

We maintain high test coverage and require tests for all new features.

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
vendor/bin/phpunit tests/Unit/Service/AuditManagerTest.php

# Run tests with filter
vendor/bin/phpunit --filter testCreateAuditLog
```

### Test Types

1. **Unit Tests** - Test individual classes and methods
2. **Integration Tests** - Test component interactions
3. **Functional Tests** - Test complete features
4. **Performance Tests** - Test performance characteristics

### Writing Tests

```php
<?php

declare(strict_types=1);

namespace BenMacha\AuditBundle\Tests\Unit\Service;

use BenMacha\AuditBundle\Service\AuditManager;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class AuditManagerTest extends TestCase
{
    private AuditManager $auditManager;
    private MockObject $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->auditManager = new AuditManager($this->entityManager);
    }

    public function testCreateAuditLogSuccess(): void
    {
        // Arrange
        $entityClass = 'App\\Entity\\User';
        $entityId = 123;
        $operation = 'update';
        $changes = ['name' => ['old' => 'John', 'new' => 'Jane']];

        // Act
        $result = $this->auditManager->createAuditLog(
            $entityClass,
            $entityId,
            $operation,
            $changes
        );

        // Assert
        $this->assertInstanceOf(AuditLog::class, $result);
        $this->assertSame($entityClass, $result->getEntityClass());
        $this->assertSame((string) $entityId, $result->getEntityId());
        $this->assertSame($operation, $result->getOperation());
        $this->assertSame($changes, $result->getChanges());
    }
}
```

### Test Coverage Requirements

- **Minimum 90% code coverage** for new code
- **100% coverage** for critical components (security, data integrity)
- **All public methods** must have tests
- **Edge cases and error conditions** must be tested

## Pull Request Process

### Before Submitting

1. **Create a feature branch** from `main`
2. **Make your changes** following coding standards
3. **Add or update tests** for your changes
4. **Update documentation** if needed
5. **Run quality checks** and ensure they pass
6. **Write a clear commit message**

### Commit Message Format

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
type(scope): description

[optional body]

[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(audit): add support for custom metadata in audit logs

fix(rollback): resolve conflict detection for nested entities

docs(readme): update installation instructions for Symfony 7

test(manager): add integration tests for audit manager service
```

### Pull Request Template

When creating a pull request, please include:

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] New tests added for new functionality
- [ ] Manual testing completed

## Checklist
- [ ] Code follows project coding standards
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No breaking changes (or clearly documented)
```

### Review Process

1. **Automated checks** must pass (tests, code style, static analysis)
2. **At least one maintainer review** is required
3. **All feedback** must be addressed
4. **Squash and merge** is preferred for feature branches

## Issue Reporting

### Bug Reports

Use the bug report template:

```markdown
**Bug Description**
A clear description of the bug

**Steps to Reproduce**
1. Step one
2. Step two
3. Step three

**Expected Behavior**
What should happen

**Actual Behavior**
What actually happens

**Environment**
- PHP version:
- Symfony version:
- Bundle version:
- Database:
- OS:

**Additional Context**
Any other relevant information
```

### Feature Requests

Use the feature request template:

```markdown
**Feature Description**
Clear description of the proposed feature

**Use Case**
Why is this feature needed?

**Proposed Solution**
How should this feature work?

**Alternatives Considered**
Other approaches you've considered

**Additional Context**
Any other relevant information
```

## Documentation

### Types of Documentation

1. **Code Documentation** - PHPDoc comments
2. **User Documentation** - README, guides, examples
3. **API Documentation** - OpenAPI/Swagger specs
4. **Architecture Documentation** - Design decisions, patterns

### Documentation Standards

- **Clear and concise** language
- **Code examples** for complex features
- **Up-to-date** with current codebase
- **Proper formatting** with Markdown
- **Screenshots** for UI features

### Updating Documentation

When making changes that affect:
- **Public API** - Update PHPDoc and API docs
- **Configuration** - Update configuration reference
- **Features** - Update README and user guides
- **Installation** - Update installation instructions

## Community

### Getting Help

- **GitHub Discussions** - For questions and general discussion
- **GitHub Issues** - For bug reports and feature requests
- **Email** - For security issues: contact@benmacha.tn

### Communication Guidelines

- **Be respectful** and professional
- **Be patient** with responses
- **Provide context** when asking questions
- **Search existing issues** before creating new ones
- **Use clear titles** and descriptions

### Recognition

We recognize contributors in several ways:
- **Contributors list** in README
- **Changelog mentions** for significant contributions
- **GitHub releases** acknowledgments
- **Social media** shout-outs

## Release Process

### Versioning

We follow [Semantic Versioning](https://semver.org/):
- **MAJOR** - Breaking changes
- **MINOR** - New features (backward compatible)
- **PATCH** - Bug fixes (backward compatible)

### Release Checklist

1. Update version numbers
2. Update CHANGELOG.md
3. Run full test suite
4. Create release tag
5. Publish to Packagist
6. Update documentation
7. Announce release

---

## Thank You!

Your contributions make this project better for everyone. Whether you're fixing bugs, adding features, improving documentation, or helping other users, every contribution is valuable and appreciated.

Happy coding! 🚀