# Changelog

All notable changes to `laravel-activity-feed` will be documented in this file.

## [Unreleased]

## [0.1.0] - 2025-01-21

**Initial beta release** - Package is feature-complete and ready for testing.

### Added
- Initial release
- FeedItem model with polymorphic entity relationships
- FeedItemEntity model for multiple entities per feed item
- Dynamic description rendering with contextual "You" resolution
- Fluent API builder pattern for creating feed items
- HasFeed trait for models
- Query scopes for filtering feeds
- Automatic caching of rendered descriptions
- Cleanup command for retention policy
- Support for system-generated activities (no actor)
- Comprehensive documentation and examples
- Unit tests for core functionality

### Database
- `feed_items` table with optimized indexes
- `feed_item_entities` table with composite indexes
- `feed_subscriptions` table for relationship-based feeds

### Features
- Multiple entity roles: actor, subject, target, mentioned, related
- Template-based descriptions with property placeholders
- Configurable retention and auto-cleanup
- Eager loading support to prevent N+1 queries
- Cursor pagination support for large datasets
