# Wiki Configuration

This document describes the automatic wiki generation configuration for the Symfony Audit Bundle.

## Wiki Page Mapping

The following source files are automatically synchronized to wiki pages:

| Source File | Wiki Page | Description |
|-------------|-----------|-------------|
| `README.md` | `Home.md` | Main landing page with overview, installation, and quick start |
| `docs/API.md` | `API-Documentation.md` | Complete API reference and usage examples |
| `docs/ATTRIBUTES.md` | `Attributes-Reference.md` | Comprehensive attributes documentation |
| `docs/USAGE_GUIDE.md` | `Usage-Guide.md` | Advanced usage patterns and best practices |
| `CONTRIBUTING.md` | `Contributing.md` | Development and contribution guidelines |
| `CHANGELOG.md` | `Changelog.md` | Version history and release notes |

## Generated Wiki Structure

```
wiki/
├── Home.md                    # Main page (from README.md)
├── API-Documentation.md       # API reference (from docs/API.md)
├── Attributes-Reference.md    # Attributes guide (from docs/ATTRIBUTES.md)
├── Usage-Guide.md            # Usage patterns (from docs/USAGE_GUIDE.md)
├── Contributing.md           # Contributing guide (from CONTRIBUTING.md)
├── Changelog.md              # Version history (from CHANGELOG.md)
├── _Sidebar.md               # Navigation sidebar
└── _Footer.md                # Common footer
```

## Navigation Structure

### Main Navigation
Each wiki page includes a navigation menu at the top with links to:
- Home
- API Documentation
- Attributes Reference
- Usage Guide
- Contributing
- Changelog

### Sidebar Navigation
The `_Sidebar.md` provides organized navigation with sections:

#### Getting Started
- Home
- Installation
- Quick Start

#### Documentation
- API Documentation
- Attributes Reference
- Usage Guide

#### Development
- Contributing
- Changelog

#### API Reference
- Core Services
- Repository Methods
- Event System

#### Attributes
- Auditable
- IgnoreAudit
- AuditSensitive
- AuditMetadata

#### Advanced Usage
- Event Subscription
- Manual Logging
- Performance Optimization
- Security Configuration

## Content Processing

### README.md Processing
- Skips title and badges section
- Starts from "## Features" section
- Maintains all installation and usage instructions

### Documentation Files Processing
- Includes full content except the main title
- Preserves all formatting and code examples
- Maintains internal links and references

### Table of Contents Generation
- Automatically generated for documents with 50+ lines
- Uses `markdown-toc` for consistent formatting
- Placed after navigation and before main content

### Footer Information
- Repository links (GitHub, Issues, Discussions)
- Last updated timestamp
- Consistent across all pages

## Workflow Triggers

The wiki sync workflow runs automatically when:

1. **Push to main branch** with changes to:
   - `README.md`
   - Any file in `docs/` directory
   - `CONTRIBUTING.md`
   - `CHANGELOG.md`
   - `.github/workflows/wiki-sync.yml`

2. **Manual trigger** via workflow_dispatch

## Features

### Automatic Features
- ✅ Cross-page navigation
- ✅ Table of contents for long documents
- ✅ Organized sidebar navigation
- ✅ Consistent footer with links
- ✅ Timestamp tracking
- ✅ Change detection (only updates when needed)
- ✅ Comprehensive commit messages
- ✅ Workflow summary reports

### Content Enhancements
- Navigation menu on every page
- Proper markdown formatting preservation
- Code syntax highlighting maintained
- Internal link structure preserved
- Responsive design compatibility

## Maintenance

### Adding New Documentation
To add new documentation to the wiki:

1. Create the source file in the appropriate location
2. Update the workflow script in `.github/workflows/wiki-sync.yml`
3. Add the new page to the navigation in `_Sidebar.md` generation
4. Update this configuration file

### Modifying Navigation
To modify the navigation structure:

1. Edit the navigation section in the workflow script
2. Update the `_Sidebar.md` generation section
3. Test the changes with a manual workflow run

### Troubleshooting

#### Common Issues
- **Wiki not updating**: Check if the wiki repository exists and has proper permissions
- **Formatting issues**: Verify markdown syntax in source files
- **Missing pages**: Ensure source files exist and are properly referenced
- **Navigation broken**: Check internal link formatting and page names

#### Debugging
- Check workflow logs in GitHub Actions
- Verify file paths and naming conventions
- Test markdown processing locally
- Validate wiki repository permissions

## Security Considerations

- Uses `GITHUB_TOKEN` for authentication
- Limited to repository scope permissions
- No external dependencies beyond Node.js and markdown-toc
- Secure handling of repository content

## Performance

- Only processes changed files
- Efficient change detection
- Minimal resource usage
- Fast execution (typically < 2 minutes)

---

*This configuration is automatically maintained and should be updated when making changes to the wiki sync workflow.*