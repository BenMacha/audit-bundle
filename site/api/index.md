---
layout: default
title: API Reference
permalink: /api/
---

# API Reference

Complete reference for the Symfony Audit Bundle REST API and PHP services.

## REST API Documentation

The Symfony Audit Bundle provides a comprehensive REST API for accessing and managing audit data. The API supports authentication, filtering, pagination, and various operations on audit entries.

### Quick Links

- [Complete API Documentation](../docs/API.html) - Detailed API reference with all endpoints
- [Usage Guide](../docs/USAGE_GUIDE.html) - Learn how to use the bundle
- [Attributes Reference](../docs/ATTRIBUTES.html) - Available PHP attributes

## API Overview

### Authentication

All API endpoints require authentication using Bearer tokens:

```bash
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
     http://your-app.com/api/audit
```

### Main Endpoints

| Endpoint | Method | Description |
|----------|--------|--------------|
| `/api/audit` | GET | Retrieve audit entries with filtering |
| `/api/audit/{id}` | GET | Get a specific audit entry |
| `/api/audit/{id}/rollback` | POST | Rollback entity to previous state |
| `/api/audit/entity/{entity}/{id}/history` | GET | Get complete entity history |
| `/api/audit/stats` | GET | Get audit statistics |

### Key Features

- **Filtering**: Filter audit entries by entity, action, user, date range, and more
- **Pagination**: Built-in pagination support with configurable page sizes
- **Sorting**: Sort results by various fields (date, action, entity)
- **Rollback**: Revert entities to previous states
- **Statistics**: Get insights into audit activity

### Response Format

All API responses follow a consistent JSON format:

```json
{
  "data": [...],
  "meta": {
    "total": 150,
    "page": 1,
    "limit": 20,
    "pages": 8
  }
}
```

## PHP Services

The bundle also provides PHP services for programmatic access:

- **AuditManager**: Core service for managing audit operations
- **ConfigurationService**: Service for managing audit configuration
- **MetadataCollector**: Service for collecting entity metadata
- **RollbackService**: Service for handling entity rollbacks

## Getting Started

1. **Authentication**: Set up API authentication in your application
2. **Basic Usage**: Start with simple GET requests to `/api/audit`
3. **Filtering**: Use query parameters to filter results
4. **Advanced Features**: Explore rollback and statistics endpoints

For detailed information about each endpoint, parameters, and response formats, see the [Complete API Documentation](../docs/API.html).