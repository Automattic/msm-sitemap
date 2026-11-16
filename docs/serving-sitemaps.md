# Serving sitemaps

This page covers the URLs MSM Sitemap answers, what's in each sitemap, and how it fits with robots.txt, WordPress core sitemaps, and caching.

## Sitemap URLs

| URL | What it is |
| --- | --- |
| `/sitemap.xml` | The sitemap index: one entry for each day that has a sitemap. Submit this one to search engines. |
| `/sitemap.xml?yyyy=2024&mm=07&dd=10` | The daily sitemap for 10 July 2024. |
| `/msm-sitemap.xsl` and `/msm-sitemap-index.xsl` | Stylesheets that make the sitemaps readable in a browser. Search engines ignore them. |

If you [index by year](customizing.md#index-sitemaps-by-year), the URLs change:

| URL | What it is |
| --- | --- |
| `/sitemap-2024.xml` | The index for 2024. There's one for each year with content. |
| `/sitemap-2024.xml?mm=07&dd=10` | The daily sitemap for 10 July 2024. |

When indexing by year, `/sitemap.xml` is no longer served, so submit each year's index instead. The robots.txt lines list them all.

A daily sitemap that doesn't exist, and every sitemap URL on a site where sitemaps aren't enabled, returns a 404.

### If /sitemap.xml returns a 404

The sitemap URLs are rewrite rules, which WordPress stores and only rebuilds when asked. The plugin clears the stored rules when it's activated and deactivated, so they're rebuilt with the sitemap URLs on the next request. That doesn't happen when:

- the plugin is loaded from code, such as an mu-plugin or `wpcom_vip_load_plugin()`, rather than activated, or
- it's network-activated on a multisite network, where only the main site's rules are cleared, or
- you've just switched indexing by year on or off.

In those cases, rebuild the rules by saving **Settings → Permalinks** without changes, or with `wp rewrite flush` (add `--url=` for each site on a network).

If the rules are fine and you still get a 404, check that sitemaps are enabled for the site (see [Sites that aren't public](#sites-that-arent-public)).

## What's in a sitemap

The index lists the newest 10,000 daily sitemaps, newest first. Each entry's `lastmod` is when that day's sitemap was last rebuilt. 10,000 days is more than 27 years of daily content; if your site has more, [index by year](customizing.md#index-sitemaps-by-year) so each index stays under the limit.

Each daily sitemap lists up to 500 posts (see [Generating sitemaps](generating-sitemaps.md#which-posts-are-included)). Each URL has:

- `loc`: the post's permalink.
- `lastmod`: when the post was last modified, in the site's timezone.
- `changefreq`: `monthly`, unless you [change it](customizing.md#change-changefreq-and-priority).
- `priority`: `0.7`, unless you [change it](customizing.md#change-changefreq-and-priority).

The daily sitemaps declare the Google News and Google Image namespaces, but the plugin doesn't add news or image tags itself. You can add your own with the [`msm_sitemap_entry` filter](hooks.md#msm_sitemap_entry).

Sitemaps are served from the XML stored when they were built, with a `200` status and an `application/xml` content type. Nothing is generated when a sitemap is requested.

## robots.txt

When the site is public, MSM Sitemap adds the sitemap index to the robots.txt that WordPress generates:

```text
# Sitemap archive
Sitemap: https://example.com/sitemap.xml
```

When indexing by year, there's a `Sitemap:` line for each year. If your site serves a physical `robots.txt` file, WordPress doesn't generate one, so add the lines to your file yourself.

## WordPress core sitemaps

WordPress has had its own sitemaps at `/wp-sitemap.xml` since version 5.5. MSM Sitemap turns them off, so they don't compete with its sitemaps, and `/wp-sitemap.xml` returns a 404 while the plugin is active.

If another plugin, such as an SEO plugin, also generates sitemaps, choose one of them to submit to search engines, and turn off sitemaps in the other if you can.

## Sites that aren't public

By default, sitemaps are only built and served when the site is public, that is, when the `blog_public` option is `1`. When it isn't:

- every sitemap URL returns a 404,
- **Tools → Sitemap** shows only a message saying sitemaps aren't supported on private sites,
- automatic updates can't be turned on, and a background build stops at the next day.

To use sitemaps on a site that isn't public, such as a staging site or a WordPress VIP site with `VIP_JETPACK_IS_PRIVATE` set, use the [`msm_sitemap_is_enabled` filter](customizing.md#use-sitemaps-on-a-site-that-isnt-public) rather than changing `blog_public`, which other features rely on. The robots.txt lines still only appear on public sites, so submit the sitemap index to search engines directly.

## Caching

Sitemaps only change when they're rebuilt, so a page cache in front of them is safe, as long as it's purged when they change. The [`msm_update_sitemap_post`](hooks.md#msm_update_sitemap_post) and [`msm_delete_sitemap_post`](hooks.md#msm_delete_sitemap_post) actions fire whenever a daily sitemap is rebuilt or deleted, with the date, so you can purge that day's sitemap and the index. See [Purge caches when a sitemap changes](customizing.md#purge-caches-when-a-sitemap-changes).
