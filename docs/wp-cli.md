# WP-CLI commands

Every command is a subcommand of `wp msm-sitemap`. Run `wp help msm-sitemap <command>` for the built-in help. On a multisite network, add `--url=` to choose the site.

Commands that take `--date` accept a year (`2024`), a month (`2024-07`), or a day (`2024-07-10`).

## generate

Builds sitemaps, in the WP-CLI process, one day at a time.

```shell
wp msm-sitemap generate --all
wp msm-sitemap generate --date=2024-07
```

| Option | Description |
| --- | --- |
| `--all` | Every day from the year of the oldest included post to today. Running `generate` with no options does the same. |
| `--date=<date>` | One year, month, or day. |
| `--quiet` | Only show errors. |
| `--force` | Count every day that has a sitemap afterwards, not only new ones. |

`generate` always rebuilds each day's sitemap from the current content, so it also refreshes existing sitemaps. It doesn't depend on WP-Cron, and works whether or not automatic updates are on. Days with no included posts have their sitemaps deleted.

By default, the count it reports is the number of sitemaps that didn't exist before. With `--force`, it's every day that has a sitemap afterwards.

## delete

Deletes sitemaps.

```shell
wp msm-sitemap delete --date=2024-07-10
wp msm-sitemap delete --all --yes
```

| Option | Description |
| --- | --- |
| `--all` | Every sitemap. |
| `--date=<date>` | One year, month, or day. |
| `--yes` | Don't ask for confirmation. |
| `--quiet` | Only show errors. |

Either `--all` or `--date` is required. You're asked to confirm when deleting all sitemaps, or more than one. Each deletion updates the indexed URL count and fires [`msm_delete_sitemap_post`](hooks.md#msm_delete_sitemap_post).

A deleted sitemap is built again the next time its day is rebuilt, for example when a post on that day changes and automatic updates are on.

## list

Lists sitemaps.

```shell
wp msm-sitemap list --date=2024-07 --fields=date,url_count
```

```text
+------------+-----------+------------------------------------------------------------+
| date       | url_count | sitemap_url                                                |
+------------+-----------+------------------------------------------------------------+
| 2024-07-10 | 50        | https://example.com/sitemap.xml?yyyy=2024&mm=07&dd=10      |
| 2024-07-11 | 48        | https://example.com/sitemap.xml?yyyy=2024&mm=07&dd=11      |
+------------+-----------+------------------------------------------------------------+
```

| Option | Description |
| --- | --- |
| `--all` | Every sitemap. The default. |
| `--date=<date>` | One year, month, or day. |
| `--fields=<fields>` | Any of `id`, `date`, `url_count`, `status`, `sitemap_url`. Default `id,date,url_count,status`. `sitemap_url` is always shown. |
| `--format=<format>` | `table` (default), `json`, or `csv`. |

## get

Shows one sitemap, by its post ID, or the sitemaps for a date.

```shell
wp msm-sitemap get 123
wp msm-sitemap get 2024-07-10 --format=json
```

Shows `id`, `date`, `url_count`, `status`, `last_modified` (UTC) and `sitemap_url`. `--format` is `table` (default), `json`, or `csv`.

## validate

Checks that each sitemap has XML, that the XML parses, and that it has at least one `<url>`. Reports each sitemap that fails, then the totals.

```shell
wp msm-sitemap validate --all
wp msm-sitemap validate --date=2024
```

Takes `--all` (the default) or `--date`.

## export

Writes each sitemap's XML to a file named after its date, such as `2024-07-10.xml`.

```shell
wp msm-sitemap export --all --output=sitemaps
wp msm-sitemap export --date=2024-07 --output=/tmp/sitemaps --pretty
```

| Option | Description |
| --- | --- |
| `--output=<path>` | Required. The directory to write to, created if needed. A relative path is relative to the current directory. |
| `--all` | Every sitemap. The default. |
| `--date=<date>` | One year, month, or day. |
| `--pretty` | Indent the XML. |

## recount

Counts the URLs in every sitemap again, and updates the totals shown on **Tools → Sitemap**. Use it if the counts look wrong.

```shell
wp msm-sitemap recount
```

## stats

Shows how many sitemaps there are, and the newest day that has one.

```shell
wp msm-sitemap stats --format=json
```

`--format` is `table` (default), `json`, or `csv`.

## cron

Manages [automatic updates](generating-sitemaps.md#turn-on-automatic-updates).

```shell
wp msm-sitemap cron enable
wp msm-sitemap cron status --format=json
```

| Subcommand | Description |
| --- | --- |
| `status` | Shows whether updates are on, when the next 15-minute check is due, whether the site is public, and whether a full build is running or halting. The default when no subcommand is given. Takes `--format`. |
| `enable` | Turns updates on, and schedules the 15-minute check. Fails if sitemaps aren't enabled for the site. |
| `disable` | Turns updates off, removes every scheduled sitemap event, and forgets the progress of any full build. |
| `reset` | Removes the saved setting, every scheduled sitemap event, and any build progress, as on a fresh install. This leaves updates off. |

## Older command names

These commands from 1.4 and earlier still work, for existing scripts. Use the newer commands for anything new.

| Old command | Use instead |
| --- | --- |
| `generate-sitemap` | `generate` |
| `generate-sitemaps` | `generate --all` |
| `generate-sitemap-for-year --year=2024` | `generate --date=2024` |
| `generate-sitemap-for-year-month --year=2024 --month=7` | `generate --date=2024-07` |
| `generate-sitemap-for-year-month-day --year=2024 --month=7 --day=10` | `generate --date=2024-07-10` |
| `recount-indexed-posts` | `recount` |
