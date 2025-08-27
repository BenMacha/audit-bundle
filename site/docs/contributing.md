---
layout: default
title: Contributing
permalink: /docs/contributing/
nav_order: 6
---

# Contributing to Symfony Audit Bundle

We welcome contributions from the community! This guide will help you get started with contributing to the project.

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git
- Symfony CLI (optional but recommended)

### Setting Up Development Environment

1. **Fork the repository** on GitHub

2. **Clone your fork**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/audit-bundle.git
   cd audit-bundle
   ```

3. **Install dependencies**:
   ```bash
   composer install
   ```

4. **Set up the test environment**:
   ```bash
   cp .env.test.dist .env.test
   # Edit .env.test with your database configuration
   ```

5. **Create test database**:
   ```bash
   php bin/console doctrine:database:create --env=test
   php bin/console doctrine:schema:create --env=test
   ```

6. **Run tests** to ensure everything works:
   ```bash
   composer test
   ```

## Development Workflow

### 1. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b bugfix/issue-number
```

### 2. Make Your Changes

- Write clean, well-documented code
- Follow PSR-12 coding standards
- Add tests for new functionality
- Update documentation if needed

### 3. Test Your Changes

```bash
# Run all tests
composer test

# Run specific test suite
composer test:unit
composer test:integration
composer test:functional

# Run code quality checks
composer cs:check
composer phpstan
```

### 4. Commit Your Changes

```bash
git add .
git commit -m "Add feature: description of your changes"
```

**Commit Message Guidelines:**
- Use present tense ("Add feature" not "Added feature")
- Use imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit first line to 72 characters
- Reference issues and pull requests when applicable

### 5. Push and Create Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a pull request on GitHub.

## Code Standards

### PHP Standards

We follow PSR-12 coding standards. Use PHP CS Fixer to ensure compliance:

```bash
# Check code style
composer cs:check

# Fix code style issues
composer cs:fix
```

### Code Quality

We use PHPStan for static analysis:

```bash
composer phpstan
```

### Documentation Standards

- Use PHPDoc for all public methods and properties
- Include type hints for all parameters and return values
- Write clear, concise comments for complex logic

**Example:**

```php
<?php

/**
 * Creates an audit entry for the given entity and action.
 *
 * @param object $entity The entity being audited
 * @param string $action The action performed (create, update, delete)
 * @param array<string, mixed> $changes The changes made to the entity
 * @param array<string, mixed> $context Additional context information
 *
 * @throws AuditException When the entity is not auditable
 */
public function createAuditEntry(
    object $entity,
    string $action,
    array $changes = [],
    array $context = []
): void {
    // Implementation
}
```

## Testing Guidelines

### Test Structure

We use PHPUnit for testing. Tests are organized as follows:

```
tests/
├── Unit/           # Unit tests for individual classes
├── Integration/    # Integration tests for service interactions
├── Functional/     # Functional tests for complete features
└── Fixtures/       # Test fixtures and data
```

### Writing Tests

#### Unit Tests

```php
<?php

namespace BenMacha\AuditBundle\Tests\Unit\Service;

use BenMacha\AuditBundle\Service\AuditService;
use PHPUnit\Framework\TestCase;

class AuditServiceTest extends TestCase
{
    public function testCreateAuditEntry(): void
    {
        // Arrange
        $service = new AuditService(/* dependencies */);
        $entity = new User();
        
        // Act
        $service->createAuditEntry($entity, 'create');
        
        // Assert
        $this->assertTrue(/* assertion */);
    }
}
```

#### Integration Tests

```php
<?php

namespace BenMacha\AuditBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuditIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }
    
    public function testAuditServiceIntegration(): void
    {
        $container = static::getContainer();
        $auditService = $container->get(AuditService::class);
        
        // Test service integration
    }
}
```

### Test Coverage

- Aim for at least 80% code coverage
- All public methods should have tests
- Test both success and failure scenarios
- Include edge cases and boundary conditions

```bash
# Generate coverage report
composer test:coverage
```

## Documentation

### Code Documentation

- Document all public APIs
- Include usage examples in docblocks
- Keep documentation up to date with code changes

### User Documentation

- Update relevant documentation files in `docs/`
- Include examples for new features
- Update the changelog for significant changes

## Pull Request Guidelines

### Before Submitting

- [ ] Tests pass (`composer test`)
- [ ] Code style is correct (`composer cs:check`)
- [ ] Static analysis passes (`composer phpstan`)
- [ ] Documentation is updated
- [ ] Changelog is updated (for significant changes)

### Pull Request Template

When creating a pull request, please include:

```markdown
## Description
Brief description of the changes.

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests added/updated
- [ ] Integration tests added/updated
- [ ] Manual testing performed

## Checklist
- [ ] Code follows project standards
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] Tests pass
```

### Review Process

1. **Automated checks** must pass (CI/CD pipeline)
2. **Code review** by maintainers
3. **Testing** in development environment
4. **Approval** and merge

## Issue Reporting

### Bug Reports

When reporting bugs, please include:

- **Environment details** (PHP version, Symfony version, etc.)
- **Steps to reproduce** the issue
- **Expected behavior**
- **Actual behavior**
- **Error messages** or logs
- **Minimal code example** if possible

### Feature Requests

For feature requests, please include:

- **Use case** description
- **Proposed solution**
- **Alternative solutions** considered
- **Additional context**

## Security Issues

For security-related issues:

- **Do not** create public issues
- Email security concerns to: contact@benmacha.tn
- Include detailed information about the vulnerability
- Allow time for investigation and fix before disclosure

## Community Guidelines

### Code of Conduct

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on constructive feedback
- Respect different viewpoints and experiences

### Communication

- **GitHub Issues**: Bug reports, feature requests
- **GitHub Discussions**: General questions, ideas
- **Pull Requests**: Code contributions

## Recognition

Contributors are recognized in:

- **CONTRIBUTORS.md** file
- **Release notes** for significant contributions
- **GitHub contributors** page

## Development Tools

### Recommended IDE Setup

**PhpStorm:**
- Install Symfony plugin
- Configure PHP CS Fixer
- Set up PHPStan integration

**VS Code:**
- Install PHP extensions
- Configure PHP CS Fixer extension
- Set up PHPStan extension

### Useful Commands

```bash
# Development server
symfony serve

# Watch tests
composer test:watch

# Code quality check
composer quality

# Full CI check locally
composer ci
```

## Getting Help

If you need help:

1. Check existing [documentation]({{ site.baseurl }}/docs/)
2. Search [existing issues]({{ site.repository }}/issues)
3. Create a [new discussion]({{ site.repository }}/discussions)
4. Join our community chat (if available)

## Thank You!

Thank you for contributing to the Symfony Audit Bundle! Your contributions help make this project better for everyone.

---

**Next Steps:**
- [Installation Guide]({{ site.baseurl }}/docs/installation/) - Get started with the bundle
- [API Reference]({{ site.baseurl }}/docs/api/) - Complete API documentation
- [Troubleshooting]({{ site.baseurl }}/docs/troubleshooting/) - Common issues and solutions