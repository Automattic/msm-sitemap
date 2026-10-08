# Contributing to Metro Sitemap

Thank you for your interest in contributing. This document covers setting up a local environment, running the checks, and proposing a change. For the plugin's architecture, hooks and WP-CLI commands, see [DEVELOPERS.md](./DEVELOPERS.md).

## Code of Conduct

This project follows the [Automattic Code of Conduct](https://automattic.com/code-of-conduct/).

## Development Setup

You need [Node.js](https://nodejs.org/), [Docker](https://www.docker.com/products/docker-desktop) and [Composer](https://getcomposer.org/).

```bash
composer install
npm ci
npx wp-env start
```

### Checks

```bash
composer lint                # PHP syntax
composer cs                  # Coding standards
composer test:unit           # Unit tests
composer test:integration    # Integration tests (single site, needs wp-env)
composer test:integration-ms # Integration tests (multisite, needs wp-env)
```

## Workflow

1. Create a branch from `develop`.
2. Make your change, with tests.
3. Run the checks locally.
4. Push your branch and open a pull request against `develop`.

## Pull Requests

- Keep each pull request to one change, and explain what it does and why.
- Reference related issues, for example `Fixes #123`.
- Make sure CI passes before requesting a review.
- **Sign your commits.** `develop` and `main` only accept commits with a verified signature, so a pull request with an unsigned commit cannot be merged until it is re-signed and force-pushed. See GitHub's guide to [signing commits](https://docs.github.com/en/authentication/managing-commit-signature-verification/signing-commits).
