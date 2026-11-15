# How MSM Sitemap works

A guide for contributors and for anyone debugging a site: where sitemaps are stored, the options and events the plugin uses, and where the code lives.

## Storage

Each daily sitemap is a post of the `msm_sitemap` post type:

| Field | Holds |
| --- | --- |
| `post_name`, `post_title` | The date, as `Y-m-d`. |
| `post_date` | Midnight on that date. |
| `post_modified` | When the sitemap was last built. The index uses it for `lastmod`. |
| `post_status` | Always `publish`. |
| `msm_sitemap_xml` meta | The finished sitemap XML, served as is. |
| `msm_indexed_url_count` meta | The number of URLs in the sitemap. |

The post type isn't public and has no admin menu. The index is built from these posts when it's requested, newest first, up to 10,000 of them. The daily sitemaps are only built when content changes, never when they're requested.

## Options

| Option | Holds |
| --- | --- |
| `msm_sitemap_cron_enabled` | Whether automatic updates are on. Missing until they're first turned on or off. |
| `msm_sitemap_update_last_run` | When the 15-minute check last ran, as a Unix timestamp. |
| `msm_sitemap_indexed_url_count` | The total number of URLs across all sitemaps, shown on **Tools → Sitemap**. |
| `msm_sitemap_create_in_progress` | Whether a full build from the admin is running. |
| `msm_stop_processing` | Set by **Halt Sitemap Generation** to stop a full build. |
| `msm_years_to_process`, `msm_months_to_process`, `msm_days_to_process` | A full build's remaining work, so it can resume. |

The oldest year with content is cached for a week in the `msm_sitemap` object cache group.

## Uninstalling

Deactivating or deleting the plugin leaves its sitemap posts and options in place. To remove them, before deactivating:

```shell
wp msm-sitemap cron reset
wp msm-sitemap delete --all --yes
wp option delete msm_sitemap_update_last_run msm_sitemap_indexed_url_count
```

## Scheduled events

| Event | Scheduled by |
| --- | --- |
| `msm_cron_update_sitemap` | Turning on automatic updates. Runs every 15 minutes and calls `Metro_Sitemap::update_sitemap_from_modified_posts()`. |
| `msm_cron_generate_sitemap_for_year_month_day` | `MSM_Sitemap_Builder_Cron`, in response to `msm_update_sitemap_for_year_month_date`, for each day that needs rebuilding. |
| `msm_cron_generate_sitemap_for_year`, `msm_cron_generate_sitemap_for_year_month` | A full build from the admin. Each day's event schedules the next day, then the next month and year, until the build finishes or is halted. |

## Code

| File | Responsibility |
| --- | --- |
| `msm-sitemap.php` | `Metro_Sitemap`: setup, rewrite rules, robots.txt, the post type, finding posts, building and deleting daily sitemaps, building the index, and reacting to post changes. |
| `includes/msm-sitemap-builder-cron.php` | `MSM_Sitemap_Builder_Cron`: rebuilding days and full builds through WP-Cron. Turned off by `msm_sitemap_use_cron_builder`. |
| `includes/CronService.php` | `Cron_Service`: turning automatic updates on and off, and reporting their status. Used by the admin screen, WP-CLI and the builder. |
| `includes/Site.php` | `Site`: whether the site is public, whether sitemaps are enabled, and the index URLs. |
| `includes/Permalinks.php` | Makes `get_permalink()` return each sitemap post's public URL. |
| `includes/StylesheetManager.php` | The XSL stylesheet URLs and their content. |
| `includes/Admin/` | The **Tools → Sitemap** screen (`UI`), its form actions (`Action_Handlers`), and its notices. |
| `includes/Domain/ValueObjects/` | Immutable, validated values for URL entries, URL sets and index entries. |
| `includes/Infrastructure/Factories/` | Build those values from WordPress data, applying the per-URL filters. |
| `includes/wp-cli.php` | `Metro_Sitemap_CLI`: the [WP-CLI commands](wp-cli.md). |
| `templates/full-sitemaps.php` | Serves sitemap requests. |

A request for `/sitemap.xml` is routed by a rewrite rule to `index.php?sitemap=true`. The plugin skips the main query and loads `templates/full-sitemaps.php`, which returns the index or a stored daily sitemap, or a 404.

For setting up a local environment and running the tests, see the [contributing guide](../CONTRIBUTING.md).
