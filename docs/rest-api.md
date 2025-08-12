# REST API Reference

MSM Sitemap provides a comprehensive REST API for programmatic access to all sitemap management features. The API follows WordPress REST API conventions and requires proper authentication.

## Base URL
```
/wp-json/msm-sitemap/v1/
```

## Authentication
All endpoints require WordPress authentication via nonce or user session with `manage_options` capability.

## Endpoint Reference

### Sitemap Management

#### `GET /sitemaps`
List all sitemaps with optional filtering.

**Parameters:**
- `start_date` (string, optional) - Filter sitemaps from this date (YYYY-MM-DD)
- `end_date` (string, optional) - Filter sitemaps until this date (YYYY-MM-DD)
- `per_page` (integer, optional) - Number of sitemaps per page (default: 10)
- `page` (integer, optional) - Page number (default: 1)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "date": "2024-08-12",
      "url_count": 45,
      "sitemap_url": "https://example.com/sitemap-2024-08-12.xml",
      "created": "2024-08-12T10:30:00Z"
    }
  ],
  "total": 150,
  "total_pages": 15
}
```

#### `POST /sitemaps`
Create a new sitemap for a specific date.

**Parameters:**
- `date` (string, required) - Date for sitemap (YYYY-MM-DD)
- `force` (boolean, optional) - Force recreation if exists (default: false)

**Response:**
```json
{
  "success": true,
  "message": "Sitemap created for 2024-08-12 with 45 URLs.",
  "count": 45,
  "date": "2024-08-12"
}
```

**Status Codes:**
- `201 Created` - Sitemap created successfully
- `409 Conflict` - Sitemap already exists (unless force=true)
- `422 Unprocessable Entity` - Invalid date or no content found

#### `GET /sitemaps/{date}`
Get sitemap details for a specific date.

**Parameters:**
- `date` (string, required) - Date in URL path (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "date": "2024-08-12",
    "url_count": 45,
    "sitemap_url": "https://example.com/sitemap-2024-08-12.xml",
    "created": "2024-08-12T10:30:00Z",
    "xml_content": "<xml>...</xml>"
  }
}
```

#### `DELETE /sitemaps/{date}`
Delete sitemap for a specific date.

**Parameters:**
- `date` (string, required) - Date in URL path (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "message": "Sitemap for 2024-08-12 deleted successfully.",
  "count": 1
}
```

**Status Codes:**
- `204 No Content` - Sitemap deleted successfully
- `200 OK` - No sitemap found to delete

### Statistics

#### `GET /stats`
Get comprehensive sitemap statistics.

**Parameters:**
- `start_date` (string, optional) - Start date for stats (YYYY-MM-DD)
- `end_date` (string, optional) - End date for stats (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "data": {
    "total_sitemaps": 150,
    "total_urls": 6750,
    "most_recent_sitemap": "2024-08-12",
    "most_recent_urls": 45,
    "date_range": {
      "start": "2020-01-01",
      "end": "2024-08-12"
    }
  }
}
```

### Cron Management

#### `GET /cron/status`
Get cron status and configuration.

**Response:**
```json
{
  "success": true,
  "data": {
    "enabled": true,
    "next_scheduled": "2024-08-12T15:30:00Z",
    "blog_public": true,
    "generating": false,
    "halted": false,
    "current_frequency": "15min"
  }
}
```

#### `POST /cron/enable`
Enable automatic sitemap updates.

**Response:**
```json
{
  "success": true,
  "message": "Automatic sitemap updates enabled successfully."
}
```

**Status Codes:**
- `200 OK` - Cron enabled successfully
- `409 Conflict` - Cron already enabled
- `403 Forbidden` - Blog is not public

#### `POST /cron/disable`
Disable automatic sitemap updates.

**Response:**
```json
{
  "success": true,
  "message": "Automatic sitemap updates disabled successfully."
}
```

**Status Codes:**
- `200 OK` - Cron disabled successfully
- `409 Conflict` - Cron already disabled

#### `POST /cron/frequency`
Update automatic update frequency.

**Parameters:**
- `frequency` (string, required) - New frequency (5min, 10min, 15min, 30min, hourly, 2hourly, 3hourly)

**Response:**
```json
{
  "success": true,
  "message": "Automatic update frequency successfully changed.",
  "frequency": "hourly"
}
```

#### `POST /cron/reset`
Reset cron to clean state.

**Response:**
```json
{
  "success": true,
  "message": "Sitemap cron reset to clean state."
}
```

### Generation

#### `POST /generate-missing`
Generate missing/outdated sitemaps.

**Response:**
```json
{
  "success": true,
  "message": "Missing sitemap generation scheduled.",
  "missing_dates": ["2024-08-10", "2024-08-11"]
}
```

#### `POST /generate-full`
Start full sitemap generation.

**Response:**
```json
{
  "success": true,
  "message": "Starting sitemap generation...",
  "was_in_progress": false
}
```

**Status Codes:**
- `200 OK` - Generation started successfully
- `403 Forbidden` - Cron must be enabled

#### `POST /halt-generation`
Halt ongoing sitemap generation.

**Response:**
```json
{
  "success": true,
  "message": "Sitemap generation halted successfully."
}
```

#### `POST /recount`
Recount URLs in sitemaps.

**Parameters:**
- `date_queries` (array, optional) - Array of date query objects

**Response:**
```json
{
  "success": true,
  "message": "URL counts updated successfully.",
  "total_sitemaps": 150,
  "total_urls": 6750
}
```

#### `GET /recent-urls`
Get recent URL counts.

**Parameters:**
- `start_date` (string, optional) - Start date (YYYY-MM-DD)
- `end_date` (string, optional) - End date (YYYY-MM-DD)
- `per_page` (integer, optional) - Results per page (default: 10)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "date": "2024-08-12",
      "url_count": 45
    }
  ]
}
```

### Validation

#### `POST /validate`
Validate sitemaps for specified dates.

**Parameters:**
- `date_queries` (array, required) - Array of date query objects

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "date": "2024-08-12",
      "valid": true,
      "errors": []
    }
  ]
}
```

#### `GET /validate/{date}`
Get validation status for a specific date.

**Parameters:**
- `date` (string, required) - Date in URL path (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "data": {
    "date": "2024-08-12",
    "valid": true,
    "errors": [],
    "xml_content": "<xml>...</xml>"
  }
}
```

### Export

#### `GET /export`
Export sitemaps in various formats.

**Parameters:**
- `date_queries` (array, optional) - Array of date query objects
- `format` (string, optional) - Export format (json, csv, xml, default: json)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "date": "2024-08-12",
      "urls": [
        "https://example.com/post-1",
        "https://example.com/post-2"
      ]
    }
  ]
}
```

### Data Management

#### `POST /reset`
Reset all sitemap data.

**Response:**
```json
{
  "success": true,
  "message": "All sitemap data reset successfully."
}
```

## Error Responses

All endpoints return consistent error responses:

```json
{
  "success": false,
  "message": "Error description",
  "error_code": "specific_error_code"
}
```

## HTTP Status Codes

- `200 OK` - Request successful
- `201 Created` - Resource created successfully
- `204 No Content` - Request successful, no content to return
- `400 Bad Request` - Invalid request parameters
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `409 Conflict` - Resource conflict (e.g., already exists)
- `422 Unprocessable Entity` - Validation failed

## Testing the API

You can test the API using the admin test page at **Settings > Sitemap** or with curl:

```bash
# Get nonce for authentication
NONCE=$(wp user meta get 1 session_tokens --format=json | jq -r 'keys[0]')

# List sitemaps
curl -X GET "https://example.com/wp-json/msm-sitemap/v1/sitemaps" \
  -H "X-WP-Nonce: $NONCE"

# Create sitemap
curl -X POST "https://example.com/wp-json/msm-sitemap/v1/sitemaps" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $NONCE" \
  -d '{"date": "2024-08-12"}'
```
