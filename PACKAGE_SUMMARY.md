# Laravel Activity Feed Package - Complete Summary

## Package Name
`laravel-activity-feed`

## Overview
A robust Laravel package for creating activity feeds with dynamic entity resolution and multiple relationships. Designed for building Facebook-style feeds, purchase order tracking, audit logs, and any system requiring activity timelines.

## Key Features

### 1. Dynamic Entity Resolution
- Entity names automatically update when models change
- No need to store static text - references are resolved at runtime
- Example: If user "John Doe" changes name to "John Smith", all feed items automatically reflect the new name

### 2. Contextual Rendering
- Automatically shows "You" when viewing your own activities
- Personalized feed experience per viewer
- Example: "John approved the order" vs "You approved the order"

### 3. Multiple Entities Per Item
- Support for unlimited entities per feed item
- Predefined roles: actor, subject, target, mentioned, related
- Custom roles supported
- Example: Track actor (who did it), subject (what was affected), mentioned users, etc.

### 4. System Actions
- Support for activities without an actor
- Perfect for automated/system-generated events
- Example: "Approval flow restarted for Order #123"

### 5. Performance Optimized
- Composite database indexes for fast queries
- Built-in caching (15-minute TTL by default)
- Eager loading support to prevent N+1 queries
- Cursor pagination for large datasets
- Designed for medium-high traffic (1K-100K items/day)

### 6. Flexible Querying
- Filter by entity, action, date range
- Query across multiple entities
- Relationship-based feeds
- Complex query combinations

## Database Architecture

### Table: `feed_items`
Stores the main activity data:
- `action`: Type of activity (created, updated, approved, etc.)
- `description_template`: Template with placeholders like "{actor} approved {subject}"
- `properties`: JSON storage for additional metadata
- `occurred_at`: When the activity happened
- Indexes: Composite index on (occurred_at, action) for fast filtering

### Table: `feed_item_entities`
Polymorphic pivot table for entity relationships:
- Links feed items to multiple entities
- Stores entity role (actor, subject, etc.)
- Indexes: Composite indexes on (entity_type, entity_id, role) and (feed_item_id, role)

### Table: `feed_subscriptions` (Optional)
For building relationship-based feeds:
- Track who follows what
- Enable personalized feeds

### Database Engine
**InnoDB** - Chosen for:
- Good balance of read/write performance
- Transaction support
- Foreign key constraints
- Suitable for medium-high volume (1K-100K items/day)

## API Design

### Fluent Builder API
```php
feed()
    ->withAction('approved')
    ->withTemplate('{actor} approved {subject} for {amount}')
    ->causedBy($user)              // Actor
    ->performedOn($order)          // Subject
    ->mentioning($requester)       // Mentioned user
    ->withProperties(['amount' => '$500'])
    ->log();
```

### HasFeed Trait
```php
// Add to any model
use HasFeed;

// Quick logging
$order->logActivity('updated', '{actor} updated the order');

// Get feed items
$order->feedItems()->latest('occurred_at')->get();
```

### Query Scopes
```php
FeedItem::forEntity($order, 'subject')
    ->ofAction(['approved', 'declined'])
    ->inPeriod(now()->subDays(7), now())
    ->with('entities.entity')
    ->latestOccurred()
    ->cursorPaginate(20);
```

## Template System

### How Templates Work
1. **Storage**: `{actor} approved {subject} for {amount}`
2. **Entity References**: Stored in `feed_item_entities` with roles
3. **Properties**: Stored in JSON column
4. **Runtime Resolution**: Placeholders replaced when rendering
5. **Caching**: Rendered output cached per viewer (15 min TTL)

### Placeholder Types
- `{actor}`, `{subject}`, `{target}`, `{mentioned}`, `{related}` - Entity roles
- `{any_property}` - Any key from the properties JSON

### Dynamic Resolution
- If entity name changes, feed automatically shows new name
- If viewer is the actor, shows "You" instead of their name
- Custom display names via `getFeedDisplayName()` method

## Configuration

### Key Settings (`config/feed.php`)
- `cache_ttl`: How long to cache rendered descriptions (default: 900s)
- `retention_days`: How long to keep feed items (default: 90 days, configurable)
- `per_page`: Default pagination size
- `eager_load`: Relations to always load
- `actions`: Predefined action types

## Performance Strategy

### Indexing
- Composite index: (occurred_at, action) for time-based queries
- Composite index: (entity_type, entity_id, role) for entity lookups
- Composite index: (feed_item_id, role) for relationship queries

### Caching
- Rendered descriptions cached per viewer
- Cache key: `feed_item:{id}:rendered:{viewer_id}`
- Auto-invalidation on feed item update/delete
- Configurable TTL (default: 15 minutes)

### Query Optimization
- Always eager load entities to prevent N+1
- Use cursor pagination for large datasets
- Scope methods for efficient filtering
- Support for read replicas (configure in Laravel)

### Scalability
- Current design: Medium traffic (1K-100K items/day)
- For higher volume: Consider partitioning by date
- Optional archival strategy for old data
- Cleanup command for retention management

## Use Cases

### 1. Purchase Order Tracking
Track approvals, declines, edits, status changes

### 2. Social Media Feed
Posts, comments, likes, mentions, shares

### 3. E-commerce Orders
Order placed, confirmed, shipped, delivered, support tickets

### 4. Project Management
Task creation, assignment, status changes, completion

### 5. System Audit Logs
User role changes, configuration updates, system events

## File Structure

```
laravel-activity-feed/
├── src/
│   ├── Models/
│   │   ├── FeedItem.php              # Main feed item model
│   │   └── FeedItemEntity.php        # Entity relationship model
│   ├── Builders/
│   │   └── FeedItemBuilder.php       # Fluent API builder
│   ├── Renderers/
│   │   └── DescriptionRenderer.php   # Dynamic description rendering
│   ├── Traits/
│   │   └── HasFeed.php               # Trait for models
│   ├── Commands/
│   │   └── CleanupFeedItemsCommand.php # Cleanup old items
│   ├── ActivityFeedServiceProvider.php
│   └── helpers.php                    # Helper functions
├── database/migrations/
│   ├── create_feed_items_table.php.stub
│   ├── create_feed_item_entities_table.php.stub
│   └── create_feed_subscriptions_table.php.stub
├── config/
│   └── feed.php                       # Configuration
├── tests/
│   ├── Unit/
│   │   ├── FeedItemBuilderTest.php
│   │   └── DescriptionRendererTest.php
│   └── TestCase.php
├── README.md                          # Main documentation
├── EXAMPLES.md                        # Usage examples
├── CHANGELOG.md                       # Version history
├── CONTRIBUTING.md                    # Contribution guide
├── LICENSE                            # MIT License
├── composer.json                      # Package definition
└── phpunit.xml                        # Test configuration
```

## Installation & Usage

### Install
```bash
composer require yourvendor/laravel-activity-feed
php artisan vendor:publish --tag=feed-config
php artisan vendor:publish --tag=feed-migrations
php artisan migrate
```

### Basic Usage
```php
// Log an activity
feed()
    ->withAction('created')
    ->withTemplate('{actor} created {subject}')
    ->causedBy($user)
    ->performedOn($post)
    ->log();

// Query feed
$feed = FeedItem::forEntity($order)
    ->with('entities.entity')
    ->latestOccurred()
    ->get();

// Render for viewer
$description = $feedItem->renderDescription(auth()->user());
```

## Maintenance

### Cleanup Command
```bash
# Manual cleanup
php artisan feed:cleanup

# With custom retention
php artisan feed:cleanup --days=30

# Dry run
php artisan feed:cleanup --dry-run

# Schedule in Kernel.php
$schedule->command('feed:cleanup')->daily();
```

## Testing
- Unit tests for builder and renderer
- PHPUnit configuration included
- Orchestra Testbench for Laravel testing
- Run: `composer test`

## Future Enhancements (Potential)
- Read/unread tracking per user
- Notifications integration
- Real-time broadcasting support
- Feed grouping/aggregation
- Advanced partitioning for very high volume
- GraphQL API support
- Feed subscription webhooks

## Comparison with Spatie Activity Log

### Similarities
- Both log activities
- Both use fluent API
- Both support polymorphic relationships

### Key Differences

| Feature | This Package | Spatie Activity Log |
|---------|--------------|---------------------|
| Multiple entities per item | ✅ Yes (unlimited) | ❌ Limited (subject + causer) |
| Dynamic "You" resolution | ✅ Yes | ❌ No |
| Live entity name updates | ✅ Yes | ❌ No (stores static text) |
| Template system | ✅ Yes | ❌ Basic description |
| System actions (no actor) | ✅ Yes | ⚠️ Possible but awkward |
| Built for feeds | ✅ Yes | ❌ Built for audit logs |
| Caching layer | ✅ Yes | ❌ No |
| Feed-specific scopes | ✅ Yes | ❌ Basic scopes |

## License
MIT License - Free for personal and commercial use

## Credits
- Inspired by Spatie Laravel Activity Log
- Built for Laravel 10+ and PHP 8.1+
- Designed for modern activity feed requirements
