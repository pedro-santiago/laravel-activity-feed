# Laravel Activity Feed

A robust Laravel package for creating activity feeds with dynamic entity resolution and multiple relationships. Perfect for building Facebook-style feeds, activity timelines, and audit logs.

## Features

- **Dynamic Entity Resolution**: Entity names automatically update when the underlying model changes (e.g., user renames)
- **Contextual Rendering**: Automatically renders "You" when viewing your own activities
- **Multiple Entities Per Feed Item**: Support for actor, subject, target, mentioned, and custom roles
- **Grouped Field Changes**: Track multiple field edits in a single feed item with detailed change history
- **Flexible Relationships**: Query feeds by any entity or combination of entities
- **Performance Optimized**:
  - Composite database indexes for fast queries
  - Built-in caching for rendered descriptions
  - Eager loading to prevent N+1 queries
  - Cursor pagination support
- **Configurable Retention**: Auto-cleanup old feed items
- **System Actions**: Support for activities without an actor (system-generated events)
- **Fluent API**: Intuitive, chainable builder pattern

## Installation

Install via Composer:

```bash
composer require yourvendor/laravel-activity-feed
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=feed-config
php artisan vendor:publish --tag=feed-migrations
```

Run the migrations:

```bash
php artisan migrate
```

## Quick Start

### Basic Usage

```php
use function YourVendor\ActivityFeed\feed;

// Log a simple activity
feed()
    ->withAction('created')
    ->withTemplate('{actor} created a new post')
    ->causedBy($user)
    ->performedOn($post)
    ->log();
```

### Multiple Entities

```php
feed()
    ->withAction('approved')
    ->withTemplate('{actor} approved {subject} for {amount}')
    ->causedBy($approver)
    ->performedOn($order)
    ->mentioning($requester)
    ->withProperties(['amount' => '$500'])
    ->log();
```

### System Actions (No Actor)

```php
feed()
    ->withAction('system.restart')
    ->withTemplate('Approval flow restarted for {subject}')
    ->performedOn($order)
    ->withProperties(['reason' => 'Timeout'])
    ->log();
```

### Grouped Field Changes

Track multiple field edits in a single feed item:

```php
// Automatic detection from model changes
$order->update($request->validated());

feed()
    ->withAction('updated')
    ->withTemplate('{actor} {changes_summary} on {subject}')
    ->causedBy(auth()->user())
    ->performedOn($order)
    ->withModelChanges($order)  // Automatically tracks all changed fields
    ->log();

// Renders: "John Doe updated 3 fields on Order #123"
// With expandable details showing each field change
```

See [GROUPED_CHANGES.md](GROUPED_CHANGES.md) for detailed documentation.

## Using the HasFeed Trait

Add the `HasFeed` trait to models that should have activity feeds:

```php
use YourVendor\ActivityFeed\Traits\HasFeed;

class Order extends Model
{
    use HasFeed;
}
```

### Create Feed Items from Models

```php
// Using the trait
$order->logActivity(
    'updated',
    '{actor} updated the order status to {status}',
    ['status' => 'shipped']
);

// Or with the builder
$order->createFeedItem()
    ->withAction('updated')
    ->withTemplate('{actor} updated the order')
    ->causedBy($user)
    ->log();
```

### Query Feed Items

```php
// Get all feed items for an order
$feedItems = $order->feedItems()
    ->with('entities.entity')
    ->latest('occurred_at')
    ->get();

// Get feed items where the order is the subject
$subjectItems = $order->feedItemsAsSubject()->get();

// Get feed items where the user is the actor
$actorItems = $user->feedItemsAsActor()->get();
```

## Querying Feeds

### Filter by Entity

```php
use YourVendor\ActivityFeed\Models\FeedItem;

// Get feed for a specific order
$feed = FeedItem::forEntity($order, 'subject')
    ->with('entities.entity')
    ->latestOccurred()
    ->get();
```

### Filter by Multiple Entities

```php
// Get feed for all user's orders
$feed = FeedItem::forEntities($user->orders, 'subject')
    ->latestOccurred()
    ->cursorPaginate(20);
```

### Filter by Action

```php
// Single action
$approvals = FeedItem::ofAction('approved')->get();

// Multiple actions
$changes = FeedItem::ofAction(['created', 'updated', 'deleted'])->get();
```

### Filter by Date Range

```php
$recentFeed = FeedItem::inPeriod(
    now()->subDays(7),
    now()
)->get();
```

### Complex Queries

```php
$feed = FeedItem::query()
    ->forEntity($user, 'actor') // User did something
    ->orWhereHas('entities', function($q) use ($user) {
        $q->where('role', 'mentioned')
          ->where('entity_type', User::class)
          ->where('entity_id', $user->id);
    }) // OR user was mentioned
    ->ofAction(['approved', 'declined'])
    ->inPeriod(now()->subMonth(), now())
    ->with('entities.entity')
    ->latestOccurred()
    ->cursorPaginate(20);
```

## Rendering Feed Descriptions

Feed items use templates with placeholders that are resolved dynamically:

```php
$feedItem = feed()
    ->withAction('approved')
    ->withTemplate('{actor} approved {subject} for {amount}')
    ->causedBy($john)
    ->performedOn($order)
    ->withProperty('amount', '$500')
    ->log();

// Render for a viewer
echo $feedItem->renderDescription($currentUser);
// Output: "John Doe approved Order #123 for $500"

// Render for the actor themselves
echo $feedItem->renderDescription($john);
// Output: "You approved Order #123 for $500"
```

### Template Placeholders

- `{actor}` - The user/entity who performed the action
- `{subject}` - The primary entity being acted upon
- `{target}` - Additional target entity
- `{mentioned}` - Mentioned entity
- `{related}` - Related entity
- `{any_property_key}` - Any property from the properties array

### Custom Display Names

Override `getFeedDisplayName()` in your models:

```php
class User extends Model
{
    use HasFeed;

    public function getFeedDisplayName(): string
    {
        return $this->full_name;
    }
}
```

## Builder API Reference

### Actions

```php
->withAction(string $action)
```

### Templates

```php
->withTemplate(string $template)
->withDescription(string $template) // Alias
```

### Entities

```php
->causedBy(?Model $actor)           // Who did it
->by(?Model $actor)                 // Alias

->performedOn(?Model $subject)      // What was acted upon
->on(?Model $subject)               // Alias

->targeting(?Model $target)         // Target entity
->mentioning(?Model $mentioned)     // Mentioned entity
->relatedTo(?Model $related)        // Related entity

->addEntity(?Model $entity, string $role)  // Custom role
->addEntities(array $entities, string $role) // Multiple with same role
```

### Properties

```php
->withProperties(array $properties)
->withProperty(string $key, $value)
```

### Timing

```php
->occurredAt(Carbon|string $timestamp)
```

### Execution

```php
->log()  // Create and save the feed item
```

## Model Scopes

Available scopes on `FeedItem`:

```php
->ofAction(string|array $action)
->inPeriod($startDate, $endDate)
->forEntity(Model $entity, ?string $role = null)
->forEntities($entities, ?string $role = null)
->latestOccurred()
```

## Configuration

Edit `config/feed.php`:

```php
return [
    // Cache TTL for rendered descriptions (seconds)
    'cache_ttl' => 900, // 15 minutes

    // Retention period (days) - null for indefinite
    'retention_days' => 90,

    // Auto cleanup old items
    'auto_cleanup' => false,

    // Default pagination
    'per_page' => 20,

    // Always eager load these relationships
    'eager_load' => [
        'entities.entity',
    ],

    // Predefined actions
    'actions' => [
        'created', 'updated', 'deleted',
        'approved', 'declined', 'pending',
        'completed', 'cancelled', 'restored',
    ],
];
```

## Cleanup Old Feed Items

Run manually:

```bash
# Use configured retention period
php artisan feed:cleanup

# Override retention period
php artisan feed:cleanup --days=30

# Dry run to see what would be deleted
php artisan feed:cleanup --dry-run
```

Schedule in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('feed:cleanup')->daily();
}
```

## Database Schema

### feed_items

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| action | varchar(50) | Action type |
| description_template | text | Template with placeholders |
| properties | json | Additional metadata |
| occurred_at | timestamp | When the action happened |
| created_at | timestamp | Record creation time |
| updated_at | timestamp | Record update time |

**Indexes:**
- `action`
- `occurred_at`
- Composite: `(occurred_at, action)`

### feed_item_entities

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| feed_item_id | bigint | Foreign key to feed_items |
| entity_type | varchar | Polymorphic type |
| entity_id | bigint | Polymorphic ID |
| role | varchar(50) | Entity role (actor, subject, etc.) |
| created_at | timestamp | Record creation time |

**Indexes:**
- Composite: `(entity_type, entity_id, role)`
- Composite: `(feed_item_id, role)`

## Performance Tips

1. **Always Eager Load**: Use `with('entities.entity')` to prevent N+1 queries
2. **Use Cursor Pagination**: For large datasets, use `cursorPaginate()` instead of `paginate()`
3. **Cache Rendered Descriptions**: Enabled by default with 15-minute TTL
4. **Index Custom Queries**: Add database indexes for frequently queried columns
5. **Cleanup Old Data**: Regularly run `feed:cleanup` to maintain performance

## Example: Building a User Feed

```php
use YourVendor\ActivityFeed\Models\FeedItem;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Get feed for user's entities (orders, posts, etc.)
        $feed = FeedItem::query()
            ->forEntities($user->orders, 'subject')
            ->orForEntities($user->posts, 'subject')
            ->orForEntity($user, 'mentioned')
            ->with('entities.entity')
            ->latestOccurred()
            ->cursorPaginate(20);

        // Transform for display
        $items = $feed->map(function ($feedItem) use ($user) {
            return [
                'id' => $feedItem->id,
                'action' => $feedItem->action,
                'description' => $feedItem->renderDescription($user),
                'occurred_at' => $feedItem->occurred_at->diffForHumans(),
                'properties' => $feedItem->properties,
            ];
        });

        return response()->json($items);
    }
}
```

## Testing

```bash
composer test
```

## License

MIT License

## Contributing

Contributions are welcome! Please submit pull requests or open issues.

## Credits

- Inspired by [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog)
- Built for Laravel 10+ and PHP 8.1+
