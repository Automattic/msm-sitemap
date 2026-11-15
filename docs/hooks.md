# Filters and actions

Every hook MSM Sitemap provides. For worked examples, see [Customizing MSM Sitemap](customizing.md).

Most of these hooks change what goes into a sitemap when it's built. Sitemaps are stored once built, so rebuild them after changing those hooks: `wp msm-sitemap generate --all`.

| Hook | Type | What it controls |
| --- | --- | --- |
| [`msm_sitemap_entry_post_type`](#msm_sitemap_entry_post_type) | Filter | Post types included |
| [`msm_sitemap_post_status`](#msm_sitemap_post_status) | Filter | Post status included |
| [`msm_sitemap_skip_post`](#msm_sitemap_skip_post) | Filter | Leaving a post out |
| [`msm_sitemap_changefreq`](#msm_sitemap_changefreq) | Filter | A URL's `changefreq` |
| [`msm_sitemap_priority`](#msm_sitemap_priority) | Filter | A URL's `priority` |
| [`msm_sitemap_entry`](#msm_sitemap_entry) | Filter | Each `<url>` element |
| [`msm_sitemap_entry_posts_per_page`](#msm_sitemap_entry_posts_per_page) | Filter | Maximum URLs per day |
| [`msm_sitemap_namespace`](#msm_sitemap_namespace) | Filter | XML namespaces in daily sitemaps |
| [`msm_sitemap_index`](#msm_sitemap_index) | Filter | Days listed in the index |
| [`msm_sitemap_index_appended_xml`](#msm_sitemap_index_appended_xml) | Filter | Extra XML in the index |
| [`msm_sitemap_index_xml`](#msm_sitemap_index_xml) | Filter | The whole index XML |
| [`msm_sitemap_index_by_year`](#msm_sitemap_index_by_year) | Filter | One index per year |
| [`msm_sitemap_is_enabled`](#msm_sitemap_is_enabled) | Filter | Whether sitemaps are enabled |
| [`msm_sitemap_cron_enabled`](#msm_sitemap_cron_enabled) | Filter | Whether automatic updates run |
| [`msm_sitemap_use_cron_builder`](#msm_sitemap_use_cron_builder) | Filter | Whether WP-Cron rebuilds sitemaps |
| [`msm_pre_get_last_modified_posts`](#msm_pre_get_last_modified_posts) | Filter | The query for recently modified posts |
| [`msm_sitemap_pre_get_post_year_range`](#msm_sitemap_pre_get_post_year_range) | Filter | The years that have content |
| [`msm_sitemap_include_xsl_reference`](#msm_sitemap_include_xsl_reference) | Filter | Linking sitemaps to their stylesheets |
| [`msm_sitemaps_stylesheet_css`](#msm_sitemaps_stylesheet_css) | Filter | Stylesheet CSS |
| [`msm_sitemaps_stylesheet_content`](#msm_sitemaps_stylesheet_content) | Filter | Daily sitemap stylesheet |
| [`msm_sitemaps_stylesheet_index_content`](#msm_sitemaps_stylesheet_index_content) | Filter | Index stylesheet |
| [`msm_sitemap_create_status`](#msm_sitemap_create_status) | Filter | Build status shown in the admin |
| [`msm_update_sitemap_for_year_month_date`](#msm_update_sitemap_for_year_month_date) | Action | A day needs rebuilding |
| [`msm_update_sitemap_post`](#msm_update_sitemap_post) | Action | A daily sitemap was built |
| [`msm_delete_sitemap_post`](#msm_delete_sitemap_post) | Action | A daily sitemap was deleted |
| [`msm_insert_sitemap_post`](#msm_insert_sitemap_post) | Action | No longer fires |

## Content

### msm_sitemap_entry_post_type

Filters the post types included in the sitemaps.

- `string[] $post_types` Post type names. Default `array( 'post' )`.

### msm_sitemap_post_status

Filters the post status included in the sitemaps. Only one status is supported.

- `string $status` Default `publish`. A status that isn't registered falls back to `publish`.

Since 1.5.0. Before 1.6.0, any status other than `publish` broke the sitemaps. After upgrading, run `wp msm-sitemap generate --all`, then `wp msm-sitemap recount`.

### msm_sitemap_skip_post

Filters whether to leave a post out of its sitemap. The global `$post` is set to the post, so template tags work.

- `bool $skip` Default `false`.
- `int $post_id` The post's ID.

### msm_sitemap_changefreq

Filters a URL's `changefreq`. A value the sitemap protocol doesn't allow, or a value that isn't a string, leaves the post out of the sitemap, and is logged.

- `string $changefreq` Default `monthly`. One of `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never`.
- `WP_Post $post` The post.

### msm_sitemap_priority

Filters a URL's `priority`. A numeric string, such as `'0.8'` from an option, is accepted. A value outside `0.0` to `1.0`, or a value that isn't a number, leaves the post out of the sitemap, and is logged.

- `float $priority` Default `0.7`.
- `WP_Post $post` The post.

### msm_sitemap_entry

Passes each `<url>` element after its `loc`, `lastmod`, `changefreq` and `priority` are added. Change the element itself, as the return value is ignored. The global `$post` is set to the post.

- `SimpleXMLElement $url` The `<url>` element.

### msm_sitemap_entry_posts_per_page

Filters the most URLs a daily sitemap can hold. Posts beyond the limit are left out.

- `int $limit` Default `500`.

### msm_sitemap_namespace

Filters the namespace attributes on each daily sitemap's `<urlset>` element.

- `array<string, string> $namespaces` Attribute names and values. By default: `xmlns:xsi`, `xmlns` (the sitemap protocol), `xmlns:n` (Google News), `xmlns:image` (Google Image), and `xsi:schemaLocation`.

## The index

### msm_sitemap_index

Filters the days listed in the index. Intended for removing days; adding a day with no sitemap only adds a broken link.

- `string[] $sitemaps` Dates in `Y-m-d H:i:s` format, newest first.
- `int|false $year` The year being indexed, or `false` when not indexing by year.

Since 1.4.0.

### msm_sitemap_index_appended_xml

Filters XML to add just before the index's closing `</sitemapindex>` tag, such as `<sitemap>` elements for other sitemaps.

- `string $xml` Default empty.
- `int|false $year` The year being indexed, or `false`.
- `string[] $sitemaps` The dates in the index.

### msm_sitemap_index_xml

Filters the finished index XML.

- `string $xml` The index XML.
- `int|false $year` The year being indexed, or `false`.
- `string[] $sitemaps` The dates in the index.

### msm_sitemap_index_by_year

Filters whether to publish one index per year (`/sitemap-2024.xml`) instead of a single `/sitemap.xml`. Read when the theme has loaded, so add it at the top level of a plugin or `functions.php`. Rebuild the rewrite rules after changing it.

- `bool $by_year` Default `false`.

## Enabling and scheduling

### msm_sitemap_is_enabled

Filters whether sitemaps are built and served. Use this to enable them on a site that isn't public, rather than changing `blog_public`. The robots.txt lines still depend on `blog_public`.

- `bool $is_enabled` Default: whether `blog_public` is `1`.

Since 1.6.0.

### msm_sitemap_cron_enabled

Filters whether automatic updates run. Returning `false` stops them, whatever the saved setting. Returning `true` doesn't schedule the 15-minute event, so use `wp msm-sitemap cron enable` to turn updates on.

- `bool $enabled` Default: the saved setting.

### msm_sitemap_use_cron_builder

Filters whether WP-Cron rebuilds sitemaps. Return `false` to rebuild them some other way, by handling [`msm_update_sitemap_for_year_month_date`](#msm_update_sitemap_for_year_month_date). Read when the theme has loaded, so add it at the top level of a plugin or `functions.php`. With the builder off, **Generate from all articles** in the admin does nothing.

- `bool $use_cron_builder` Default `true`.

### msm_pre_get_last_modified_posts

Filters the SQL query the 15-minute check uses to find modified posts. If you write a new query, prepare it with `$wpdb->prepare()`, and select the `ID`, `post_date` and `post_modified_gmt` columns, ordered by `post_modified_gmt` oldest first, with `LIMIT 1000`. When a run finds 1,000 posts, the next run carries on from the newest `post_modified_gmt` it saw. A query without that column or order skips any changes beyond the first 1,000.

- `string $query` The prepared query.
- `string $post_types_in` The included post types, quoted and comma-separated, for an `IN ()` clause.
- `string $date` Find posts modified after this time, in `Y-m-d H:i:s` format, UTC.

### msm_sitemap_pre_get_post_year_range

Short-circuits the query for the years that have content. Return an array of years to use instead.

- `int[]|false $years` Default `false`, which runs the query.

## Stylesheets

### msm_sitemap_include_xsl_reference

Filters whether sitemaps and the index link to their stylesheets. Return `false` to serve plain XML.

- `bool $include` Default `true`.

Since 1.5.0.

### msm_sitemaps_stylesheet_css

Filters the CSS used by both stylesheets.

- `string $css` The CSS.

### msm_sitemaps_stylesheet_content

Filters the full XSL served at `/msm-sitemap.xsl`, for daily sitemaps.

- `string $xsl` The XSL.

### msm_sitemaps_stylesheet_index_content

Filters the full XSL served at `/msm-sitemap-index.xsl`, for the index.

- `string $xsl` The XSL.

## Admin

### msm_sitemap_create_status

Filters the **Sitemap Creation Status** text on **Tools → Sitemap**.

- `string $status` `Running`, `Not Running`, or `Halting`, translated.

## Actions

### msm_update_sitemap_for_year_month_date

Fires when a day's sitemap needs rebuilding: from the 15-minute check, or when a post is published, unpublished, re-dated, trashed or deleted. The WP-Cron builder uses it to schedule the rebuild.

- `array $date` The year, month and day, in that order.
- `int $time` The Unix timestamp the rebuild should run at.

### msm_update_sitemap_post

Fires after a daily sitemap is built or rebuilt.

- `int $sitemap_id` The sitemap post's ID.
- `string $year`, `string $month`, `string $day` The sitemap's date, zero-padded.
- `string $xml` The sitemap XML.
- `int $url_count` The number of URLs in it.

### msm_delete_sitemap_post

Fires after a daily sitemap is deleted, whether because its day has no posts left or by `wp msm-sitemap delete`.

- `int $sitemap_id` The deleted sitemap post's ID.
- `string $year`, `string $month`, `string $day` The sitemap's date, zero-padded.

### msm_insert_sitemap_post

No longer fires. New sitemaps fire [`msm_update_sitemap_post`](#msm_update_sitemap_post), so use that for both new and rebuilt sitemaps.

## Scheduled events

These WP-Cron events are internal, but you may see them in cron tools.

| Event | Runs |
| --- | --- |
| `msm_cron_update_sitemap` | Every 15 minutes while automatic updates are on, on the `ms-sitemap-15-min-cron-interval` schedule. Finds recently modified posts. |
| `msm_cron_generate_sitemap_for_year_month_day` | Once for each day being rebuilt. |
| `msm_cron_generate_sitemap_for_year`, `msm_cron_generate_sitemap_for_year_month` | During a full build from the admin, to work through years and months. |
