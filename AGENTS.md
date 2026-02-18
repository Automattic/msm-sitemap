# MSM Sitemap

High-performance XML sitemap generator for large WordPress sites.

## Project Knowledge

| Property | Value |
|----------|-------|
| **Main file** | `msm-sitemap.php` |
| **Text domain** | `msm-sitemap` |
| **Namespace** | `Automattic\MSM_Sitemap` |
| **Source directory** | `includes/` |
| **Version** | 2.0.0-dev |
| **Requires PHP** | 8.2+ |
| **Requires WP** | 6.4+ |

### Directory Structure

```
msm-sitemap/
├── includes/
│   ├── Domain/             # Value objects, entities, repository interfaces
│   ├── Application/        # Use cases and services
│   └── Infrastructure/     # WordPress integration, persistence, DI container
├── tests/
│   ├── Unit/               # Unit tests (Brain Monkey)
│   └── Integration/        # Integration tests (wp-env)
├── assets/                 # XSL stylesheets for human-readable sitemaps
├── docs/                   # Documentation
├── .github/workflows/      # CI: cs-lint, integration, unit
└── .phpcs.xml.dist         # PHPCS configuration
```

### Key Classes

- **Domain**: `UrlEntry`, `UrlSet`, `SitemapDate`, `ImageEntry` (value objects); repository interfaces
- **Application**: `SitemapService`, `SitemapGenerationService`, `TaxonomySitemapService`, `UrlSetFactory`
- **Infrastructure**: `PostRepository`, `TaxonomyRepository`, `AuthorRepository`, `PageRepository`, `ImageRepository`, `Container` (DI), `SitemapXmlFormatter`, `EventDispatcher`
- WP-CLI commands for sitemap generation and management

### Dependencies

- **Dev**: `automattic/vipwpcs`, `yoast/wp-test-utils`, `phpunit/phpcov` (coverage merging)

## Commands

```bash
composer cs                    # Check code standards (PHPCS)
composer cs-fix                # Auto-fix code standard violations
composer lint                  # PHP syntax lint
composer test:unit             # Run unit tests
composer test:integration      # Run integration tests (requires wp-env)
composer test:integration-ms   # Run multisite integration tests
composer coverage:unit         # Unit test coverage
composer coverage:integration  # Integration test coverage
composer coverage:merge        # Merge coverage reports
```

## Conventions

Follow the standards documented in `~/code/plugin-standards/` for full details. Key points:

- **Commits**: Use the `/commit` skill. Favour explaining "why" over "what".
- **PRs**: Use the `/pr` skill. Squash and merge by default.
- **Branch naming**: `feature/description`, `fix/description` from `develop`.
- **Testing**: Write integration tests for WordPress-dependent behaviour, unit tests for isolated logic. Use `Yoast\WPTestUtils\WPIntegration\TestCase` for integration, `Yoast\WPTestUtils\BrainMonkey\YoastTestCase` for unit.
- **Code style**: WordPress coding standards via PHPCS. Tabs for indentation.
- **i18n**: All user-facing strings must use the `msm-sitemap` text domain.
- **DDD layering**: Domain classes must not depend on Infrastructure or Application. Application orchestrates Domain. Infrastructure implements interfaces defined in Domain.

## Architectural Decisions

- **Domain-Driven Design (DDD)**: The plugin uses a three-layer architecture (Domain/Application/Infrastructure). This is intentional and well-established — do not bypass layers (e.g., do not call WordPress functions directly from Domain classes).
- **Value objects**: URL entries, sitemap dates, and image entries are immutable value objects. Create new instances rather than mutating existing ones.
- **Repository pattern**: Database access is abstracted behind repository interfaces defined in Domain, with WordPress implementations in Infrastructure. Tests can mock repositories.
- **Dependency Injection container**: Services are wired via a DI container in Infrastructure. Register new services there rather than instantiating them directly.
- **Custom post type for storage**: Sitemaps are stored as a custom post type for performance on large sites. This is a deliberate architectural choice — do not switch to file-based storage.
- **Tier 1 plugin**: This is a well-maintained, modernised plugin. It serves as a reference implementation for the standards.

## Common Pitfalls

- Do not edit WordPress core files or bundled dependencies in `vendor/`.
- Run `composer cs` before committing. CI will reject code standard violations.
- Integration tests require `npx wp-env start` running first.
- **Do not violate DDD layer boundaries**: Domain must not `use` anything from Infrastructure or Application. If you need WordPress functions in Domain code, define an interface in Domain and implement it in Infrastructure.
- **Value objects are immutable**: Never add setters to value objects. Create new instances instead.
- Do not instantiate services with `new` — use the DI container.
- Sitemaps for large sites can involve thousands of posts. Always consider performance implications — avoid N+1 queries and unbounded loops.
- The legacy function prefix `msm_` coexists with the namespaced code. Do not remove legacy functions that may be used by third-party code without a deprecation cycle.
