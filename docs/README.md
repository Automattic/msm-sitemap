# MSM Sitemap documentation

What MSM Sitemap does, and how to install it, is covered in the [README](../README.md). These pages go further.

## Using MSM Sitemap

- [Generating sitemaps](generating-sitemaps.md): turning on automatic updates, building sitemaps for existing content, what keeps them up to date, and running large sites and multisite networks.
- [Serving sitemaps](serving-sitemaps.md): the sitemap URLs, what each sitemap contains, robots.txt, WordPress core sitemaps, sites that aren't public, caching, and limits.
- [WP-CLI commands](wp-cli.md): every `wp msm-sitemap` command.

## Extending MSM Sitemap

- [Customizing MSM Sitemap](customizing.md): recipes for the most common changes, such as adding post types, leaving posts out, and indexing by year.
- [Filters and actions](hooks.md): every hook the plugin provides.

## Contributing

- [How MSM Sitemap works](architecture.md): where sitemaps are stored, the options and scheduled events the plugin uses, and how the code is organized.
- [Contributing guide](../CONTRIBUTING.md): local environment, tests, coding standards, and pull requests.

## Concepts

- **Sitemap index:** the list of sitemaps search engines start from, at `/sitemap.xml`. It has one entry for each day that has content.
- **Daily sitemap:** the list of URLs for the posts published on one day, in the site's timezone.
- **Sitemap post:** each daily sitemap is stored as a post of the private `msm_sitemap` post type, with the finished XML in post meta.
- **Automatic updates:** the scheduled events that keep sitemaps up to date as content changes. They are off until you turn them on.
