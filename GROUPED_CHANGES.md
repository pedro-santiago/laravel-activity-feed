# Grouped Changes - Track Multiple Field Updates

When you edit multiple fields at once, you can group them into a single feed item with detailed change tracking.

## Quick Example

```php
// User edits multiple fields on an order
feed()
    ->withAction('updated')
    ->withTemplate('{actor} {changes_summary} on {subject}')
    ->causedBy($user)
    ->performedOn($order)
    ->withChange('status', 'pending', 'approved')
    ->withChange('amount', '$32,848.00', '$32,858.00')
    ->withChange('shipping_method', 'standard', 'express')
    ->log();

// Renders as: "John Doe updated 3 fields on Order #123"
```

## Methods for Tracking Changes

### 1. Manual Change Tracking

```php
feed()
    ->withAction('updated')
    ->withTemplate('{actor} updated {subject}')
    ->causedBy($user)
    ->performedOn($order)
    ->withChange('field_name', $oldValue, $newValue)
    ->log();
```

### 2. Multiple Changes at Once

```php
feed()
    ->withAction('updated')
    ->withTemplate('{actor} updated {subject}')
    ->causedBy($user)
    ->performedOn($order)
    ->withChanges([
        'status' => ['old' => 'pending', 'new' => 'approved'],
        'amount' => ['old' => '$500', 'new' => '$550'],
        'priority' => ['old' => 'normal', 'new' => 'high'],
    ])
    ->log();
```

### 3. Automatic Detection from Model (Best!)

```php
// In your controller
public function update(Request $request, Order $order)
{
    $order->update($request->validated());

    // Automatically detect all changed fields
    feed()
        ->withAction('updated')
        ->withTemplate('{actor} {changes_summary} on {subject}')
        ->causedBy(auth()->user())
        ->performedOn($order)
        ->withModelChanges($order)  // ← Magic happens here!
        ->log();

    return back()->with('success', 'Order updated');
}
```

## Real-World Examples

### Example 1: Order Update with Multiple Fields

```php
class OrderController extends Controller
{
    public function update(Request $request, Order $order)
    {
        // Store old values if you want custom tracking
        $oldStatus = $order->status;
        $oldAmount = $order->amount;
        $oldPriority = $order->priority;

        // Update the order
        $order->update([
            'status' => $request->status,
            'amount' => $request->amount,
            'priority' => $request->priority,
            'notes' => $request->notes,
        ]);

        // Log with automatic change detection
        feed()
            ->withAction('updated')
            ->withTemplate('{actor} {changes_summary} on {subject}')
            ->causedBy(auth()->user())
            ->performedOn($order)
            ->withModelChanges($order)
            ->log();

        return redirect()->route('orders.show', $order);
    }
}
```

### Example 2: User Profile Update

```php
public function updateProfile(Request $request)
{
    $user = auth()->user();

    $user->update($request->only([
        'name',
        'email',
        'phone',
        'address',
        'timezone',
    ]));

    feed()
        ->withAction('profile_updated')
        ->withTemplate('{actor} updated their profile')
        ->causedBy($user)
        ->performedOn($user)
        ->withModelChanges($user)
        ->log();

    return back()->with('success', 'Profile updated');
}
```

### Example 3: Product Price & Inventory Update

```php
public function updateProduct(Request $request, Product $product)
{
    $product->update([
        'price' => $request->price,
        'sale_price' => $request->sale_price,
        'stock' => $request->stock,
        'is_available' => $request->is_available,
    ]);

    feed()
        ->withAction('product_updated')
        ->withTemplate('{actor} updated {subject} ({changes_summary})')
        ->causedBy(auth()->user())
        ->performedOn($product)
        ->withModelChanges($product)
        ->log();
}
```

## Displaying Grouped Changes

### Method 1: Show Summary Only

```php
$feedItem = FeedItem::find($id);

// "John Doe updated 3 fields on Order #123"
echo $feedItem->renderDescription(auth()->user());
```

### Method 2: Show Detailed Changes

```php
$feedItem = FeedItem::find($id);

if ($feedItem->hasChanges()) {
    echo $feedItem->renderDescription(auth()->user());
    echo "\n\nChanges:\n";

    foreach ($feedItem->formatChanges() as $change) {
        echo "• {$change}\n";
    }
}

// Output:
// John Doe updated 3 fields on Order #123
//
// Changes:
// • Status: pending → approved
// • Amount: $32,848.00 → $32,858.00
// • Shipping method: standard → express
```

### Method 3: Show in Blade Template

```blade
<div class="feed-item">
    <p class="feed-item__description">
        {{ $feedItem->renderDescription(auth()->user()) }}
    </p>

    @if($feedItem->hasChanges())
        <div class="feed-item__changes">
            <strong>Changes ({{ $feedItem->getChangesCount() }}):</strong>
            <ul>
                @foreach($feedItem->formatChanges() as $change)
                    <li>{{ $change }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <time>{{ $feedItem->occurred_at->diffForHumans() }}</time>
</div>
```

### Method 4: Expandable Changes (Like Your Screenshot)

```blade
<div class="feed-item" x-data="{ expanded: false }">
    <div class="feed-item__header">
        <p>{{ $feedItem->renderDescription(auth()->user()) }}</p>

        @if($feedItem->hasChanges() && $feedItem->getChangesCount() > 0)
            <button @click="expanded = !expanded" class="btn-expand">
                <span x-show="!expanded">Show {{ $feedItem->getChangesCount() }} changes</span>
                <span x-show="expanded">Hide changes</span>
            </button>
        @endif
    </div>

    <div x-show="expanded" x-collapse class="feed-item__changes">
        @foreach($feedItem->getChanges() as $change)
            <div class="change-item">
                <span class="change-field">{{ ucfirst(str_replace('_', ' ', $change['field'])) }}</span>
                <div class="change-values">
                    <span class="old-value">{{ $change['old'] }}</span>
                    <span class="arrow">→</span>
                    <span class="new-value">{{ $change['new'] }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
```

## Using Template Placeholders

You can use `{changes_summary}` in your templates:

```php
// Single field changed
feed()
    ->withAction('updated')
    ->withTemplate('{actor} {changes_summary} on {subject}')
    ->causedBy($user)
    ->performedOn($order)
    ->withChange('status', 'pending', 'approved')
    ->log();

// Renders: "John Doe updated Status on Order #123"

// Multiple fields changed
feed()
    ->withAction('updated')
    ->withTemplate('{actor} {changes_summary} on {subject}')
    ->causedBy($user)
    ->performedOn($order)
    ->withModelChanges($order)  // 3 fields changed
    ->log();

// Renders: "John Doe updated 3 fields on Order #123"
```

## Advanced: Custom Change Formatting

### Override in Your Application

```php
// In your AppServiceProvider or custom class
class CustomFeedItem extends FeedItem
{
    protected function formatChangeValue($value): string
    {
        // Custom money formatting
        if (is_numeric($value) && strpos($this->description_template, 'price') !== false) {
            return '$' . number_format($value, 2);
        }

        // Custom date formatting
        if ($value instanceof \Carbon\Carbon) {
            return $value->format('M d, Y');
        }

        return parent::formatChangeValue($value);
    }

    protected function formatFieldName(string $field): string
    {
        // Custom field name mapping
        $fieldNames = [
            'qty' => 'Quantity',
            'amt' => 'Amount',
            'desc' => 'Description',
        ];

        return $fieldNames[$field] ?? parent::formatFieldName($field);
    }
}
```

## API Reference

### FeedItemBuilder Methods

| Method | Description |
|--------|-------------|
| `withChange($field, $old, $new)` | Track a single field change |
| `withChanges(array $changes)` | Track multiple changes at once |
| `withModelChanges(Model $model)` | Auto-detect changes from model |
| `getChanges()` | Get all tracked changes |
| `hasChanges()` | Check if there are any changes |

### FeedItem Methods

| Method | Description |
|--------|-------------|
| `hasChanges()` | Check if item has tracked changes |
| `getChanges()` | Get array of all changes |
| `getChangesCount()` | Get number of changes |
| `getChange($field)` | Get a specific change by field name |
| `formatChanges($includeFieldNames)` | Get formatted change strings |
| `getChangesSummary()` | Get summary like "updated 3 fields" |

## JSON Storage Structure

Changes are stored in the `properties` JSON column:

```json
{
  "changes": [
    {
      "field": "status",
      "old": "pending",
      "new": "approved"
    },
    {
      "field": "amount",
      "old": "$32,848.00",
      "new": "$32,858.00"
    },
    {
      "field": "shipping_method",
      "old": "standard",
      "new": "express"
    }
  ],
  "changes_count": 3,
  "other_property": "value"
}
```

## Querying by Changes

### Find Items with Changes

```php
$itemsWithChanges = FeedItem::whereNotNull('properties->changes')->get();
```

### Find Items that Changed a Specific Field

```php
$statusChanges = FeedItem::where('properties->changes', 'like', '%"field":"status"%')->get();
```

### Find Recent Bulk Updates

```php
$bulkUpdates = FeedItem::where('properties->changes_count', '>=', 3)
    ->inPeriod(now()->subDays(7), now())
    ->get();
```

## Performance Considerations

1. **JSON Querying**: Querying JSON fields can be slower than regular columns. If you need to frequently query specific field changes, consider adding dedicated columns.

2. **Storage**: Changes are stored as JSON. For very large numbers of field changes (50+), consider logging multiple feed items instead.

3. **Indexing**: You can add JSON indexes in MySQL 8.0+:
   ```php
   $table->rawIndex('((cast(properties->"$.changes_count" as unsigned)))', 'changes_count_index');
   ```

## Best Practices

1. **Use `withModelChanges()`**: It's the easiest and most reliable way
2. **Clear Templates**: Use `{changes_summary}` for automatic summaries
3. **Show Details on Demand**: Use expandable UI for change details
4. **Filter Sensitive Fields**: Don't log password changes or API keys
5. **Batch Related Changes**: Group related edits into one feed item

## Example: Complete Implementation

```php
// Controller
public function update(UpdateOrderRequest $request, Order $order)
{
    DB::transaction(function () use ($request, $order) {
        $order->update($request->validated());

        // Only log if something actually changed
        if ($order->wasChanged()) {
            feed()
                ->withAction('updated')
                ->withTemplate('{actor} {changes_summary} on {subject}')
                ->causedBy(auth()->user())
                ->performedOn($order)
                ->withModelChanges($order)
                ->log();
        }
    });

    return redirect()
        ->route('orders.show', $order)
        ->with('success', 'Order updated successfully');
}

// View (resources/views/orders/feed.blade.php)
@foreach($feed as $item)
    <div class="feed-item" x-data="{ showChanges: false }">
        <div class="feed-item__content">
            <p>{{ $item->renderDescription(auth()->user()) }}</p>

            @if($item->hasChanges())
                <button
                    @click="showChanges = !showChanges"
                    class="text-sm text-blue-600"
                >
                    <span x-show="!showChanges">
                        Show {{ $item->getChangesCount() }} changes
                    </span>
                    <span x-show="showChanges">Hide changes</span>
                </button>

                <div x-show="showChanges" class="mt-2 space-y-1">
                    @foreach($item->formatChanges() as $change)
                        <div class="text-sm text-gray-600">
                            {{ $change }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <time class="text-xs text-gray-500">
            {{ $item->occurred_at->diffForHumans() }}
        </time>
    </div>
@endforeach
```
