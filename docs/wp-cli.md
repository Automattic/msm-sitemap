# WP-CLI Commands

MSM Sitemap provides a flexible WP-CLI interface for advanced management:

## Commands

### generate
Generate sitemaps for all or specific dates.

**Options:**
- `--all` – Generate sitemaps for all dates.
- `--date=<YYYY|YYYY-MM|YYYY-MM-DD>` – Generate for a specific year, month, or day.
- `--force` – Force regeneration even if sitemaps exist.
- `--quiet` – Suppress output except errors.

**Examples:**
```shell
# Generate all sitemaps
$ wp msm-sitemap generate --all
Success: Generated 235 sitemaps.

# Generate sitemaps for July 2024
$ wp msm-sitemap generate --date=2024-07
Success: Generated 26 sitemaps.

# Generate a sitemap for a specific day, forcing regeneration
$ wp msm-sitemap generate --date=2024-07-13 --force
Success: Generated 1 sitemap.

# Generate all sitemaps, suppressing output
$ wp msm-sitemap generate --all --quiet
# (no output unless there is an error)
```

### delete
Delete sitemaps for all or specific dates.

**Options:**
- `--all` – Delete all sitemaps. Requires confirmation (unless `--yes` is used).
- `--date=<YYYY|YYYY-MM|YYYY-MM-DD>` – Delete for a specific date.
- `--quiet` – Suppress output except errors.
- `--yes` – Answer yes to any confirmation prompts (skips confirmation for destructive actions; recommended for scripts/automation).

You must specify either `--date` or `--all`. If `--all` is used, or `--date` matches multiple sitemaps, you must confirm deletion (or use `--yes`). The command will refuse to run if neither is provided.

**Examples:**
```shell
# Delete all sitemaps (with confirmation)
$ wp msm-sitemap delete --all
Are you sure you want to delete ALL sitemaps? [y/n] y
Success: Deleted 235 sitemaps.

# Delete all sitemaps, skipping confirmation
$ wp msm-sitemap delete --all --yes
Success: Deleted 235 sitemaps.

# Delete sitemaps for July 2024 (multiple sitemaps, with confirmation)
$ wp msm-sitemap delete --date=2024-07
Are you sure you want to delete 26 sitemaps for the specified date? [y/n] y
Success: Deleted 26 sitemaps.

# Delete a single sitemap for a specific day
$ wp msm-sitemap delete --date=2024-07-10
Success: Deleted 1 sitemap.

# Delete a single sitemap for a specific day, suppressing output
$ wp msm-sitemap delete --date=2024-07-10 --quiet
# (no output unless there is an error)
```

### list
List sitemaps.

**Options:**
- `--all` or `--date=<date>`
- `--fields=<fields>` – Comma-separated list (id,date,url_count,status).
- `--format=<format>` – table, json, csv.

**Examples:**
```shell
# List all sitemaps in JSON format
$ wp msm-sitemap list --all --format=json
[
  {"id":123,"date":"2024-07-10","url_count":50,"status":"publish"},
  {"id":124,"date":"2024-07-11","url_count":48,"status":"publish"},
  {"id":125,"date":"2024-07-12","url_count":52,"status":"publish"}
]

# List sitemaps for July 2024, showing only id, date, and url_count
$ wp msm-sitemap list --date=2024-07 --fields=id,date,url_count
+-----+------------+-----------+
| id  | date       | url_count |
+-----+------------+-----------+
| 123 | 2024-07-10 | 50        |
| 124 | 2024-07-11 | 48        |
| 125 | 2024-07-12 | 52        |
+-----+------------+-----------+
```

### get
Get details for a sitemap by ID or date.

**Arguments:**
- `<id|date>` – Sitemap post ID or date.

**Options:**
- `--format=<format>` – table, json, csv.

**Examples:**
```shell
# Get details for sitemap ID 123 in JSON format
$ wp msm-sitemap get 123 --format=json
[
  {"id":123,"date":"2024-07-10","url_count":50,"status":"publish","last_modified":"2024-07-10 12:34:56"}
]

# Get details for a specific date
$ wp msm-sitemap get 2024-07-10
+-----+------------+-----------+----------+---------------------+
| id  | date       | url_count | status   | last_modified       |
+-----+------------+-----------+----------+---------------------+
| 123 | 2024-07-10 | 50        | publish  | 2024-07-10 12:34:56 |
+-----+------------+-----------+----------+---------------------+
```

### validate
Validate sitemaps for all or specific dates.

**Options:**
- `--all` or `--date=<date>`

**Examples:**
```shell
# Validate all sitemaps
$ wp msm-sitemap validate --all
Success: 235 sitemaps valid.

# Validate sitemaps for July 2024
$ wp msm-sitemap validate --date=2024-07
Success: 26 sitemaps valid.
```

### export
Export sitemaps to a directory.

**Options:**
- `--all` or `--date=<date>`
- `--output=<path>` (required) – Output directory or file path. The directory will be created if it does not exist.
- `--pretty` (optional) – Pretty-print (indent) the exported XML for human readability.

After export, the command will show the absolute path to the export directory and a shell command to open it (e.g., `open "/path/to/my-export"`).

**Examples:**
```shell
# Export all sitemaps to a directory
$ wp msm-sitemap export --all --output=path/to/my-export
Success: Exported 235 sitemaps to /absolute/path/to/my-export.
To view the files, run: open "/absolute/path/to/my-export"

# Export sitemaps for July 2024, pretty-printed
$ wp msm-sitemap export --date=2024-07 --output=path/to/my-export --pretty
Success: Exported 26 sitemaps to /absolute/path/to/my-export.
To view the files, run: open "/absolute/path/to/my-export"
```

### recount
Recalculate and update the indexed URL count for all sitemap posts.

**Options:**
- No arguments.

**Example:**
```shell
# Recalculate indexed URL counts
$ wp msm-sitemap recount
Total URLs found: 1234
Number of sitemaps found: 235
```

### stats
Show sitemap statistics (total, most recent, etc).

**Options:**
- `--format=<format>` – table, json, csv.
- `--detailed` – Show comprehensive statistics including timeline, coverage, and storage info.
- `--section=<section>` – Show only a specific section: overview, timeline, url_counts, performance, coverage, storage.

**Examples:**
```shell
# Show basic sitemap statistics in table format
$ wp msm-sitemap stats --format=table
+-------+--------------------------+---------------------+
| total | most_recent              | created             |
+-------+--------------------------+---------------------+
| 235   | 2024-07-12 (ID 125)      | 2024-07-12 13:45:00 |
+-------+--------------------------+---------------------+

# Show detailed statistics in JSON format
$ wp msm-sitemap stats --detailed --format=json
{
  "overview": {
    "total_sitemaps": 235,
    "total_urls": 12345,
    "most_recent": { ... },
    "oldest": { ... },
    "average_urls_per_sitemap": 52.5
  },
  "timeline": { ... },
  "url_counts": { ... },
  "performance": { ... },
  "coverage": { ... },
  "storage": { ... }
}

# Show only coverage statistics
$ wp msm-sitemap stats --section=coverage --format=json
{
  "date_coverage": 85.2,
  "total_days": 365,
  "covered_days": 311,
  "gaps": ["2024-02-15", "2024-03-01"],
  "continuous_streaks": [ ... ]
}
```

### recent-urls
Show recent URL counts for the last N days.

**Options:**
- `--days=<days>` – Number of days to show (default: 7).
- `--format=<format>` – table, json, csv.

**Example:**
```shell
# Show URL counts for the last 14 days
$ wp msm-sitemap recent-urls --days=14 --format=table
+------------+-----------+
| date       | url_count |
+------------+-----------+
| 2024-07-01 | 45        |
| 2024-07-02 | 52        |
| ...        | ...       |
+------------+-----------+
```

## Cron Management Commands

### cron enable
Enable automatic sitemap updates.

**Options:**
- No arguments.

**Example:**
```shell
# Enable automatic sitemap updates
$ wp msm-sitemap cron enable
Success: ✅ Automatic sitemap updates enabled successfully.
```
**Note:** If already enabled, shows: `Warning: ⚠️ Automatic updates are already enabled.`

### cron disable
Disable automatic sitemap updates.

**Options:**
- No arguments.

**Example:**
```shell
# Disable automatic sitemap updates
$ wp msm-sitemap cron disable
Success: ✅ Automatic sitemap updates disabled successfully.
✅ Cron events cleared successfully.
```
**Note:** If already disabled, shows: `Warning: ⚠️ Automatic updates are already disabled.`

### cron status
Check the status of automatic updates.

**Options:**
- `--format=<format>` – table, json, csv.

**Example:**
```shell
# Check cron status in table format
$ wp msm-sitemap cron status --format=table
+---------+-------------------------+-------------+------------+--------+-------------------+
| enabled | next_scheduled          | blog_public | generating | halted | current_frequency |
+---------+-------------------------+-------------+------------+--------+-------------------+
| Yes     | 2025-08-01 14:30:00 UTC | Yes         | No         | No     | 15min             |
+---------+-------------------------+-------------+------------+--------+-------------------+
```

### cron frequency
View or update the automatic update frequency.

**Arguments:**
- `[<frequency>]` – Optional frequency to set. If not provided, shows current frequency and valid options.

**Valid frequencies:** `5min`, `10min`, `15min`, `30min`, `hourly`, `2hourly`, `3hourly`

**Examples:**
```shell
# Show current frequency and valid options
$ wp msm-sitemap cron frequency
Current cron frequency: 15min
Valid frequencies:
  - 5min
  - 10min
  - 15min
  - 30min
  - hourly
  - 2hourly
  - 3hourly

# Update to hourly frequency
$ wp msm-sitemap cron frequency hourly
Success: ✅ Automatic update frequency successfully changed.
```

### cron reset
Reset cron to clean state (for testing).

**Options:**
- No arguments.

**Example:**
```shell
# Reset cron to clean state
$ wp msm-sitemap cron reset
Success: ✅ Sitemap cron reset to clean state.
📝 This simulates a fresh install state.
```

## Legacy Commands (1.4.2 and earlier)

As of 1.5.0, the following legacy commands are still supported but are soft-deprecated. Please use the API commands above for all new scripts and automation.

| Legacy Command | API Equivalent |
| -------------- | -------------- |
| `generate-sitemap` | `generate` |
| `generate-sitemap-for-year --year=YYYY` | `generate --date=YYYY` |
| `generate-sitemap-for-year-month --year=YYYY --month=MM` | `generate --date=YYYY-MM` |
| `generate-sitemap-for-year-month-day --year=YYYY --month=MM --day=DD` | `generate --date=YYYY-MM-DD` |
| `recount-indexed-posts` | `recount` |

**Examples:**
```shell
# Legacy
wp msm-sitemap generate-sitemap-for-year --year=2024

# Current
wp msm-sitemap generate --date=2024
```

## Getting Help

See `wp help msm-sitemap <command>` for full details and options.
