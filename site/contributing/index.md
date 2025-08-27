---
layout: default
title: Contributing
permalink: /contributing/
---

# Contributing to Symfony Audit Bundle

Thank you for considering contributing to the Symfony Audit Bundle! Your contributions help make this project a valuable tool for the Symfony community.

## Quick Links

- [Complete Contributing Guide](../CONTRIBUTING.html) - Detailed contribution guidelines
- [GitHub Repository](https://github.com/benmacha/audit-bundle) - Source code and issues
- [Issue Tracker](https://github.com/benmacha/audit-bundle/issues) - Report bugs or request features
- [Pull Requests](https://github.com/benmacha/audit-bundle/pulls) - Submit your contributions

## Ways to Contribute

### 🐛 Report Bugs

Found a bug? Help us improve by reporting it:

1. Check existing issues to avoid duplicates
2. Use our [bug report template](https://github.com/benmacha/audit-bundle/issues/new?template=bug_report.yml)
3. Provide detailed reproduction steps
4. Include environment information (PHP, Symfony versions)

### 💡 Suggest Features

Have an idea for improvement?

1. Check if it's already been suggested
2. Use our [feature request template](https://github.com/benmacha/audit-bundle/issues/new?template=feature_request.yml)
3. Explain the use case and benefits
4. Consider implementation complexity

### 🔧 Contribute Code

Ready to write some code?

1. **Fork** the repository
2. **Clone** your fork locally
3. **Create** a feature branch
4. **Make** your changes
5. **Test** thoroughly
6. **Submit** a pull request

### 📚 Improve Documentation

Documentation improvements are always welcome:

- Fix typos or unclear explanations
- Add missing examples
- Improve API documentation
- Translate documentation

## Getting Started

### Prerequisites

- **PHP 8.1+**
- **Composer**
- **Git**
- **Symfony knowledge** (basic to intermediate)

### Development Setup

```bash
# 1. Fork and clone the repository
git clone https://github.com/YOUR_USERNAME/audit-bundle.git
cd audit-bundle

# 2. Install dependencies
composer install

# 3. Set up environment
cp .env.example .env
# Edit .env with your configuration

# 4. Run tests to verify setup
composer test
```

## Code Quality Standards

We maintain high code quality standards:

### Coding Standards

- **PSR-12** coding standard
- **PHPStan level 8** static analysis
- **Symfony** coding conventions
- **PHP 8.1+** features and syntax

### Quality Checks

```bash
# Check code style
composer cs-check

# Fix code style
composer cs-fix

# Run static analysis
composer phpstan

# Run all tests
composer test

# Run all quality checks
composer quality
```

### Best Practices

- ✅ Use type declarations for all parameters and return types
- ✅ Write self-documenting code with clear names
- ✅ Add PHPDoc blocks for complex methods
- ✅ Follow SOLID principles
- ✅ Keep methods small and focused (max 20-30 lines)
- ✅ Use dependency injection
- ✅ Handle exceptions appropriately
- ✅ Write comprehensive tests

## Testing

All contributions must include appropriate tests:

### Test Types

- **Unit Tests** - Test individual classes and methods
- **Integration Tests** - Test component interactions
- **Functional Tests** - Test complete features

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Integration

# Run with coverage
composer test-coverage
```

### Writing Tests

```php
<?php
// Example test structure
namespace BenMacha\AuditBundle\Tests\Unit\Service;

use BenMacha\AuditBundle\Service\AuditManager;
use PHPUnit\Framework\TestCase;

class AuditManagerTest extends TestCase
{
    public function testSomething(): void
    {
        // Arrange
        $service = new AuditManager(/* dependencies */);
        
        // Act
        $result = $service->doSomething();
        
        // Assert
        $this->assertSame('expected', $result);
    }
}
```

## Pull Request Process

### Before Submitting

1. ✅ Ensure all tests pass
2. ✅ Run quality checks
3. ✅ Update documentation if needed
4. ✅ Add changelog entry
5. ✅ Rebase on latest main branch

### PR Guidelines

- **Clear title** describing the change
- **Detailed description** explaining what and why
- **Link related issues** using keywords (fixes #123)
- **Small, focused changes** (one feature per PR)
- **Good commit messages** following conventional commits

### Review Process

1. **Automated checks** must pass
2. **Code review** by maintainers
3. **Testing** in different environments
4. **Approval** and merge

## Community Guidelines

### Code of Conduct

We are committed to providing a welcoming and inclusive environment. Please:

- Be respectful and constructive
- Welcome newcomers and help them learn
- Focus on what's best for the community
- Show empathy towards other community members

### Communication

- **GitHub Issues** - Bug reports and feature requests
- **GitHub Discussions** - General questions and ideas
- **Pull Requests** - Code contributions and reviews

## Recognition

We value all contributions and recognize contributors:

- **Contributors list** in README
- **Changelog mentions** for significant contributions
- **Special thanks** in release notes

## Need Help?

Don't hesitate to ask for help:

- 📖 Read the [complete contributing guide](../CONTRIBUTING.html)
- 💬 Start a [GitHub discussion](https://github.com/benmacha/audit-bundle/discussions)
- 🐛 Check existing [issues](https://github.com/benmacha/audit-bundle/issues)
- 📧 Contact maintainers if needed

## What's Next?

After reading this overview:

1. **Read** the [complete contributing guide](../CONTRIBUTING.html)
2. **Set up** your development environment
3. **Look** for [good first issues](https://github.com/benmacha/audit-bundle/labels/good%20first%20issue)
4. **Start** contributing!

Thank you for helping make the Symfony Audit Bundle better! 🚀