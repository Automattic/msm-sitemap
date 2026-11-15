# Customizing MSM Sitemap

Recipes for the changes sites most often need. Each one uses a filter or action from the [hooks reference](hooks.md), which lists every hook with its parameters.

## Before you start

**Where to put the code.** Use a plugin, an mu-plugin, or your theme's `functions.php`. Add the filters at the top level of the file, not inside a callback for `init` or a later hook: MSM Sitemap reads `msm_sitemap_index_by_year` and `msm_sitemap_use_cron_builder` when the theme has loaded, and adding them later has no effect.

**Rebuild afterwards.** Sitemaps are built once and stored, so anything that changes what goes into a sitemap only affects sitemaps built after the change. Once your code is in place, rebuild them:

```shell
wp msm-sitemap generate --all
```

## Include other post types

Only `post` is included by default. To add pages and a custom post type:

```php
add_filter( 'msm_sitemap_entry_post_type', function ( array $post_types ): array {
	$post_types[] = 'page';
	$post_types[] = 'product';
	return $post_types;
} );
```

Posts are grouped by their publish date, so pages land in the sitemap for the day they were first published.

## Include posts with a different status

Only published posts are included by default. To use another status, such as one added by an editorial workflow plugin:

```php
add_filter( 'msm_sitemap_post_status', function (): string {
	return 'live';
} );
```

The status must be registered with `register_post_status()`, or the plugin falls back to `publish`. Only one status can be used. From 1.4.3 to 1.5.6, using any status other than `publish` broke the daily sitemaps, and from 1.5.5 left the index empty too. If you used this filter before 1.6.0, rebuild your sitemaps after upgrading to fix them.

## Leave posts out

Return `true` to leave a post out of its sitemap:

```php
add_filter( 'msm_sitemap_skip_post', function ( bool $skip, int $post_id ): bool {
	if ( get_post_meta( $post_id, 'exclude_from_sitemap', true ) ) {
		return true;
	}
	return $skip;
}, 10, 2 );
```

The global `$post` is set to the post being considered, so template tags such as `get_the_ID()` work in the callback too. A day whose posts are all left out has no sitemap.

## Change changefreq and priority

Every URL gets a `changefreq` of `monthly` and a `priority` of `0.7`. Both filters receive the post:

```php
add_filter( 'msm_sitemap_changefreq', function ( string $changefreq, WP_Post $post ): string {
	return 'page' === $post->post_type ? 'yearly' : $changefreq;
}, 10, 2 );

add_filter( 'msm_sitemap_priority', function ( float $priority, WP_Post $post ): float {
	return is_sticky( $post->ID ) ? 1.0 : $priority;
}, 10, 2 );
```

`changefreq` must be one of `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly` or `never`, and `priority` must be between `0.0` and `1.0`. If either is invalid, that post is left out of the sitemap and a message is written to the PHP error log.

## Change or add to each URL entry

The `msm_sitemap_entry` filter passes each `<url>` element as a `SimpleXMLElement`. Change the element itself: the value you return is ignored. For example, to serve URLs from a different host:

```php
add_filter( 'msm_sitemap_entry', function ( SimpleXMLElement $url ): SimpleXMLElement {
	$url->loc = str_replace( 'https://origin.example.com', 'https://www.example.com', (string) $url->loc );
	return $url;
} );
```

You can add child elements too, such as `image:image` tags, since the image namespace is already declared. Add any other namespace you need with [`msm_sitemap_namespace`](hooks.md#msm_sitemap_namespace).

## Raise the number of URLs per day

A daily sitemap holds at most 500 URLs, and any posts beyond that are left out. If you publish more than 500 posts on some days, raise the limit:

```php
add_filter( 'msm_sitemap_entry_posts_per_page', function (): int {
	return 2000;
} );
```

The sitemap protocol allows up to 50,000 URLs, and 50 MB, in one sitemap. Every post in a day is loaded when the day is built, so test a higher limit against your busiest day.

## Index sitemaps by year

Split the index into one index per year:

```php
add_filter( 'msm_sitemap_index_by_year', '__return_true' );
```

The index moves from `/sitemap.xml` to `/sitemap-2024.xml` and so on, and robots.txt lists one line per year. Rebuild the rewrite rules after adding or removing this filter, by saving **Settings → Permalinks** or running `wp rewrite flush`. See [Serving sitemaps](serving-sitemaps.md#sitemap-urls) for the full set of URLs.

## Leave days out of the index

The `msm_sitemap_index` filter receives the dates in the index, as `Y-m-d H:i:s` strings. To list only days from 2020 onwards:

```php
add_filter( 'msm_sitemap_index', function ( array $sitemaps ): array {
	return array_filter( $sitemaps, function ( string $date ): bool {
		return $date >= '2020-01-01';
	} );
} );
```

This only hides days from the index. Their sitemaps are still built, and still served at their own URLs.

## Add other sitemaps to the index

To list sitemaps from elsewhere, such as a news sitemap, append `<sitemap>` elements to the index:

```php
add_filter( 'msm_sitemap_index_appended_xml', function ( string $xml ): string {
	return $xml . '<sitemap><loc>https://example.com/news-sitemap.xml</loc></sitemap>';
} );
```

The filter also receives the year being indexed (or `false`) and the dates in the index. To change the finished index XML as a whole, use [`msm_sitemap_index_xml`](hooks.md#msm_sitemap_index_xml).

## Use sitemaps on a site that isn't public

Sitemaps are only built and served when the site is public. To use them on a staging site, without changing the site's search engine visibility:

```php
add_filter( 'msm_sitemap_is_enabled', function ( bool $is_enabled ): bool {
	if ( 'staging' === wp_get_environment_type() ) {
		return true;
	}
	return $is_enabled;
} );
```

Return `false` to turn sitemaps off on a site that is public. See [Serving sitemaps](serving-sitemaps.md#sites-that-arent-public) for what changes.

## Turn automatic updates off in code

Automatic updates are turned on and off from **Tools → Sitemap** or with WP-CLI, and stored in the database. To keep them off in an environment regardless of that setting, for example on a copy of a production database:

```php
add_filter( 'msm_sitemap_cron_enabled', function ( bool $enabled ): bool {
	return 'production' === wp_get_environment_type() ? $enabled : false;
} );
```

Returning `true` from this filter doesn't turn updates on by itself, because it doesn't schedule the 15-minute event. To turn them on as part of a deployment, run `wp msm-sitemap cron enable` instead; it does nothing if they're already on.

## Purge caches when a sitemap changes

`msm_update_sitemap_post` fires whenever a daily sitemap is built or rebuilt, and `msm_delete_sitemap_post` whenever one is deleted. Both receive the sitemap's year, month and day:

```php
function my_purge_sitemap_cache( int $sitemap_id, string $year, string $month, string $day ): void {
	my_cdn_purge( home_url( '/sitemap.xml' ) );
	my_cdn_purge( add_query_arg( array( 'yyyy' => $year, 'mm' => $month, 'dd' => $day ), home_url( '/sitemap.xml' ) ) );
}
add_action( 'msm_update_sitemap_post', 'my_purge_sitemap_cache', 10, 4 );
add_action( 'msm_delete_sitemap_post', 'my_purge_sitemap_cache', 10, 4 );
```

`my_cdn_purge()` stands for whatever your cache or CDN provides.

## Rebuild sitemaps with your own job queue

By default, each day that needs rebuilding is scheduled as a WP-Cron event. To send that work to another queue instead, turn off the WP-Cron builder and handle the `msm_update_sitemap_for_year_month_date` action:

```php
add_filter( 'msm_sitemap_use_cron_builder', '__return_false' );

add_action( 'msm_update_sitemap_for_year_month_date', function ( array $date, int $time ): void {
	list( $year, $month, $day ) = $date;
	my_queue_job( 'rebuild_sitemap', Metro_Sitemap::get_date_stamp( $year, $month, $day ), $time );
}, 10, 2 );

// In the job, with the Y-m-d date stamp:
Metro_Sitemap::generate_sitemap_for_date( $date_stamp );
```

`generate_sitemap_for_date()` builds the day's sitemap, or deletes it if the day has no posts left. `my_queue_job()` stands for your queue's API.

With the WP-Cron builder turned off, **Generate from all articles** on the admin screen does nothing, so build existing content with `wp msm-sitemap generate --all`. The 15-minute check still needs automatic updates turned on.

## Find the oldest year without a query

To work out which years to build, the plugin finds the year of the oldest included post, and caches it for a week. On a very large posts table you can skip that query by returning the years yourself:

```php
add_filter( 'msm_sitemap_pre_get_post_year_range', function () {
	return range( 2005, (int) wp_date( 'Y' ) );
} );
```

Return an array of years, or `false` to let the plugin work it out.

## Change how sitemaps look in a browser

Sitemaps link to stylesheets that show them as a table in a browser. Search engines ignore them. To serve plain XML with no stylesheet:

```php
add_filter( 'msm_sitemap_include_xsl_reference', '__return_false' );
```

To restyle them, add CSS with `msm_sitemaps_stylesheet_css`:

```php
add_filter( 'msm_sitemaps_stylesheet_css', function ( string $css ): string {
	return $css . ' body { font-family: Georgia, serif; }';
} );
```

To change the stylesheets' markup, such as their headings and text, filter their full XSL with `msm_sitemaps_stylesheet_content` (daily sitemaps) and `msm_sitemaps_stylesheet_index_content` (the index).
