# Cron Management

MSM Sitemap uses WordPress cron for automatic sitemap generation and updates. This document covers cron management, configuration, and testing.

## Overview

MSM Sitemap uses asynchronous generation via WP-Cron to avoid timeouts and memory issues on large sites. The cron system handles:

- **Automatic sitemap updates** when new content is published
- **Missing sitemap generation** for dates without sitemaps
- **Full sitemap regeneration** for complete rebuilds
- **Frequency management** for update scheduling

## Service Layer Architecture

The plugin uses a **Service Layer pattern** for cron management to ensure separation of concerns and maintainability:

### Cron Management Services

* **`Cron_Service`** (`includes/CronService.php`): Single source of truth for all cron management logic
  - Handles enabling/disabling cron functionality
  - Manages WordPress cron events and options
  - Provides status checking and consistency validation
  - Used by CLI, admin UI, and cron job handlers
  - **Filter Support**: `msm_sitemap_cron_enabled` filter allows overriding cron status (useful for testing)

* **`CronManagementService`** (`includes/Application/Services/CronManagementService.php`): Centralized business logic
  - Manages cron frequency updates
  - Provides consistent messages and status information
  - Handles cron enable/disable operations
  - Used by CLI, REST API, and admin UI

* **`FullSitemapGenerationService`** (`includes/Application/Services/FullSitemapGenerationService.php`): Full generation management
  - Handles full sitemap generation initiation
  - Manages generation state and progress
  - Provides halt functionality for ongoing generation

### Sitemap Generation Services

* **`FullGenerationCronService`**: Handles full sitemap generation
* **`IncrementalGenerationCronService`**: Handles incremental updates
* **`MissingSitemapGenerationService`**: Manages missing sitemap detection and generation

## Configuration

### Frequency Options

Valid cron frequencies:
- `5min` - Every 5 minutes
- `10min` - Every 10 minutes  
- `15min` - Every 15 minutes (default)
- `30min` - Every 30 minutes
- `hourly` - Every hour
- `2hourly` - Every 2 hours
- `3hourly` - Every 3 hours

### WordPress Options

The plugin uses several WordPress options for cron management:

- `msm_sitemap_cron_enabled` - Whether cron is enabled (boolean)
- `msm_sitemap_cron_frequency` - Update frequency (string)
- `msm_sitemap_last_check` - Last time missing sitemaps were checked (timestamp)
- `msm_sitemap_last_update` - Last time sitemaps were updated (timestamp)
- `msm_sitemap_update_last_run` - Last time the update cron ran (timestamp)
- `msm_generation_in_progress` - Whether full generation is in progress (boolean)

## Management Interfaces

### WP-CLI Commands

```bash
# Enable automatic updates
wp msm-sitemap cron enable

# Disable automatic updates  
wp msm-sitemap cron disable

# Check status
wp msm-sitemap cron status

# Update frequency
wp msm-sitemap cron frequency hourly

# Reset to clean state
wp msm-sitemap cron reset
```

### REST API Endpoints

```bash
# Get cron status
GET /wp-json/msm-sitemap/v1/cron/status

# Enable cron
POST /wp-json/msm-sitemap/v1/cron/enable

# Disable cron
POST /wp-json/msm-sitemap/v1/cron/disable

# Update frequency
POST /wp-json/msm-sitemap/v1/cron/frequency

# Reset cron
POST /wp-json/msm-sitemap/v1/cron/reset
```

### Admin Interface

The admin interface at **Settings > Sitemap** provides:

- Cron status display
- Enable/disable buttons
- Frequency selection
- Manual generation triggers
- Generation status monitoring

## Testing

### Override Cron Status (Testing)

If you're writing tests or need to override the cron enabled status for development purposes, use this filter:

```php
add_filter( 'msm_sitemap_cron_enabled', 'my_override_cron_status' );
/**
 * Override the cron enabled status.
 *
 * @param bool $enabled Whether cron is currently enabled.
 * @return bool The desired cron status.
 */
function my_override_cron_status( bool $enabled ): bool {
    // Force cron to be enabled for testing
    return true;
    
    // Or force it to be disabled
    // return false;
}
```

### Cron Testing in PHPUnit

The `msm_sitemap_cron_enabled` filter allows tests to override cron status without modifying production code:

```php
// In tests, force cron to be enabled
add_filter( 'msm_sitemap_cron_enabled', '__return_true' );

// Or force it to be disabled
add_filter( 'msm_sitemap_cron_enabled', '__return_false' );
```

## Architecture Benefits

This architecture ensures:

- **Single Responsibility**: Each class has a clear, focused purpose
- **Testability**: Service logic can be tested independently using filters
- **Maintainability**: Changes to cron logic only require updating the service
- **Extensibility**: New features can be added without affecting existing code
- **Clean Separation**: UI rendering is separate from action handling

## Troubleshooting

### Common Issues

1. **Cron not running**: Check if WordPress cron is working on your site
2. **Generation stuck**: Use the halt generation feature to stop ongoing processes
3. **Frequency not updating**: Ensure the site has proper permissions to update options
4. **Blog not public**: Cron requires the blog to be public (not private)

### Debug Information

Use the cron status command to get detailed information:

```bash
wp msm-sitemap cron status --format=json
```

This will show:
- Whether cron is enabled
- Next scheduled time
- Blog public status
- Generation status
- Halt status
- Current frequency
