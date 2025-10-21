# Laravel Activity Feed - Examples

This file contains practical examples for common use cases.

## Purchase Order Management System

Similar to the screenshot you provided, here's how to implement an activity feed for a purchase order system:

### Setup Models

```php
// app/Models/PurchaseOrder.php
use YourVendor\ActivityFeed\Traits\HasFeed;

class PurchaseOrder extends Model
{
    use HasFeed;

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function getFeedDisplayName(): string
    {
        return "PO-{$this->id}";
    }
}

// app/Models/User.php
use YourVendor\ActivityFeed\Traits\HasFeed;

class User extends Model
{
    use HasFeed;

    public function getFeedDisplayName(): string
    {
        return $this->name;
    }
}
```

### Logging Activities

```php
use function YourVendor\ActivityFeed\feed;

// When a purchase order is created
feed()
    ->withAction('created')
    ->withTemplate('{actor} created {subject}')
    ->causedBy($requester)
    ->performedOn($order)
    ->withProperties([
        'balance' => $order->balance,
        'description' => $order->description,
    ])
    ->log();

// When an approval is requested
feed()
    ->withAction('pending')
    ->withTemplate('Approval flow has been restarted for {subject}')
    ->performedOn($order)
    ->withProperties([
        'via' => 'PayEm platform',
    ])
    ->log();

// When someone edits a field
feed()
    ->withAction('updated')
    ->withTemplate('{actor} edited field: {field}')
    ->causedBy($editor)
    ->performedOn($order)
    ->withProperties([
        'field' => 'Amount',
        'old_value' => '$32,848.00',
        'new_value' => '$32,858.00',
    ])
    ->log();

// When someone declines a request
feed()
    ->withAction('declined')
    ->withTemplate('{actor} declined the request')
    ->causedBy($approver)
    ->performedOn($order)
    ->withProperties([
        'reason' => 'The amount of keyboards must be increased by 10 as decided during our last meeting.',
    ])
    ->log();

// When someone approves a request
feed()
    ->withAction('approved')
    ->withTemplate('{actor} approved the request')
    ->causedBy($approver)
    ->performedOn($order)
    ->occurredAt(now()->subMinutes(5))
    ->log();
```

### Displaying the Feed

```php
// In your controller
class PurchaseOrderController extends Controller
{
    public function show($id)
    {
        $order = PurchaseOrder::findOrFail($id);

        $feed = $order->feedItems()
            ->with('entities.entity')
            ->latestOccurred()
            ->get()
            ->map(function ($feedItem) {
                $actor = $feedItem->actor()?->entity;

                return [
                    'action' => $feedItem->action,
                    'description' => $feedItem->renderDescription(auth()->user()),
                    'actor' => $actor ? [
                        'name' => $actor->name,
                        'avatar' => $actor->avatar_url,
                    ] : null,
                    'occurred_at' => $feedItem->occurred_at,
                    'properties' => $feedItem->properties,
                ];
            });

        return view('purchase-orders.show', compact('order', 'feed'));
    }
}
```

### Blade Template

```blade
<!-- resources/views/purchase-orders/show.blade.php -->
<div class="activity-feed">
    <h3>Activity</h3>

    @foreach($feed as $item)
        <div class="feed-item feed-item--{{ $item['action'] }}">
            @if($item['actor'])
                <img src="{{ $item['actor']['avatar'] }}" alt="{{ $item['actor']['name'] }}" class="avatar">
            @endif

            <div class="feed-item__content">
                <p class="feed-item__description">{{ $item['description'] }}</p>

                @if(isset($item['properties']['reason']))
                    <p class="feed-item__reason">{{ $item['properties']['reason'] }}</p>
                @endif

                <span class="feed-item__status badge badge--{{ $item['action'] }}">
                    {{ ucfirst($item['action']) }}
                </span>

                <time class="feed-item__time">
                    {{ $item['occurred_at']->format('M j, Y \a\t h:i A') }}
                </time>
            </div>
        </div>
    @endforeach
</div>
```

## Social Media Post System

### Creating Post Activities

```php
// When a post is created
feed()
    ->withAction('created')
    ->withTemplate('{actor} created a new post')
    ->causedBy($user)
    ->performedOn($post)
    ->log();

// When someone comments
feed()
    ->withAction('commented')
    ->withTemplate('{actor} commented on {subject}')
    ->causedBy($commenter)
    ->performedOn($post)
    ->relatedTo($comment)
    ->withProperty('preview', Str::limit($comment->body, 50))
    ->log();

// When someone likes
feed()
    ->withAction('liked')
    ->withTemplate('{actor} liked {subject}')
    ->causedBy($user)
    ->performedOn($post)
    ->log();

// When someone is mentioned
feed()
    ->withAction('mentioned')
    ->withTemplate('{actor} mentioned {mentioned} in a post')
    ->causedBy($author)
    ->performedOn($post)
    ->mentioning($mentionedUser)
    ->log();
```

### User's Personalized Feed

```php
class FeedController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $following = $user->following()->pluck('id');

        // Get activities from people the user follows
        $feed = FeedItem::query()
            ->whereHas('entities', function($q) use ($following) {
                $q->where('role', 'actor')
                  ->where('entity_type', User::class)
                  ->whereIn('entity_id', $following);
            })
            ->orWhere(function($q) use ($user) {
                // Or activities where user is mentioned
                $q->whereHas('entities', function($query) use ($user) {
                    $query->where('role', 'mentioned')
                          ->where('entity_type', User::class)
                          ->where('entity_id', $user->id);
                });
            })
            ->with('entities.entity')
            ->latestOccurred()
            ->cursorPaginate(20);

        return response()->json($feed);
    }
}
```

## E-commerce Order Tracking

### Order Lifecycle Activities

```php
// Order placed
feed()
    ->withAction('created')
    ->withTemplate('{actor} placed {subject} for {total}')
    ->causedBy($customer)
    ->performedOn($order)
    ->withProperties([
        'total' => $order->total_formatted,
        'items_count' => $order->items->count(),
    ])
    ->log();

// Order confirmed
feed()
    ->withAction('confirmed')
    ->withTemplate('Order {subject} has been confirmed')
    ->performedOn($order)
    ->withProperty('confirmation_number', $order->confirmation_number)
    ->log();

// Order shipped
feed()
    ->withAction('shipped')
    ->withTemplate('{subject} has been shipped via {carrier}')
    ->performedOn($order)
    ->withProperties([
        'carrier' => 'UPS',
        'tracking_number' => $trackingNumber,
    ])
    ->log();

// Order delivered
feed()
    ->withAction('delivered')
    ->withTemplate('{subject} was delivered to {actor}')
    ->causedBy($customer)
    ->performedOn($order)
    ->occurredAt($deliveryTime)
    ->log();

// Customer support interaction
feed()
    ->withAction('support.contacted')
    ->withTemplate('{actor} contacted support about {subject}')
    ->causedBy($customer)
    ->performedOn($order)
    ->relatedTo($supportTicket)
    ->withProperty('issue', 'Damaged item')
    ->log();
```

### Customer Order History

```php
class OrderHistoryController extends Controller
{
    public function show($orderId)
    {
        $order = Order::findOrFail($orderId);

        $timeline = $order->feedItems()
            ->with('entities.entity')
            ->latestOccurred()
            ->get()
            ->map(function($item) {
                return [
                    'status' => $item->action,
                    'description' => $item->renderDescription(auth()->user()),
                    'timestamp' => $item->occurred_at,
                    'details' => $item->properties,
                ];
            });

        return view('orders.history', compact('order', 'timeline'));
    }
}
```

## Project Management System

### Task Activities

```php
// Task created
feed()
    ->withAction('created')
    ->withTemplate('{actor} created task {subject} in {project}')
    ->causedBy($user)
    ->performedOn($task)
    ->relatedTo($project)
    ->log();

// Task assigned
feed()
    ->withAction('assigned')
    ->withTemplate('{actor} assigned {subject} to {target}')
    ->causedBy($manager)
    ->performedOn($task)
    ->targeting($assignee)
    ->log();

// Task status changed
feed()
    ->withAction('status_changed')
    ->withTemplate('{actor} moved {subject} to {new_status}')
    ->causedBy($user)
    ->performedOn($task)
    ->withProperties([
        'old_status' => 'In Progress',
        'new_status' => 'Review',
    ])
    ->log();

// Task completed
feed()
    ->withAction('completed')
    ->withTemplate('{actor} completed {subject}')
    ->causedBy($assignee)
    ->performedOn($task)
    ->log();
```

### Project Activity Dashboard

```php
class ProjectDashboardController extends Controller
{
    public function activities($projectId)
    {
        $project = Project::findOrFail($projectId);

        $activities = FeedItem::query()
            ->whereHas('entities', function($q) use ($project) {
                // Activities on the project itself
                $q->where('entity_type', Project::class)
                  ->where('entity_id', $project->id);
            })
            ->orWhereHas('entities', function($q) use ($project) {
                // Or activities on project's tasks
                $taskIds = $project->tasks()->pluck('id');
                $q->where('entity_type', Task::class)
                  ->whereIn('entity_id', $taskIds);
            })
            ->with('entities.entity')
            ->inPeriod(now()->subDays(30), now())
            ->latestOccurred()
            ->get();

        return response()->json($activities);
    }
}
```

## System Audit Log

### Tracking System Changes

```php
// User role changed by admin
feed()
    ->withAction('role_changed')
    ->withTemplate('{actor} changed {target} role from {old_role} to {new_role}')
    ->causedBy($admin)
    ->targeting($user)
    ->withProperties([
        'old_role' => 'user',
        'new_role' => 'moderator',
    ])
    ->log();

// System configuration changed
feed()
    ->withAction('config_updated')
    ->withTemplate('System configuration {config_key} was updated')
    ->withProperties([
        'config_key' => 'maintenance_mode',
        'old_value' => 'disabled',
        'new_value' => 'enabled',
        'changed_by_ip' => request()->ip(),
    ])
    ->log();

// Automated backup
feed()
    ->withAction('system.backup')
    ->withTemplate('Automated backup completed for {target}')
    ->targeting($database)
    ->withProperties([
        'size' => '2.5 GB',
        'duration' => '45 seconds',
    ])
    ->log();
```

### Admin Audit View

```php
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = FeedItem::query()
            ->with('entities.entity');

        // Filter by action type
        if ($request->has('action')) {
            $query->ofAction($request->action);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->inPeriod($request->start_date, $request->end_date);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $user = User::find($request->user_id);
            $query->forEntity($user, 'actor');
        }

        $logs = $query->latestOccurred()
            ->paginate(50);

        return view('admin.audit-log', compact('logs'));
    }
}
```

## Tips for Large Scale Applications

### Partitioning Strategy

For very high volume applications, consider partitioning the feed_items table by date:

```sql
-- Example partitioning by month
ALTER TABLE feed_items PARTITION BY RANGE (YEAR(occurred_at) * 100 + MONTH(occurred_at)) (
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    -- Add more partitions as needed
);
```

### Archiving Old Data

```php
// Custom archival command
class ArchiveFeedItems extends Command
{
    public function handle()
    {
        $cutoff = now()->subYear();

        FeedItem::where('occurred_at', '<', $cutoff)
            ->chunkById(1000, function($items) {
                // Move to archive table or export to S3
                foreach ($items as $item) {
                    ArchivedFeedItem::create($item->toArray());
                    $item->delete();
                }
            });
    }
}
```

### Caching Strategies

```php
// Cache entire feed for frequently accessed entities
Cache::remember("feed:order:{$order->id}", 600, function() use ($order) {
    return $order->feedItems()
        ->with('entities.entity')
        ->latestOccurred()
        ->get();
});

// Invalidate cache when new feed item is added
$order->createFeedItem()
    ->withAction('updated')
    ->withTemplate('{actor} updated the order')
    ->causedBy($user)
    ->log();

Cache::forget("feed:order:{$order->id}");
```
