Comprehensive Sitemaps
===========

Comprehensive sitemaps for your WordPress.com VIP site. Joint collaboration between Metro.co.uk, WordPress.com VIP, Alley Interactive, Maker Media, 10up, and others.

## How It Works

### Sitemap Data Storage

* One post type entry for each date.
* Sitemap XML is generated and stored in meta. This has several benefits:
 * Avoid memory and timeout problems when rendering heavy sitemap pages with lots of posts.
 * Older archives that are unlikely to change can be served up faster since we're not building them on-demand.
* Archive pages are rendered on-demand.

### Sitemap Generation

We want to generate the entire sitemap catalogue async to avoid running into timeout and memory issues.

Here's how the defualt WP-Cron approach works:

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
