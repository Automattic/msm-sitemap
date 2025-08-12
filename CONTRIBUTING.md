# Contributing to MSM Sitemap

Thank you for your interest in contributing to MSM Sitemap! This document provides guidelines for contributing to the project.

## Requirements

* **Minimum Requirements:** WordPress 5.9+, PHP 7.4+
* **Coding Standards:** Follows [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) and [PSR-12](https://www.php-fig.org/psr/psr-12/)

## Development Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Automattic/msm-sitemap.git
   cd msm-sitemap
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Set up WordPress test environment:**
   ```bash
   # The plugin includes PHPUnit configuration for WordPress testing
   # Make sure you have a WordPress test environment available
   ```

## Testing

### Running Tests

```bash
# Run  tests
composer test

# Run slow tests with verbose output
composer test-slow
```

### Code Quality

```bash
# Run linting
composer lint

# Run code style checks
composer cs

# Fix code style issues automatically
composer cbf
```

### Testing Architecture

The plugin uses a **filter-based testing approach** to ensure clean separation between production and test code:

#### Cron Testing
The `msm_sitemap_cron_enabled` filter allows tests to override cron status without modifying production code:

```php
// In tests, force cron to be enabled
add_filter( 'msm_sitemap_cron_enabled', '__return_true' );

// Or force it to be disabled
add_filter( 'msm_sitemap_cron_enabled', '__return_false' );
```

#### REST API Testing
REST API tests use WordPress's built-in test framework and require proper action hooks:

```php
// Trigger the rest_api_init action to properly register routes
do_action( 'rest_api_init' );
```

## Code Standards

### PHP Code Style

- Follow [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
- Follow [PSR-4](https://www.php-fig.org/psr/psr-12/) for class file naming
- Use type declarations where appropriate
- Write self-documenting code with clear variable and function names

### Documentation

- Use PHPDoc comments for all public methods and classes
- Include business case context in documentation
- Provide type-safe, documented code samples
- Update documentation when adding new features

### Git Commit Messages

- Use [Conventional Commits](https://www.conventionalcommits.org/) format
- Keep the first line under 50 characters
- Use prefixes: `feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`
- Provide detailed description in the body when needed

## Architecture Guidelines

### Service Layer Pattern

The plugin uses a **Service Layer pattern** for business logic:

- **Single Responsibility**: Each class has a clear, focused purpose
- **Testability**: Service logic can be tested independently using filters
- **Maintainability**: Changes to logic only require updating the service
- **Extensibility**: New features can be added without affecting existing code
- **Clean Separation**: UI rendering is separate from action handling

### Dependency Injection

- Use the `SitemapContainer` for service registration and resolution
- Register new services in the container
- Inject dependencies through constructor injection
- Avoid static method calls for business logic

### WordPress Integration

- Follow WordPress coding standards and conventions
- Use WordPress hooks and filters for extensibility
- Leverage WordPress built-in functionality where appropriate
- Maintain backward compatibility when possible

## Submitting Contributions

### Before Submitting

1. **Run all tests** to ensure your changes don't break existing functionality
2. **Check code style** to ensure compliance with standards
3. **Update documentation** for any new features or changes
4. **Test manually** in a WordPress environment

### Pull Request Process

1. **Fork the repository** and create a feature branch
2. **Make your changes** following the guidelines above
3. **Write tests** for new functionality
4. **Update documentation** as needed
5. **Submit a pull request** with a clear description of changes

### Pull Request Guidelines

- **Title**: Use conventional commit format (e.g., "feat: Add new REST API endpoint")
- **Description**: Explain what the PR does and why
- **Testing**: Describe how you tested the changes
- **Breaking Changes**: Note any breaking changes clearly
- **Related Issues**: Link to any related issues or discussions

## Getting Help

- **GitHub Issues**: For bug reports and feature requests
- **WordPress VIP Support**: For WPVIP customers
- **Code Reviews**: All contributions are reviewed by maintainers

## Recognition

Contributors are recognized in:
- [CHANGELOG.md](./CHANGELOG.md) for significant contributions
- GitHub contributors list
- Plugin documentation where appropriate

Thank you for contributing to MSM Sitemap!
