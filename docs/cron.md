# Cron Management

MSM Sitemap uses WordPress cron for automatic sitemap generation and updates. This document covers cron management, configuration, and testing.

## Overview

MSM Sitemap uses asynchronous generation via WP-Cron to avoid timeouts and memory issues on large sites. The cron system handles:

- **Automatic sitemap updates** when new content is published
- **Missing sitemap generation** for dates without sitemaps
- **Stale sitemap regeneration** for dates with modified content
- **Full sitemap regeneration** for complete rebuilds

## Architecture

The sitemap generation system follows the Single Responsibility Principle with separate services for detection, scheduling, and execution.

### Detection Services (What needs generation?)

These services implement `SitemapDateProviderInterface` and identify dates that need sitemap generation:

```
┌─────────────────────────────────────┐
│    SitemapDateProviderInterface     │
│    get_dates(): array<string>       │
│    get_type(): string               │
│    get_description(): string        │
└─────────────────────────────────────┘
         ▲           ▲           ▲
         │           │           │
┌────────┴──┐ ┌──────┴─────┐ ┌───┴────────────┐
│  Missing  │ │   Stale    │ │  AllDates      │
│ Detection │ │ Detection  │ │ WithPosts      │
│  Service  │ │  Service   │ │  Service       │
└───────────┘ └────────────┘ └────────────────┘
```

- **`MissingSitemapDetectionService`**: Finds dates with posts but no sitemap
- **`StaleSitemapDetectionService`**: Finds dates where sitemap is older than latest post modification
- **`AllDatesWithPostsService`**: Provides all dates with published posts (for full regeneration)

### Scheduling & Execution (How generation happens?)

```
┌──────────────────────────────────────────────────────────────────┐
│                    BackgroundGenerationScheduler                     │
│   (Central scheduler for both direct and background generation)  │
├──────────────────────────────────────────────────────────────────┤
│  schedule(dates): Schedule background cron events                 │
│  generate_now(dates): Generate sitemaps directly (blocking)       │
│  generate_for_date(date): Generate single sitemap                 │
│  is_in_progress(): Check if background generation is running      │
│  get_progress(): Get {total, remaining, completed}                │
│  cancel(): Cancel in-progress generation                          │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│                 BackgroundGenerationCronHandler                   │
│      (Handles scheduled individual date generation events)        │
├──────────────────────────────────────────────────────────────────┤
│  register_hooks(): Register cron action handler                   │
│  handle_generate_for_date(date): Handle individual cron event     │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│                     GenerateSitemapUseCase                        │
│                  (Core sitemap generation logic)                  │
└──────────────────────────────────────────────────────────────────┘
```

### High-Level Generation Services

These orchestrate the detection and scheduling for specific workflows:

- **`FullGenerationService`**: Full sitemap regeneration
  - Uses `AllDatesWithPostsService` to get all dates
  - Schedules via `BackgroundGenerationScheduler`

- **`IncrementalGenerationService`**: Missing/stale sitemap generation ("incremental" = only what needs updating)
  - Uses `MissingSitemapDetectionService` (which includes stale detection)
  - Provides both direct (`generate()`) and background (`schedule()`) methods

- **`AutomaticUpdateCronHandler`**: Handler for the recurring automatic update cron
  - Handles `msm_cron_update_sitemap` hook (runs hourly by default)
  - Calls `IncrementalGenerationService.generate()` for direct generation

- **`BackgroundGenerationCronHandler`**: Handler for scheduled background generation events
  - Handles `msm_cron_generate_sitemap_for_date` events (individual date generation)
  - Used by both Full and Incremental background generation

## Generation Workflows

### 1. Automatic Incremental Updates

When cron is enabled, the system periodically checks for missing or stale sitemaps:

```
Cron Event (msm_cron_update_sitemap)
        │
        ▼
AutomaticUpdateCronHandler.execute()
        │
        ▼
IncrementalGenerationService.generate()
        │
        ▼
MissingSitemapDetectionService.get_missing_sitemaps()
        │
        ├── Missing dates (posts exist, no sitemap)
        │
        └── Stale dates (sitemap older than post modifications)
        │
        ▼
BackgroundGenerationScheduler.generate_now(dates)
        │
        ▼
SitemapCleanupService.cleanup_all_orphaned_sitemaps()
```

### 2. Background Generation (Schedule Background Generation Button)

When triggered from the admin UI:

```
Admin UI: "Schedule Background Generation" button
        │
        ▼
IncrementalGenerationService.schedule()
        │
        ▼
BackgroundGenerationScheduler.schedule(dates)
        │
        ├── Set progress tracking options
        │
        └── Schedule staggered cron events (5 second intervals)
                │
                ▼
        For each date (via cron):
        msm_cron_generate_sitemap_for_date
                │
                ▼
        BackgroundGenerationCronHandler.handle_generate_for_date()
                │
                ▼
        BackgroundGenerationScheduler.generate_for_date()
                │
                ▼
        BackgroundGenerationScheduler.record_date_completion()
                │
                ▼
        (When last date completes):
        SitemapCleanupService.cleanup_all_orphaned_sitemaps()
```

### 3. Direct Generation (Generate Now Button)

For immediate generation without background scheduling:

```
Admin UI: "Generate Now" button
        │
        ▼
IncrementalGenerationService.generate()
        │
        ▼
MissingSitemapDetectionService.get_missing_sitemaps()
        │
        ▼
BackgroundGenerationScheduler.generate_now(dates)
        │
        ▼
(Generates all sitemaps synchronously)
```

### 4. Full Regeneration

When triggered from admin UI or CLI:

```
Admin UI: "Regenerate All Sitemaps" button
        │
        ▼
FullGenerationService.start_full_generation()
        │
        ▼
AllDatesWithPostsService.get_dates()
        │
        ▼
BackgroundGenerationScheduler.schedule(dates)
        │
        └── (Same background flow as section 2)
```

### 5. CLI Direct Generation

For immediate generation without cron:

```
CLI: wp msm-sitemap generate --date=2024-01-15
        │
        ▼
GenerateSitemapUseCase.execute(command)
        │
        ▼
SitemapService.generate_for_date()
```

## Progress Tracking

Background generation tracks progress via WordPress options:

| Option | Description |
|--------|-------------|
| `msm_background_generation_in_progress` | Boolean: Is generation running? |
| `msm_background_generation_total` | Integer: Total dates to generate |
| `msm_background_generation_remaining` | Integer: Dates still pending |
| `msm_generation_in_progress` | Boolean: Full generation flag (for UI) |

Progress can be retrieved:

```php
$scheduler = $container->get( BackgroundGenerationScheduler::class );
$progress = $scheduler->get_progress();

// Returns:
// [
//     'in_progress' => true,
//     'total' => 150,
//     'remaining' => 75,
//     'completed' => 75
// ]
```

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

### Background Generation Interval

Individual date generation events are scheduled 5 seconds apart to avoid server overload:

```php
// In BackgroundGenerationScheduler
private const INTERVAL_BETWEEN_EVENTS = 5; // seconds
```

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

# Get missing sitemaps status
GET /wp-json/msm-sitemap/v1/missing

# Generate missing sitemaps
POST /wp-json/msm-sitemap/v1/generate-missing
```

### Admin Interface

The admin interface at **Settings > Sitemap** provides:

- Cron status display
- Enable/disable buttons
- Frequency selection
- Manual generation triggers
- Generation status monitoring

## WP VIP Compatibility

The architecture is designed to work well with WP VIP Cron Control:

1. **Staggered Events**: Individual date events are scheduled 5 seconds apart
2. **Single Event Per Action**: Only one `msm_cron_generate_sitemap_for_date` event runs at a time
3. **Non-Autoloaded Options**: Progress tracking uses `update_option(..., false)` to avoid autoload
4. **Graceful Cancellation**: The `cancel()` method properly cleans up scheduled events

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

### Manual Cron Event Execution

For testing background generation in wp-env:

```bash
# Run a specific date's generation
npx wp-env run cli wp cron event run msm_cron_generate_sitemap_for_date

# List all scheduled events
npx wp-env run cli wp cron event list
```

## Architecture Benefits

This architecture ensures:

- **Single Responsibility**: Detection services find dates; scheduler handles execution
- **Polymorphism**: All detection services implement the same interface
- **Testability**: Services can be tested independently
- **Flexibility**: Both direct and background generation share the same core scheduler
- **Maintainability**: Clear separation between "what needs generation" and "how to generate"
- **Extensibility**: New detection strategies can be added by implementing `SitemapDateProviderInterface`

## Troubleshooting

### Common Issues

1. **Cron not running**: Check if WordPress cron is working on your site
2. **Generation stuck**: Use the cancel functionality or `wp msm-sitemap cron reset`
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

### Check Progress

```bash
# Check if generation is in progress
wp option get msm_background_generation_in_progress

# Check progress details
wp option get msm_background_generation_total
wp option get msm_background_generation_remaining
```
