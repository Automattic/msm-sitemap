# Generating sitemaps

MSM Sitemap builds one sitemap for each day that has published content, stores it, and serves the stored copy when it's requested. This page covers how those sitemaps get built and kept up to date.

## Nothing is built until you start it

Activating the plugin doesn't build any sitemaps, and doesn't schedule anything. On a site with millions of posts, building every sitemap at once can put a real load on the database, so the plugin leaves the timing to you. Until you build them, `/sitemap.xml` is an empty index.

There are two steps:

1. **Build sitemaps for your existing content**, either from the admin screen or with WP-CLI.
2. **Turn on automatic updates**, so sitemaps follow your content as it's published, changed, and removed.

Sitemaps can only be built and served when they're enabled for the site. By default, that means the site must be public (**Settings → Reading → Search engine visibility** unchecked). To use sitemaps on a site that isn't public, such as a staging site, see [Serving sitemaps](serving-sitemaps.md#sites-that-arent-public).

## Turn on automatic updates

From the admin: go to **Tools → Sitemap** and choose **Enable Automatic Updates** under **Cron Management**.

From the shell:

```shell
wp msm-sitemap cron enable
```

This schedules an event that runs every 15 minutes. Turning updates off again (**Disable Automatic Updates**, or `wp msm-sitemap cron disable`) removes every scheduled sitemap event and forgets the progress of any full build in progress. Sitemaps already built are kept, and still served.

Turning on automatic updates doesn't build sitemaps for content that already exists. Do that once, as described below.

### On a multisite network

Each site has its own sitemaps and its own automatic updates setting, even when the plugin is network-activated. There's no network-wide switch, so turn updates on for each site. With WP-CLI:

```shell
wp site list --field=url | xargs -I % wp msm-sitemap cron enable --url=%
```

## Build sitemaps for existing content

### With WP-CLI (recommended for large sites)

```shell
wp msm-sitemap generate --all
```

`generate` builds sitemaps in the WP-CLI process itself, one day at a time, so it doesn't depend on WP-Cron and works whether or not automatic updates are on. You can build a year, month, or day at a time, which makes a large site easier to build in stages:

```shell
wp msm-sitemap generate --date=2024
wp msm-sitemap generate --date=2024-07
wp msm-sitemap generate --date=2024-07-10
```

Press Ctrl+C to stop. Every day finished before then keeps its sitemap. See [WP-CLI commands](wp-cli.md) for every option.

### From the admin

With automatic updates on, go to **Tools → Sitemap** and choose **Generate from all articles**. The buttons under **Generate** stay disabled until automatic updates are on.

The build runs in the background through WP-Cron, newest year first, one day per scheduled event. It saves its progress as it goes, so it carries on where it left off if it's interrupted. **Sitemap Creation Status** shows **Running** until it finishes.

- **Halt Sitemap Generation** stops the build after the day it's working on.
- **Generate from recently modified posts** runs the regular 15-minute check straight away (see below).
- **Reset Sitemap Data** deletes the stored XML, the URL counts, and the progress of any build. It doesn't delete the sitemap posts themselves, so the index still lists those days, with empty sitemaps, until you build them again. To remove sitemaps completely, use `wp msm-sitemap delete --all`.

Because WP-Cron only runs when someone visits the site, a background build can take a long time on a quiet site or one with many years of content. A server cron job that runs WP-Cron every minute speeds it up. On WordPress VIP, cron is already run for you.

## How sitemaps stay up to date

With automatic updates on, a day's sitemap is rebuilt when:

- **A post is published, unpublished, or goes live on schedule.** The day is queued as soon as the status changes.
- **A published post's date changes.** Both the old day and the new day are queued.
- **A post is trashed or deleted.**
- **A published post is edited.** The 15-minute check finds posts modified since it last ran, up to 1,000 at a time, and queues each day they belong to. Its first run looks back one hour.

Queued days are rebuilt by WP-Cron on its next run. The 15-minute check spaces the days it queues a minute apart, so a burst of edits doesn't rebuild everything at once.

Only posts of the post types and status the sitemap includes trigger a rebuild: published `post` posts, unless you've [changed that](customizing.md#include-other-post-types).

If automatic updates are off, these changes are not picked up. Run `wp msm-sitemap generate` for the affected dates, or rebuild everything, when you turn them back on.

## Which posts are included

Each daily sitemap lists the published posts of the included post types whose publish date falls on that day, in the site's timezone. By default, only the `post` post type is included. You can [add other post types](customizing.md#include-other-post-types), [use a different post status](customizing.md#include-posts-with-a-different-status), and [leave out particular posts](customizing.md#leave-posts-out).

A daily sitemap holds at most 500 URLs. If you publish more than 500 posts on a single day, the rest are left out of that day's sitemap. If that's likely on your site, [raise the limit](customizing.md#raise-the-number-of-urls-per-day).

A day with no included posts has no sitemap. If its last post is removed, its sitemap is deleted.
