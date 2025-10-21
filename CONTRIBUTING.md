# Contributing to Laravel Activity Feed

Thank you for considering contributing to Laravel Activity Feed! Here are some guidelines to help you get started.

## How to Contribute

### Reporting Bugs

If you find a bug, please open an issue with:
- A clear, descriptive title
- Steps to reproduce the issue
- Expected behavior
- Actual behavior
- Laravel version, PHP version, and package version

### Suggesting Features

Feature suggestions are welcome! Please open an issue with:
- A clear description of the feature
- Use cases and benefits
- Any potential implementation ideas

### Pull Requests

1. **Fork the repository** and create a new branch from `main`
2. **Write tests** for your changes
3. **Ensure tests pass** by running `composer test`
4. **Follow PSR-12 coding standards**
5. **Update documentation** if needed (README.md, EXAMPLES.md)
6. **Submit a pull request** with a clear description of changes

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/laravel-activity-feed.git
cd laravel-activity-feed

# Install dependencies
composer install

# Run tests
composer test
```

## Coding Standards

This project follows PSR-12 coding standards. Please ensure your code adheres to these standards.

```bash
# Check code style
./vendor/bin/phpcs

# Fix code style automatically
./vendor/bin/phpcbf
```

## Testing

All new features and bug fixes should include tests.

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/phpunit tests/Unit/FeedItemBuilderTest.php
```

## Documentation

When adding new features:
1. Update the README.md with usage examples
2. Add examples to EXAMPLES.md if applicable
3. Update the CHANGELOG.md

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Focus on the best outcome for the community

## Questions?

Feel free to open an issue for any questions about contributing.

Thank you for helping make Laravel Activity Feed better!
