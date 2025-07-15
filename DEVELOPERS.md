# Metro Sitemap – Developer & Contributor Documentation

> **Note:** This document is for developers. For installation, usage, and FAQ, see [README.md](./README.md).

---

## Overview: Architecture & Storage

Metro Sitemap is designed for performance and extensibility on large WordPress sites. Key architectural decisions:

* **Sitemap data is stored in a custom post type** (`msm_sitemap`), one post per date.
* **Sitemap XML is generated and stored in post meta** for each date, so heavy sitemaps are served quickly and not built on every request.
* **Sitemap XML is served on-demand from stored meta** when a request for a sitemap is made, ensuring fast responses and low server load.
* **Sitemap generation is asynchronous** (via WP-Cron or WP-CLI), avoiding timeouts and memory issues.

---

## Customizing via Hooks

Metro Sitemap is highly extensible. Below are the main action and filter hooks you can use to customize its behavior, with business-case context and type-safe, documented code samples.

### Add Custom Post Types to the Sitemap

**Business case:**
If your site uses custom post types (e.g., `news`, `product`, or `page`) and you want them included in your XML sitemap for SEO, use this filter.

~~~php
add_filter( 'msm_sitemap_entry_post_type', 'my_add_custom_post_types_to_sitemap' );
/**
 * Filter the post types included in the sitemap.
 *
 * @param string[] $post_types Array of post type slugs.
 * @return string[] Modified array of post type slugs.
 */
function my_add_custom_post_types_to_sitemap( array $post_types ): array {
    $post_types[] = 'page';
    $post_types[] = 'my_custom_post_type';
    return $post_types;
}
~~~

### Change Included Post Status

**Business case:**
If you want to include posts with a custom status (e.g., `private`, `future`, or a workflow status) in your sitemap, use this filter. Note: Only a single status string is supported (not an array).

Don't use type declarations here, as this may be expanded to support an array of post statuses in the future.

~~~php
add_filter( 'msm_sitemap_post_status', 'my_set_custom_post_status_for_sitemap' );
/**
 * Filter the post status included in the sitemap.
 *
 * @param string $status The post status to include (default: 'publish').
 * @return string The post status to use.
 */
function my_set_custom_post_status_for_sitemap( $status ) {
    return 'my_custom_status';
}
~~~

### Change Individual Sitemap URLs

**Business case:**
If your site is behind a reverse proxy, or you need to rewrite URLs (e.g., to add a path prefix or change the domain), use this filter to modify each URL in the sitemap before output.

~~~php
add_filter( 'msm_sitemap_entry', 'my_filter_sitemap_url' );
/**
 * Filter each URL entry in the sitemap.
 *
 * @param SimpleXMLElement $url The URL XML element (modifiable by reference).
 * @return SimpleXMLElement The modified URL XML element.
 */
function my_filter_sitemap_url( SimpleXMLElement $url ): SimpleXMLElement {
    $url->loc = str_replace( 'example.com', 'mydomain.com', (string) $url->loc );
    return $url;
}
~~~

### Filter the Sitemap Index

**Business case:**
If you want to exclude certain daily sitemaps from the index (e.g., to hide old dates or only show recent content), use this filter.

~~~php
add_filter( 'msm_sitemap_index', 'my_filter_sitemap_index', 10, 2 );
/**
 * Filter the list of daily sitemaps in the index.
 *
 * @param string[] $sitemaps Array of sitemap dates (Y-m-d H:i:s).
 * @param int|string|false $year The year being indexed, or false for all years.
 * @return string[] Filtered array of sitemap dates.
 */
function my_filter_sitemap_index( array $sitemaps, $year ) : array {
    $reference_date = strtotime( '2020-01-01' );
    return array_filter( $sitemaps, function ( $date ) use ( $reference_date ) {
        return ( $reference_date < strtotime( $date ) );
    } );
}
~~~

### Customize the Last Modified Posts Query

**Business case:**
On large sites, you may want to optimize the query for last modified posts (e.g., to only scan recent posts, or use a custom index).

~~~php
add_filter( 'msm_pre_get_last_modified_posts', 'my_customize_last_modified_posts_query', 10, 3 );
/**
 * Filter the SQL query used to get last modified posts for the sitemap.
 *
 * @param string $query The SQL query string.
 * @param string $post_types_in Comma-separated list of post types.
 * @param string $date The cutoff date for modified posts.
 * @return string The modified SQL query string.
 */
function my_customize_last_modified_posts_query( string $query, string $post_types_in, string $date ): string {
    global $wpdb;
    $query = $wpdb->prepare(
        "SELECT ID, post_date FROM $wpdb->posts WHERE post_type IN ( {$post_types_in} ) AND post_status = 'publish' AND post_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH) AND post_modified_gmt >= %s LIMIT 1000",
        $date
    );
    return $query;
}
~~~

### Index Sitemaps by Year

**Business case:**
For very large sites, you may want to break up the sitemap index by year to reduce the number of entries per index file and improve performance.

~~~php
add_filter( 'msm_sitemap_index_by_year', '__return_true' );
~~~

### Add or Modify XML Namespaces

**Business case:**
If you need to add or modify XML namespaces in the sitemap (e.g., for Google News or image sitemaps), use this filter.

~~~php
add_filter( 'msm_sitemap_namespace', 'my_add_custom_sitemap_namespace' );
/**
 * Filter the XML namespaces used in the sitemap.
 *
 * @param array $namespaces Associative array of namespace prefixes and URIs.
 * @return array Modified namespaces array.
 */
function my_add_custom_sitemap_namespace( array $namespaces ): array {
    $namespaces['xmlns:custom'] = 'http://example.com/schemas/custom/1.0';
    return $namespaces;
}
~~~

### Change Number of Posts Per Sitemap Page

**Business case:**
If you need to tune the number of posts included per sitemap file (e.g., for performance or to meet search engine limits), use this filter.

~~~php
add_filter( 'msm_sitemap_entry_posts_per_page', 'my_set_sitemap_posts_per_page' );
/**
 * Filter the number of posts per sitemap page.
 *
 * @param int $per_page Number of posts per sitemap (default: 500).
 * @return int Modified number of posts per sitemap.
 */
function my_set_sitemap_posts_per_page( int $per_page ): int {
    return 1000; // Increase to 1000 per sitemap
}
~~~

### Skip Specific Posts from the Sitemap

**Business case:**
If you want to exclude specific posts from the sitemap (e.g., by ID, meta, or taxonomy), use this filter.

~~~php
add_filter( 'msm_sitemap_skip_post', 'my_skip_specific_post', 10, 2 );
/**
 * Filter whether to skip a post from the sitemap.
 *
 * @param bool $skip Whether to skip the post (default: false).
 * @param int $post_id The post ID being considered.
 * @return bool True to skip, false to include.
 */
function my_skip_specific_post( bool $skip, int $post_id ): bool {
    // Example: skip post with ID 42
    if ( $post_id === 42 ) {
        return true;
    }
    return $skip;
}
~~~

### Append Custom XML to the Sitemap Index

**Business case:**
If you need to append custom XML to the sitemap index (e.g., to add a news or image sitemap), use this filter.

~~~php
add_filter( 'msm_sitemap_index_appended_xml', 'my_append_custom_sitemap_to_index', 10, 3 );
/**
 * Filter the XML to append to the sitemap index.
 *
 * @param string $appended_xml The XML to append (default: '').
 * @param int|bool $year The year for which the sitemap index is being generated, or false for all years.
 * @param array $sitemaps The sitemaps to be included in the index.
 * @return string The XML to append.
 */
function my_append_custom_sitemap_to_index( string $appended_xml, $year, array $sitemaps ): string {
    $custom_xml = '<sitemap><loc>https://example.com/custom-sitemap.xml</loc></sitemap>';
    return $appended_xml . $custom_xml;
}
~~~

### Modify the Final Sitemap Index XML

**Business case:**
If you need to modify the final sitemap index XML before output (e.g., to inject or transform data), use this filter.

~~~php
add_filter( 'msm_sitemap_index_xml', 'my_modify_final_sitemap_index_xml', 10, 3 );
/**
 * Filter the final sitemap index XML before output.
 *
 * @param string $xml_string The sitemap index XML.
 * @param int|bool $year The year for which the sitemap index is being generated, or false for all years.
 * @param array $sitemaps The sitemaps to be included in the index.
 * @return string The modified XML string.
 */
function my_modify_final_sitemap_index_xml( string $xml_string, $year, array $sitemaps ): string {
    // Example: add a comment to the XML
    return "<!-- Custom comment -->\n" . $xml_string;
}
~~~

### Respond to Sitemap Post Updates, Inserts, and Deletions

**Business case:**
If you need to trigger cache invalidation, logging, or other side effects when a sitemap is updated, inserted, or deleted, use these actions.

~~~php
add_action( 'msm_update_sitemap_post', 'my_on_update_sitemap_post', 10, 6 );
/**
 * Action fired when a sitemap post is updated.
 *
 * @param int $sitemap_id The sitemap post ID.
 * @param string $year The year of the sitemap.
 * @param string $month The month of the sitemap.
 * @param string $day The day of the sitemap.
 * @param string $xml The generated XML.
 * @param int $url_count The number of URLs in the sitemap.
 */
function my_on_update_sitemap_post( int $sitemap_id, string $year, string $month, string $day, string $xml, int $url_count ): void {
    // Example: log the update
    error_log( "Sitemap updated: ID $sitemap_id for $year-$month-$day with $url_count URLs" );
}

add_action( 'msm_insert_sitemap_post', 'my_on_insert_sitemap_post', 10, 6 );
/**
 * Action fired when a sitemap post is inserted.
 *
 * @param int $sitemap_id The sitemap post ID.
 * @param string $year The year of the sitemap.
 * @param string $month The month of the sitemap.
 * @param string $day The day of the sitemap.
 * @param string $xml The generated XML.
 * @param int $url_count The number of URLs in the sitemap.
 */
function my_on_insert_sitemap_post( int $sitemap_id, string $year, string $month, string $day, string $xml, int $url_count ): void {
    // Example: trigger a cache purge
    // purge_cache_for_sitemap( $sitemap_id );
}

add_action( 'msm_delete_sitemap_post', 'my_on_delete_sitemap_post', 10, 4 );
/**
 * Action fired when a sitemap post is deleted.
 *
 * @param int $sitemap_id The sitemap post ID.
 * @param string $year The year of the sitemap.
 * @param string $month The month of the sitemap.
 * @param string $day The day of the sitemap.
 */
function my_on_delete_sitemap_post( int $sitemap_id, string $year, string $month, string $day ): void {
    // Example: log the deletion
    error_log( "Sitemap deleted: ID $sitemap_id for $year-$month-$day" );
}
~~~

---

## WP-CLI Command Reference

Metro Sitemap provides several WP-CLI commands for advanced management:

~~~shell
wp msm-sitemap generate-sitemap
wp msm-sitemap generate-sitemap-for-year --year=2024
wp msm-sitemap generate-sitemap-for-year-month --year=2024 --month=7
wp msm-sitemap generate-sitemap-for-year-month-day --year=2024 --month=7 --day=13
wp msm-sitemap recount-indexed-posts
~~~

See `wp help msm-sitemap <command>` for details and options.

---

## Testing & Contributing

* **Minimum Requirements:** WordPress 5.9+, PHP 7.4+
* **Coding Standards:** Follows [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) and [PSR-12](https://www.php-fig.org/psr/psr-12/)
* **Tests:** PHPUnit integration tests are included. To run:

~~~shell
composer install
composer test
~~~

* **Code Quality:** Lint and code style checks:

~~~shell
composer lint
composer cs
~~~

* **Contributions:** Please open issues or pull requests on [GitHub](https://github.com/Automattic/msm-sitemap).

---

## Internal Architecture Details

* **Custom Post Type:** Sitemaps are stored as `msm_sitemap` posts, one per date.
* **Meta Storage:** The generated XML is stored in post meta (`msm_sitemap_xml`).
* **Async Generation:** Uses WP-Cron or CLI to generate sitemaps in batches, avoiding timeouts.
* **Admin UI:** Tools > Sitemap provides stats and manual actions for admins.
* **Multisite:** Each site has its own sitemaps; can be network-activated.

---

## Further Reading

* [Changelog](./CHANGELOG.md)
* [Main README (User/Admin Guide)](./README.md)
