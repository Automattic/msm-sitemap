# Changelog for Metro Sitemap

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
