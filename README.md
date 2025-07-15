# Metro Sitemap

Stable tag: 1.4.2  
Requires at least: 5.9  
Tested up to: 6.8  
Requires PHP: 7.4  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  
Tags: sitemap, xml, seo, performance, multisite  
Contributors: metro, automattic, alleyinteractive, makermedia, 10up  

High-performance XML sitemaps for large-scale WordPress sites. Built for speed, extensibility, and reliability. Metro Sitemap generates robust, scalable XML sitemaps for WordPress sites of any size. Designed for high-traffic and enterprise environments, it ensures your content is discoverable by search engines without slowing down your site.

## At a Glance

* **Fast, reliable XML sitemaps** for large and small sites
* **Asynchronous generation** avoids timeouts and memory issues
* **Supports custom post types** (see FAQ)
* **Multisite compatible**
* **WP-CLI support** for advanced management
* **Extensible** via hooks and filters ([see developer docs](./DEVELOPERS.md))
* **Admin UI** for stats and manual actions

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress admin.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. (Optional) Visit **Tools > Sitemap** in the admin for stats and manual actions.
4. Sitemaps will be generated automatically in the background.

## Usage

* Your sitemap index will be available at `/sitemap.xml` (e.g., `https://example.com/sitemap.xml`).
* Sitemaps are generated in the background and updated as you publish new content.
* The admin UI (Tools > Sitemap) provides stats and lets you manually trigger generation if needed.

## Frequently Asked Questions

### Why isn’t my custom post type included in the sitemap?

By default, only the `post` post type is included. To add custom post types (like `page`, `news`, or others), see the [Developer Guide](./DEVELOPERS.md).

### How do I include posts with a custom status?

By default, only published posts are included. You can change this via a filter. See the [Developer Guide](./DEVELOPERS.md).

### How do I regenerate the sitemap?

Sitemaps are generated automatically, but you can:

* Use the admin UI (**Tools > Sitemap**) to trigger a manual rebuild
* Use WP-CLI commands (see below)

### Does it work on multisite?

Yes! Each site in your network will have its own sitemaps. The plugin can be network-activated.

### How do I exclude specific posts or dates?

You can filter which posts or dates appear in the sitemap using hooks—see the [Developer Guide](./DEVELOPERS.md).

### Where is the sitemap stored?

Sitemap XML is stored in a custom post type (`msm_sitemap`) and served on-demand for fast performance.

### Can I customize the number of posts per sitemap?

Yes, this is filterable. See the [Developer Guide](./DEVELOPERS.md).

## WP-CLI Commands

Metro Sitemap provides several WP-CLI commands for advanced users:

~~~shell
$ wp msm-sitemap generate-sitemap
$ wp msm-sitemap generate-sitemap-for-year --year=2024
$ wp msm-sitemap generate-sitemap-for-year-month --year=2024 --month=7
$ wp msm-sitemap generate-sitemap-for-year-month-day --year=2024 --month=7 --day=13
$ wp msm-sitemap recount-indexed-posts
~~~

See `wp help msm-sitemap <command>` for details and options.

## Support

* [GitHub Issues](https://github.com/Automattic/msm-sitemap/issues) (for bug reports and feature requests)
* [WordPress VIP Support](https://wpvip.com/wordpress-vip-enterprise-support/) (for WPVIP customers)

## Credits

Metro Sitemap is a joint collaboration between Metro.co.uk, WordPress VIP, Alley Interactive, Maker Media, 10up, and others. Special thanks to all contributors.

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for a full history of changes.

## License

GPLv2 or later. See [LICENSE](./LICENSE) for details.

## Developer Documentation

For advanced customization, see [DEVELOPERS.md](./DEVELOPERS.md).
