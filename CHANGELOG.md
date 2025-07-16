# Changelog for Metro Sitemap

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.4.2]: https://github.com/automattic/msm-sitemap/compare/1.4.1...1.4.2
[1.4.1]: https://github.com/automattic/msm-sitemap/compare/1.4.0...1.4.1
[1.4]: https://github.com/automattic/msm-sitemap/compare/1.3.0...1.4.0
[1.3]: https://github.com/automattic/msm-sitemap/compare/1.2.1...1.3.0
[1.2.1]: https://github.com/automattic/msm-sitemap/compare/1.2.0...1.2.1
[1.2]: https://github.com/automattic/msm-sitemap/compare/1.1.0...1.2.0
[1.1]: https://github.com/automattic/msm-sitemap/compare/1.0.0...1.1.0
