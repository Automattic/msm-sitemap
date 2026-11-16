# Changelog for MSM Sitemap

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.0] - 2026-11-16

Sitemaps now stay up to date when posts are unpublished, scheduled posts go live or posts move to another date, and they can be served on sites that aren't public. This release also fixes many problems with sitemap generation, WP-CLI and the Tools → Sitemap screen. The minimum WordPress and PHP versions are unchanged.

**If your site uses the `msm_sitemap_post_status` filter, run `wp msm-sitemap generate --all` and then `wp msm-sitemap recount` after upgrading.** From 1.5.0 to 1.5.6, daily sitemaps on those sites returned a 404 and, from 1.5.5, the index was empty too.

### Added

* Add the `msm_sitemap_is_enabled` filter, to generate and serve sitemaps on a site where `blog_public` isn't `1`, such as a staging site or a WordPress VIP site with `VIP_JETPACK_IS_PRIVATE` set, without overriding `blog_public` itself. It covers the admin screen, serving, generation and enabling the cron. The `Sitemap:` lines in robots.txt still only appear on a public site by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/339
* GitHub releases now include an installable ZIP. Unlike GitHub's source archive, it includes the Flot library the Tools → Sitemap chart needs by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/366

### Changed

* The plugin is called MSM Sitemap again, rather than Metro Sitemap, on the Plugins screen and in the sitemap stylesheets. Class names and hooks are unchanged by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/353

### Fixed

* Rebuild a day's sitemap when a post on it is unpublished, a scheduled post goes live, or a published post moves to another date. Rebuilds after a post is deleted or trashed are now also scheduled at the right time, rather than hours early or late on sites outside UTC by @faisalahammad in https://github.com/Automattic/msm-sitemap/pull/328
* `msm_sitemap_skip_post` and `msm_sitemap_entry` callbacks can use `get_post()`, `get_the_ID()` and other template tags again, as they could up to 1.5.3. Since 1.5.4 they saw no post, so a skip callback written that way skipped nothing. As before 1.5.4, `the_post` fires for each URL during generation by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/341
* Fix sitemaps on sites that use the `msm_sitemap_post_status` filter: sitemap posts are published again, whatever status the content uses (see the upgrade note above) by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/350
* The update cron no longer drops changes when more than 1,000 posts change between runs; it carries on from where it stopped on the next run. A site with more than 1,000 edits every 15 minutes, sustained, now falls behind rather than losing them, and Tools → Sitemap shows an older "Updated" time while it catches up by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/348
* The update cron picks up edits made early in the day on sites ahead of UTC, which it used to treat as future-dated and miss by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/357
* The update cron no longer fails on every run when a `msm_pre_get_last_modified_posts` callback empties its query by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/361
* Updates to single days no longer skip or repeat days of a full sitemap generation that is running by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/345
* A full generation whose next step is lost, for example because a large day runs out of memory, now resumes on its own within about half an hour, and a halt requested meanwhile completes. Previously it showed as running until automatic updates were disabled and enabled again by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/363
* After a generation was halted, `wp msm-sitemap generate` for a year, month or day no longer stops straight away on every later run by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/358
* Don't schedule sitemap rebuilds while the plugin's cron is off, as they did nothing when they ran. Changes made while the cron is off still need `wp msm-sitemap generate --all` (or "Generate from all articles") to catch up once it's on by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/362
* A priority or change frequency of the wrong type from `msm_sitemap_priority` or `msm_sitemap_changefreq` now leaves that post out and is logged, rather than stopping the whole day's sitemap. A numeric string priority, such as `'0.8'`, is accepted by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/356
* Generating a day's sitemap runs far fewer database queries, and a day with no posts no longer creates and then deletes a sitemap post by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/346
* Long WP-CLI runs of `generate`, `recount`, `validate`, `export`, `list` and `get` no longer run out of memory by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/347
* `wp msm-sitemap delete` now keeps the URL total right and fires `msm_delete_sitemap_post`, so cache purges run by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/352
* `/sitemap.xml` works straight after activating the plugin, without saving Settings → Permalinks first by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/351
* The buttons on Tools → Sitemap work when the admin is translated by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/349
* Tools → Sitemap meets WCAG 2.2 AA color contrast, and screen readers announce it correctly by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/344
* Strings on Tools → Sitemap and in WP-CLI translate properly, numbers and dates follow the admin's locale, and "Next Update" shows the site's time rather than the server's by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/365

### Security

* Only the plugin can now create, edit or delete sitemap posts and their stored XML, which is what the site serves as its sitemap. Previously any user who could edit posts could change them through the post editor, quick and bulk edit, admin-ajax or XML-RPC. The `msm_sitemap` post type no longer appears in the admin; use `wp msm-sitemap list`, `get` and `delete` instead by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/360

### Maintenance

* Test on PHP 8.4 and 8.5, and mark as tested up to WordPress 7.1 by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/340
* Run CI on Node 24, run the integration tests when npm dependencies change, fix local code coverage runs, and make the tests check what the 1.6.0 changes do by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/326, https://github.com/Automattic/msm-sitemap/pull/333, https://github.com/Automattic/msm-sitemap/pull/359, https://github.com/Automattic/msm-sitemap/pull/355, and https://github.com/Automattic/msm-sitemap/pull/364
* Move the developer guide into `docs/` and correct it, and add a contributing guide and a security policy by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/354, https://github.com/Automattic/msm-sitemap/pull/325, https://github.com/Automattic/msm-sitemap/pull/323, and https://github.com/Automattic/msm-sitemap/pull/324
* Update development dependencies by @dependabot

## [1.5.6] - 2026-05-19

### Fixed

* fix: Date handling in `get_last_modified_posts()` for negative-offset timezones by @kevinfodness in https://github.com/Automattic/msm-sitemap/pull/297

### Maintenance

* chore: Add export-ignore for dev files in release archives by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/267
* chore: Pin i18n script to plugin slug so it works from any working directory by @GaryJones

## [1.5.5] - 2026-01-02

### Fixed

* fix: Use post_modified for sitemap index lastmod values by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/262
* fix: Use site timezone for lastmod dates in URL entries by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/261
* fix: Explicitly set 200 status for sitemap output by @GaryJones in https://github.com/Automattic/msm-sitemap/pull/251

## [1.5.4] - 2025-12-18

### Fixed

* fix: Use site timezone for sitemap generation by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/208>
* fix: Use site timezone for sitemap index lastmod dates by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/248>

### Added

* feat: Introduce cron management service and enhanced admin UI by @GaryJones
* feat: Add WP-CLI `cron` subcommand for sitemap cron management by @GaryJones
* feat: Introduce value objects for sitemap entries (SitemapIndexEntry, UrlEntry, UrlSet) by @GaryJones

### Maintenance

* refactor: Reorganise tests into Integration directory by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/247>
* ci: Standardise CI workflows and test matrix by @GaryJones
* chore: Align git configuration with a8c-plugins standards by @GaryJones

## [1.5.3] - 2025-09-15

### Changed

* feat: use custom XSL stylesheets by @GaryJones (hotfix)

## [1.5.2] - 2025-07-31

### Fixed

* fix: Add post deletion handling to update sitemap by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/211>

### Maintenance

* style: fix more code style violations by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/212>

## [1.5.1] - 2025-07-30

### Added

* feat: Add XSL stylesheets to XML sitemaps by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/206>
* feat: Add sitemap_url to list and get CLI output by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/207>

### Changed

* Add a post_status/post_type to query for performance by @srtfisher in <https://github.com/Automattic/msm-sitemap/pull/169>

### Maintenance

* style: Run PHPCBF to automatically fix some CS violations by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/205>

## [1.5.0] – 2025-07-16

Requires:

* WordPress: 5.9 or later
* PHP 7.4 or later

### Added

* Adding hook for custom post status with tests by @elysium001 in <https://github.com/Automattic/msm-sitemap/pull/176>
* Adding custom hook for generated xml properties by @elysium001 in <https://github.com/Automattic/msm-sitemap/pull/177>
* Add `msm_pre_get_last_modified_posts` filter by @rbcorrales in <https://github.com/Automattic/msm-sitemap/pull/178>
* Allow the post year range to be short-circuited and also cached by @srtfisher in <https://github.com/Automattic/msm-sitemap/pull/195>
* feat: Add filters for sitemap index XML customization by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/197>
* feat: Enhance WP-CLI commands for Metro Sitemap by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/202>

### Changed

* Substitute cal_days_in_month by @david-binda in <https://github.com/Automattic/msm-sitemap/pull/130>
* Disables WordPress 5.5 Sitemaps by @kraftbj in <https://github.com/Automattic/msm-sitemap/pull/160>
* Convert boolean options to bool before strict comparison by @rickhurst in <https://github.com/Automattic/msm-sitemap/pull/164>
* Update supported PHP version to 7.4 and WP 5.9 minimum by @mchanDev in <https://github.com/Automattic/msm-sitemap/pull/175>
* Respect `WPCOM_SKIP_DEFAULT_SITEMAP` constant by @renatonascalves in <https://github.com/Automattic/msm-sitemap/pull/180>
* Refresh tests, up minimum WP version by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/181>
* Add Post ID to `msm_sitmap_skip_posts` by @BrookeDot in <https://github.com/Automattic/msm-sitemap/pull/184>

### Fixed

* If all posts were skipped, remove the sitemap post by @adamsilverstein in <https://github.com/Automattic/msm-sitemap/pull/122/>
* Temporary workaround for sitemaps returning 404 by @vaurdan in <https://github.com/Automattic/msm-sitemap/pull/161>
* PHP 8.1 date error by @mchanDev in <https://github.com/Automattic/msm-sitemap/pull/174>
* Update `WP_CLI::line()` to `WP_CLI::log()` by @raamdev in <https://github.com/Automattic/msm-sitemap/pull/185>
* Install SVN before checking out tests by @whyisjake in <https://github.com/Automattic/msm-sitemap/pull/186>
* fix: i18n issues by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/194>
* fix: Enhance tooltip for indexed URLs with pluralization support by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/196>
* Add custom post permalink handling for msm_sitemap posts by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/201>

### Maintenance

* Setup GitHub actions by @trepmal in <https://github.com/Automattic/msm-sitemap/pull/166>
* Update plugin headers for clarity by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/187>
* test: Add testdox argument by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/190>
* Refactor FunctionsTest for clarity and consistency by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/191>
* test: Increase WP max version for tests by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/192>
* test: Refactor test methods into TestCase by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/193>
* Add lots of tests by @GaryJones in <https://github.com/Automattic/msm-sitemap/pull/198>

### Documentation

* Migrating plugin instructions from old VIP Docs site to plugin's `README.md` by @yolih in <https://github.com/Automattic/msm-sitemap/pull/172>
* Update plugins, update tests by @mchanDev in <https://github.com/Automattic/msm-sitemap/pull/173>

## [1.4.2] – 2020-01-10

### Fixed

* Fix slow query by @emrikol in <https://github.com/Automattic/msm-sitemap/pull/153>

### Changed

* Remove cron override code for VIP Go by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/139>

### Documentation

* Fix typo for default in README.md by @pjpak in <https://github.com/Automattic/msm-sitemap/pull/148>

## [1.4.1] – 2019-03-05

### Added

* Add filter to limit the sitemap date range by @kasparsd in <https://github.com/Automattic/msm-sitemap/pull/147>

### Maintenance

* Update the plugin version and the stable tag by @shantanu2704 in <https://github.com/Automattic/msm-sitemap/pull/142>
* Update Version and Stable tag to 1.4.1 by @brettshumaker in <https://github.com/Automattic/msm-sitemap/pull/150>

## [1.4] – 2018-10-15

### Fixed

* Improve `MSM_Sitemap::get_post_ids_for_date()` SQL's performance by sorting by date via PHP. by @david-binda in <https://github.com/Automattic/msm-sitemap/pull/128>
* Remove extra wp query by @david-binda in <https://github.com/Automattic/msm-sitemap/pull/129>
* Force `$(days|months|years)_being_processed` to an array by @kraftbj in <https://github.com/Automattic/msm-sitemap/pull/134>
* CLI: Use cal_days_in_month when available by @kraftbj in <https://github.com/Automattic/msm-sitemap/pull/136>

### Changed

* Don't autoload indexed url and last run options by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/111>
* Run on wp-cron on VIP Go by @joshbetz in <https://github.com/Automattic/msm-sitemap/pull/131>

### Documentation

* Added Filtering Sitemap URL info to plugin Readme by @blunce24 in <https://github.com/Automattic/msm-sitemap/pull/141>

### Maintenance

* Removing unused variable by @david-binda in <https://github.com/Automattic/msm-sitemap/pull/125>
* Removing unused MSM_Sitemap::find_valid_days method by @david-binda in <https://github.com/Automattic/msm-sitemap/pull/126>
* Fix tests in PHP 7 by @david-binda in <https://github.com/Automattic/msm-sitemap/pull/127>

## [1.3] – 2017-07-07

### Added

* Adding the ability to index sitemaps by year by @bcampeau in <https://github.com/Automattic/msm-sitemap/pull/119>

### Fixed

* Fix if no posts exist, oldest year is set to 1970 by @mdbitz in <https://github.com/Automattic/msm-sitemap/pull/100>
* Use `c` as the date format for lastmod by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/101>
* Escape loc URL by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/103>
* Requests for Sitemaps that don't exist return 404 error messages by @mdbitz in <https://github.com/Automattic/msm-sitemap/pull/105>
* Make sure to only output unique dates by @pkevan in <https://github.com/Automattic/msm-sitemap/pull/115>

### Changed

* Filter added to posts_pre_query to bypass DB Call on sitemap render by @mdbitz in <https://github.com/Automattic/msm-sitemap/pull/106>
* Optimize `get_last_modified_posts` query by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/107>

### Maintenance

* Unit Test update to resolve run time and concerns with current logic by @mdbitz in <https://github.com/Automattic/msm-sitemap/pull/97>
* Fix Travis by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/120>

## [1.2.1] – 2016-12-21

### Fixed

* Use date range query for getting single sitemap by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/95>
* Adding `post_type`, `posts_per_page` and `post_status` to WP_Query used in … by @david-binda in <https://github.com/Automattic/msm-sitemap/pull/96>

## [1.2] – 2016-12-20

### Fixed

* Fixing issue #82 where sitemap counts do not appear by @pkevan in <https://github.com/Automattic/msm-sitemap/pull/83>
* Ensure cron events are scheduled with consistent data types in their arguments by @ethitter in <https://github.com/Automattic/msm-sitemap/pull/90>
* Switch to raw SQL from `WP_Date_Query` by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/93>
* Switch to `get_post_modified_time()` for UTC by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/94>

### Changed

* Adding VIP Go helper to block initial crons by @pkevan in <https://github.com/Automattic/msm-sitemap/pull/84>
* Add update cron for VIP Go by @pkevan in <https://github.com/Automattic/msm-sitemap/pull/85>
* VIPGo helpers and sanity check for cron update by @pkevan in <https://github.com/Automattic/msm-sitemap/pull/86>

### Maintenance

* Create `.travis.yml` by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/91>

## [1.1] – 2016-09-28

### Fixed

* Delete Empty Sitemaps by @mjangda in <https://github.com/Automattic/msm-sitemap/pull/80>

## 1.0 - 2015-06-17

Initial release.

* Enable stable Composer installations from origin.

[1.6.0]: https://github.com/automattic/msm-sitemap/compare/1.5.6...1.6.0
[1.5.6]: https://github.com/automattic/msm-sitemap/compare/1.5.5...1.5.6
[1.5.5]: https://github.com/automattic/msm-sitemap/compare/1.5.4...1.5.5
[1.5.4]: https://github.com/automattic/msm-sitemap/compare/1.5.3...1.5.4
[1.5.3]: https://github.com/automattic/msm-sitemap/compare/1.5.2...1.5.3
[1.5.2]: https://github.com/automattic/msm-sitemap/compare/1.5.1...1.5.2
[1.5.1]: https://github.com/automattic/msm-sitemap/compare/1.5.0...1.5.1
[1.5.0]: https://github.com/automattic/msm-sitemap/compare/1.4.2...1.5.0
[1.4.2]: https://github.com/automattic/msm-sitemap/compare/1.4.1...1.4.2
[1.4.1]: https://github.com/automattic/msm-sitemap/compare/1.4.0...1.4.1
[1.4]: https://github.com/automattic/msm-sitemap/compare/1.3.0...1.4.0
[1.3]: https://github.com/automattic/msm-sitemap/compare/1.2.1...1.3.0
[1.2.1]: https://github.com/automattic/msm-sitemap/compare/1.2.0...1.2.1
[1.2]: https://github.com/automattic/msm-sitemap/compare/1.1.0...1.2.0
[1.1]: https://github.com/automattic/msm-sitemap/compare/1.0.0...1.1.0
