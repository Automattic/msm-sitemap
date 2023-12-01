Comprehensive Sitemaps
===========

Comprehensive sitemaps for your WordPress VIP site. Site-wide sitemaps on WordPress.com includes 1,000 entries by default. This plugin allows you to include all the entries on your site into your sitemap.

Joint collaboration between Metro.co.uk, WordPress.com VIP, Alley Interactive, Maker Media, 10up, and others.

## How It Works

### Sitemap Data Storage

* One post type entry for each date.
* Sitemap XML is generated and stored in meta. This has several benefits:
 * Avoid memory and timeout problems when rendering heavy sitemap pages with lots of posts.
 * Older archives that are unlikely to change can be served up faster since we're not building them on-demand.
* Archive pages are rendered on-demand.

### Sitemap Generation

We want to generate the entire sitemap catalogue async to avoid running into timeout and memory issues.

Here's how the default WP-Cron approach works:

* Get year range for content.
* Store these years in options table.
* Kick off a cron event for the first year.
* Calculate the months to process for that year and store in an option.
* Kick off a cron event for the first month in the year we're processing.
* Calculate the days to process for that year and store in an option.
* Kick off a cron event for the first day in the month we're processing.
* Generate the sitemap for that day.
* Find the next day to process and repeat until we run out of days.
* Move on to the next month and repeat.
* Move on to next year when we run out of months.

The Comprehensive Sitemap plugin will only update the standard sitemap. The [news sitemap ](https://en.support.wordpress.com/sitemaps/#news-sitemaps) will only contain posts from the last two days, based on [Google’s guidelines](https://support.google.com/news/publisher/answer/74288?hl=en).

## CLI Commands

The plugin ships with a bunch of wp-cli commands to simplify sitemap creation:

```
$ wp msm-sitemap
usage: wp msm-sitemap generate-sitemap
   or: wp msm-sitemap generate-sitemap-for-year
   or: wp msm-sitemap generate-sitemap-for-year-month
   or: wp msm-sitemap generate-sitemap-for-year-month-day
   or: wp msm-sitemap recount-indexed-posts

See 'wp help msm-sitemap <command>' for more information on a specific command.
```

## Custom post types

Include custom post types in the generated sitemap with the `msm_sitemap_entry_post_type` filter.

## Generate Sitemap with posts of a custom status other than 'publish'

By default, the sitemap will only fetch posts with the status of 'publish'. To change this, use the `msm_sitemap_post_status` filter.

```
function example_filter_msm_sitemap_post_status( $post_status ) {
    return 'my_custom_status';
}
add_filter( 'msm_sitemap_post_status', 'example_filter_msm_sitemap_post_status', 10, 1 );
```

## Filtering Sitemap URLs

If you need to filter the URLs displayed in a sitemap created via the Comprehensive Sitemap plugin, there are two considerations. First, if you are filtering the individual sitemaps, which display the URLs to the articles published on a specific date, you can use the `msm_sitemap_entry` hook to filter the URLs. An example for a reverse-proxy situation is below:

```
function example_filter_msm_sitemap_entry( $url ) {
    $location = str_replace( 'example.wordpress.com', 'example.com/blog', $url->loc );
    $url->loc = $location;
    return $url;
}
add_filter( 'msm_sitemap_entry', 'example_filter_msm_sitemap_entry', 10, 1 );
```

Second, if you are filtering the root sitemap, which displays the URLs to the individual sitemaps by date, you will need to filter the `home_url` directly. There is no plugin-specific hook to filter the URLs on the root sitemap.


## Filter Sitemap Index

Use the `msm_sitemap_index` filter to exclude daily sitemaps from the index based on date.

```
add_filter( 'msm_sitemap_index', function( $sitemaps ) {
    $reference_date = strtotime( '2017-09-09' );

    return array_filter( $sitemaps, function ( $date ) use ( $reference_date ) {
        return ( $reference_date < strtotime( $date ) );
    } );
} );
```
