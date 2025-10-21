# Quick Start Guide - Laravel Activity Feed

Get up and running with Laravel Activity Feed in 5 minutes.

## Installation (1 minute)

```bash
# Install the package
composer require pedro-santiago/laravel-activity-feed

# Publish config and migrations
php artisan vendor:publish --tag=feed-config
php artisan vendor:publish --tag=feed-migrations

# Run migrations
php artisan migrate
```

## Basic Setup (2 minutes)

### 1. Add HasFeed Trait to Your Models

```php
// app/Models/Order.php
use PedroSantiago\ActivityFeed\Traits\HasFeed;

class Order extends Model
{
    use HasFeed;

    // Optional: Customize display name for feeds
    public function getFeedDisplayName(): string
    {
        return "Order #{$this->id}";
    }
}

// app/Models/User.php
use PedroSantiago\ActivityFeed\Traits\HasFeed;

class User extends Model
{
    use HasFeed;

    public function getFeedDisplayName(): string
    {
        return $this->name;
    }
}
```

## Creating Feed Items (2 minutes)

### Method 1: Using the Helper Function

```php
use function PedroSantiago\ActivityFeed\feed;

// Simple activity
feed()
    ->withAction('created')
    ->withTemplate('{actor} created a new order')
    ->causedBy(auth()->user())
    ->performedOn($order)
    ->log();

// Activity with multiple entities
feed()
    ->withAction('approved')
    ->withTemplate('{actor} approved {subject} for {amount}')
    ->causedBy($approver)
    ->performedOn($order)
    ->mentioning($requester)
    ->withProperty('amount', '$500')
    ->log();

// System activity (no actor)
feed()
    ->withAction('system.timeout')
    ->withTemplate('Order {subject} expired due to timeout')
    ->performedOn($order)
    ->withProperty('timeout_duration', '24 hours')
    ->log();
```

### Method 2: Using the Trait

```php
// Quick logging
$order->logActivity(
    'updated',
    '{actor} updated the order status',
    ['old_status' => 'pending', 'new_status' => 'approved']
);

// With builder
$order->createFeedItem()
    ->withAction('shipped')
    ->withTemplate('Order {subject} has been shipped')
    ->withProperty('tracking_number', '1Z999AA10123456784')
    ->log();
```

## Displaying Feed Items

### In Your Controller

```php
use PedroSantiago\ActivityFeed\Models\FeedItem;

class OrderController extends Controller
{
    public function show($id)
    {
        $order = Order::findOrFail($id);

        // Get feed items for this order
        $feed = $order->feedItems()
            ->with('entities.entity')  // Prevent N+1 queries
            ->latest('occurred_at')
            ->get()
            ->map(function($item) {
                return [
                    'action' => $item->action,
                    'description' => $item->renderDescription(auth()->user()),
                    'occurred_at' => $item->occurred_at->diffForHumans(),
                    'properties' => $item->properties,
                ];
            });

        return view('orders.show', compact('order', 'feed'));
    }
}
```

### In Your Blade View

```blade
<!-- resources/views/orders/show.blade.php -->
<div class="activity-feed">
    <h3>Activity</h3>

    @forelse($feed as $item)
        <div class="feed-item">
            <div class="feed-item__icon feed-item__icon--{{ $item['action'] }}">
                <!-- Icon based on action -->
            </div>

            <div class="feed-item__content">
                <p>{{ $item['description'] }}</p>
                <span class="badge badge--{{ $item['action'] }}">
                    {{ ucfirst($item['action']) }}
                </span>
                <time>{{ $item['occurred_at'] }}</time>
            </div>
        </div>
    @empty
        <p>No activity yet.</p>
    @endforelse
</div>
```

## Common Patterns

### Pattern 1: Order Status Changes

```php
// In your OrderController@updateStatus
public function updateStatus(Request $request, Order $order)
{
    $oldStatus = $order->status;
    $order->update(['status' => $request->status]);

    feed()
        ->withAction('status_changed')
        ->withTemplate('{actor} changed {subject} status to {new_status}')
        ->causedBy(auth()->user())
        ->performedOn($order)
        ->withProperties([
            'old_status' => $oldStatus,
            'new_status' => $request->status,
        ])
        ->log();

    return back()->with('success', 'Status updated');
}
```

### Pattern 2: Approval Workflow

```php
// Approval
public function approve(Order $order)
{
    $order->update(['status' => 'approved']);

    feed()
        ->withAction('approved')
        ->withTemplate('{actor} approved {subject}')
        ->causedBy(auth()->user())
        ->performedOn($order)
        ->log();
}

// Decline with reason
public function decline(Request $request, Order $order)
{
    $order->update(['status' => 'declined']);

    feed()
        ->withAction('declined')
        ->withTemplate('{actor} declined {subject}')
        ->causedBy(auth()->user())
        ->performedOn($order)
        ->withProperty('reason', $request->reason)
        ->log();
}
```

### Pattern 3: Commenting/Mentions

```php
public function addComment(Request $request, Post $post)
{
    $comment = $post->comments()->create([
        'user_id' => auth()->id(),
        'body' => $request->body,
    ]);

    $builder = feed()
        ->withAction('commented')
        ->withTemplate('{actor} commented on {subject}')
        ->causedBy(auth()->user())
        ->performedOn($post)
        ->relatedTo($comment);

    // Extract mentions from comment
    preg_match_all('/@(\w+)/', $request->body, $matches);
    foreach ($matches[1] as $username) {
        $user = User::where('username', $username)->first();
        if ($user) {
            $builder->mentioning($user);
        }
    }

    $builder->log();
}
```

## Querying Feeds

### Get Feed for a Specific Entity

```php
// All activities for an order
$feed = FeedItem::forEntity($order)
    ->with('entities.entity')
    ->latestOccurred()
    ->get();

// Only activities where order is the subject
$feed = FeedItem::forEntity($order, 'subject')
    ->with('entities.entity')
    ->get();
```

### Get Feed for Multiple Entities

```php
// All activities for user's orders
$userOrders = auth()->user()->orders;
$feed = FeedItem::forEntities($userOrders, 'subject')
    ->with('entities.entity')
    ->latestOccurred()
    ->cursorPaginate(20);
```

### Filter by Action

```php
// Only approvals and declines
$feed = FeedItem::ofAction(['approved', 'declined'])
    ->with('entities.entity')
    ->get();
```

### Filter by Date

```php
// Last 7 days
$feed = FeedItem::inPeriod(now()->subDays(7), now())
    ->with('entities.entity')
    ->get();
```

### Complex Queries

```php
// User's personalized feed
$feed = FeedItem::query()
    ->where(function($q) use ($user) {
        // Activities on user's orders
        $q->forEntities($user->orders, 'subject');
    })
    ->orWhere(function($q) use ($user) {
        // Or user was mentioned
        $q->forEntity($user, 'mentioned');
    })
    ->ofAction(['created', 'updated', 'approved', 'declined'])
    ->inPeriod(now()->subMonth(), now())
    ->with('entities.entity')
    ->latestOccurred()
    ->cursorPaginate(20);
```

## Configuration

Edit `config/feed.php` to customize:

```php
return [
    // How long to cache rendered descriptions (seconds)
    'cache_ttl' => 900, // 15 minutes

    // How long to keep feed items (days)
    'retention_days' => 90,

    // Default pagination size
    'per_page' => 20,

    // Predefined actions
    'actions' => [
        'created', 'updated', 'deleted',
        'approved', 'declined', 'pending',
        // Add your custom actions
        'shipped', 'delivered', 'cancelled',
    ],
];
```

## Maintenance

### Cleanup Old Feed Items

```bash
# Use configured retention period
php artisan feed:cleanup

# Override retention period (keep last 30 days)
php artisan feed:cleanup --days=30

# Dry run (see what would be deleted)
php artisan feed:cleanup --dry-run
```

### Schedule Cleanup

In `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('feed:cleanup')->daily();
}
```

## Tips for Success

### 1. Always Eager Load
```php
// ✅ Good - No N+1 queries
$feed = FeedItem::forEntity($order)
    ->with('entities.entity')
    ->get();

// ❌ Bad - N+1 queries
$feed = FeedItem::forEntity($order)->get();
```

### 2. Use Cursor Pagination for Large Feeds
```php
// ✅ Good for large datasets
$feed = FeedItem::query()
    ->latestOccurred()
    ->cursorPaginate(20);

// ❌ Less efficient for large datasets
$feed = FeedItem::query()
    ->latestOccurred()
    ->paginate(20);
```

### 3. Customize Display Names
```php
class Order extends Model
{
    use HasFeed;

    public function getFeedDisplayName(): string
    {
        return "Order #{$this->order_number}";
    }
}
```

### 4. Use Descriptive Templates
```php
// ✅ Good - Clear and informative
->withTemplate('{actor} approved {subject} for {amount}')

// ❌ Bad - Too vague
->withTemplate('{actor} did something')
```

### 5. Store Useful Properties
```php
// Store contextual information
->withProperties([
    'old_value' => $oldStatus,
    'new_value' => $newStatus,
    'changed_field' => 'status',
    'ip_address' => request()->ip(),
])
```

## Next Steps

- Read the full [README.md](README.md) for comprehensive documentation
- Check [EXAMPLES.md](EXAMPLES.md) for real-world use cases
- Review [ARCHITECTURE.md](ARCHITECTURE.md) to understand the internals
- Look at [tests/](tests/) for more usage examples

## Common Issues

### Issue: N+1 Query Problems
**Solution:** Always use `->with('entities.entity')`

### Issue: Slow Queries
**Solution:** Make sure migrations ran to create indexes

### Issue: Cache Not Working
**Solution:** Check your cache driver is configured in `.env`

### Issue: "You" Not Showing
**Solution:** Pass the viewer to `renderDescription(auth()->user())`

## Support

- Issues: GitHub Issues
- Documentation: README.md
- Examples: EXAMPLES.md
- Architecture: ARCHITECTURE.md

That's it! You're ready to build awesome activity feeds with Laravel! 🎉
