# MSM Sitemap

Stable tag: 1.5.6  
Requires at least: 6.4  
Tested up to: 7.1  
Requires PHP: 7.4  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  
Tags: sitemap, xml, seo, performance, multisite  
Contributors: metro, automattic, alleyinteractive, makermedia, 10up, garyj  

XML sitemaps for large WordPress sites, built in the background one day at a time and served from storage.

## Description

MSM Sitemap is built for news sites and other publishers with a lot of content. Instead of generating a sitemap on every request, it builds one sitemap for each day that has content, stores it, and serves the stored copy. The sitemap index at `/sitemap.xml` lists every day.

- **Fast to serve.** Daily sitemaps are stored as finished XML, so a request does no sitemap work.
- **Built in the background.** Sitemaps are built a day at a time through WP-Cron or WP-CLI, so even sites with millions of posts don't time out.
- **Kept up to date.** Once automatic updates are on, a day's sitemap is rebuilt when a post on that day is published, edited, unpublished, re-dated, trashed or deleted.
- **In your control.** Nothing runs until you start it, and you can build sitemaps for a year, month or day at a time from the shell.
- **Readable in a browser**, with stylesheets you can change or turn off.
- **Multisite compatible.** Each site has its own sitemaps.

The **Tools → Sitemap** screen shows how many sitemaps and URLs there are, charts recent days, and lets you turn automatic updates on and off and start a full build. The `wp msm-sitemap` commands do all that and more from the shell.

MSM Sitemap replaces WordPress core's sitemaps at `/wp-sitemap.xml` while it's active.

### Documentation

Guides for site owners and developers live in the [`docs/` directory on GitHub](https://github.com/Automattic/msm-sitemap/blob/main/docs/README.md):

- [Generating sitemaps](https://github.com/Automattic/msm-sitemap/blob/main/docs/generating-sitemaps.md): the first build, automatic updates, large sites and multisite
- [Serving sitemaps](https://github.com/Automattic/msm-sitemap/blob/main/docs/serving-sitemaps.md): URLs, robots.txt, sites that aren't public, caching and limits
- [WP-CLI commands](https://github.com/Automattic/msm-sitemap/blob/main/docs/wp-cli.md)
- [Customizing MSM Sitemap](https://github.com/Automattic/msm-sitemap/blob/main/docs/customizing.md) and the full list of [filters and actions](https://github.com/Automattic/msm-sitemap/blob/main/docs/hooks.md)

## Installation

1. Install and activate MSM Sitemap. On a multisite network, you can network-activate it.
2. Build sitemaps for your existing content. On a large site, use WP-CLI: `wp msm-sitemap generate --all`. Otherwise, go to **Tools → Sitemap**, choose **Enable Automatic Updates**, then **Generate from all articles**.
3. Turn on automatic updates, if you haven't already, so sitemaps follow your content: **Tools → Sitemap → Enable Automatic Updates**, or `wp msm-sitemap cron enable`.
4. Submit `https://example.com/sitemap.xml` to search engines. It's also added to your site's robots.txt.

Nothing is built or scheduled until you do steps 2 and 3. Building every sitemap at once can put a heavy load on the database of a large site, so the plugin leaves the timing to you. See [generating sitemaps](https://github.com/Automattic/msm-sitemap/blob/main/docs/generating-sitemaps.md) for the details.

Sitemaps are only available on public sites, unless you [enable them on a site that isn't public](https://github.com/Automattic/msm-sitemap/blob/main/docs/customizing.md#use-sitemaps-on-a-site-that-isnt-public).

## Frequently Asked Questions

### Why is my sitemap empty, or why does /sitemap.xml return a 404?

An empty index means no sitemaps have been built yet: see the installation steps. A 404 usually means the rewrite rules haven't been rebuilt, which happens when the plugin is loaded from code rather than activated, or network-activated. Save **Settings → Permalinks** without changes, or run `wp rewrite flush`. If that doesn't help, check that the site is public. [More on sitemap URLs](https://github.com/Automattic/msm-sitemap/blob/main/docs/serving-sitemaps.md#if-sitemapxml-returns-a-404).

### Which content is included?

Published posts of the `post` post type, grouped by the day they were published. You can [add pages and custom post types](https://github.com/Automattic/msm-sitemap/blob/main/docs/customizing.md#include-other-post-types), [use a different post status](https://github.com/Automattic/msm-sitemap/blob/main/docs/customizing.md#include-posts-with-a-different-status), and [leave particular posts out](https://github.com/Automattic/msm-sitemap/blob/main/docs/customizing.md#leave-posts-out).

### Can I use it on a staging site, or a site that isn't public?

Yes, with [a filter](https://github.com/Automattic/msm-sitemap/blob/main/docs/customizing.md#use-sitemaps-on-a-site-that-isnt-public). By default, sitemaps are only built and served on sites that allow search engines to index them.

### I publish more than 500 posts a day. Are they all included?

Not by default. Each daily sitemap holds up to 500 URLs, and posts beyond that are left out. You can [raise the limit](https://github.com/Automattic/msm-sitemap/blob/main/docs/customizing.md#raise-the-number-of-urls-per-day).

### Does it replace WordPress core sitemaps?

Yes. WordPress's own sitemaps at `/wp-sitemap.xml` are turned off while MSM Sitemap is active. If another plugin also generates sitemaps, submit only one set to search engines.

### Does it work on multisite?

Yes. Each site has its own sitemaps, and its own automatic updates setting, so turn updates on for each site, even when the plugin is network-activated.

### How do I rebuild the sitemaps?

Run `wp msm-sitemap generate --all`, or choose **Generate from all articles** on **Tools → Sitemap** (automatic updates must be on). Rebuild after changing any filter that affects what goes into a sitemap.

### How do I turn off the styling in the browser?

Add `add_filter( 'msm_sitemap_include_xsl_reference', '__return_false' );` to a plugin or your theme's `functions.php`. Sitemaps are then served as plain XML. You can also [restyle them](https://github.com/Automattic/msm-sitemap/blob/main/docs/customizing.md#change-how-sitemaps-look-in-a-browser).

### Where are sitemaps stored?

Each daily sitemap is a post of the private `msm_sitemap` post type, with its XML in post meta. See [how MSM Sitemap works](https://github.com/Automattic/msm-sitemap/blob/main/docs/architecture.md).

## Contributing

Bug reports and pull requests are welcome on [GitHub](https://github.com/Automattic/msm-sitemap). See the [contributing guide](https://github.com/Automattic/msm-sitemap/blob/main/CONTRIBUTING.md) to get started.

## Support

- [GitHub issues](https://github.com/Automattic/msm-sitemap/issues), for bug reports and feature requests.
- [WordPress VIP Support](https://wpvip.com/wordpress-vip-enterprise-support/), for WordPress VIP customers.

## Credits

MSM Sitemap is a joint collaboration between Metro.co.uk, WordPress VIP, Alley Interactive, Maker Media, 10up, and others. Special thanks to all contributors.

## Changelog

Every change is listed in [CHANGELOG.md](https://github.com/Automattic/msm-sitemap/blob/main/CHANGELOG.md).

## License

GPLv2 or later. See [LICENSE](https://github.com/Automattic/msm-sitemap/blob/main/LICENSE) for details.
