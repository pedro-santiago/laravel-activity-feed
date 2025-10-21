# Grouped Changes - Quick Reference

## The Problem

When a user edits multiple fields at once (e.g., status, amount, and priority on an order), you want to:
1. Log it as ONE feed item (not 3 separate items)
2. Show a summary: "John updated 3 fields on Order #123"
3. Allow expanding to see details of each change

## The Solution

### Method 1: Automatic Detection (Recommended!)

```php
public function update(Request $request, Order $order)
{
    $order->update($request->validated());

    feed()
        ->withAction('updated')
        ->withTemplate('{actor} {changes_summary} on {subject}')
        ->causedBy(auth()->user())
        ->performedOn($order)
        ->withModelChanges($order)  // ← Magic!
        ->log();
}
```

### Method 2: Manual Tracking

```php
feed()
    ->withAction('updated')
    ->withTemplate('{actor} {changes_summary} on {subject}')
    ->causedBy(auth()->user())
    ->performedOn($order)
    ->withChange('status', 'pending', 'approved')
    ->withChange('amount', '$500', '$550')
    ->withChange('priority', 'normal', 'high')
    ->log();
```

### Method 3: Batch Array

```php
feed()
    ->withAction('updated')
    ->withTemplate('{actor} {changes_summary} on {subject}')
    ->causedBy(auth()->user())
    ->performedOn($order)
    ->withChanges([
        'status' => ['old' => 'pending', 'new' => 'approved'],
        'amount' => ['old' => '$500', 'new' => '$550'],
    ])
    ->log();
```

## Displaying Changes

### In Your Controller

```php
public function show($id)
{
    $order = Order::findOrFail($id);

    $feed = $order->feedItems()
        ->with('entities.entity')
        ->latest('occurred_at')
        ->get();

    return view('orders.show', compact('order', 'feed'));
}
```

### In Your Blade View

```blade
@foreach($feed as $item)
    <div class="feed-item">
        <!-- Summary -->
        <p>{{ $item->renderDescription(auth()->user()) }}</p>

        <!-- Expandable details (if changes exist) -->
        @if($item->hasChanges())
            <button onclick="toggleChanges({{ $item->id }})">
                Show {{ $item->getChangesCount() }} changes
            </button>

            <div id="changes-{{ $item->id }}" style="display: none;">
                @foreach($item->formatChanges() as $change)
                    <div>{{ $change }}</div>
                @endforeach
            </div>
        @endif

        <time>{{ $item->occurred_at->diffForHumans() }}</time>
    </div>
@endforeach
```

### Output Example

**Collapsed:**
```
John Doe updated 3 fields on Order #123
[Show 3 changes]
2 hours ago
```

**Expanded:**
```
John Doe updated 3 fields on Order #123
[Hide changes]

• Status: pending → approved
• Amount: $32,848.00 → $32,858.00
• Priority: normal → high

2 hours ago
```

## Template Placeholder

Use `{changes_summary}` in your template:

```php
'{actor} {changes_summary} on {subject}'
```

**Renders as:**
- 1 field changed: "John Doe updated Status on Order #123"
- 3 fields changed: "John Doe updated 3 fields on Order #123"
- 0 fields changed: "John Doe no changes on Order #123"

## API Quick Reference

### Builder Methods
- `withChange($field, $old, $new)` - Track one change
- `withChanges(array)` - Track multiple changes
- `withModelChanges($model)` - Auto-detect from model

### FeedItem Methods
- `hasChanges()` - Check if has changes
- `getChanges()` - Get array of changes
- `getChangesCount()` - Get number of changes
- `formatChanges()` - Get formatted strings
- `getChangesSummary()` - Get "updated 3 fields"

## Data Structure

Changes are stored in the `properties` JSON column:

```json
{
  "changes": [
    {"field": "status", "old": "pending", "new": "approved"},
    {"field": "amount", "old": "$500", "new": "$550"}
  ],
  "changes_count": 2
}
```

## Full Example

```php
// Controller
public function update(UpdateOrderRequest $request, Order $order)
{
    $order->update($request->validated());

    if ($order->wasChanged()) {
        feed()
            ->withAction('updated')
            ->withTemplate('{actor} {changes_summary} on {subject}')
            ->causedBy(auth()->user())
            ->performedOn($order)
            ->withModelChanges($order)
            ->log();
    }

    return back()->with('success', 'Order updated');
}

// Blade View
<div class="feed" x-data>
    @foreach($feed as $item)
        <div class="feed-item" x-data="{ open: false }">
            <p>{{ $item->renderDescription(auth()->user()) }}</p>

            @if($item->hasChanges())
                <button @click="open = !open" class="text-sm text-blue-600">
                    <span x-show="!open">Show {{ $item->getChangesCount() }} changes</span>
                    <span x-show="open">Hide changes</span>
                </button>

                <div x-show="open" class="mt-2">
                    @foreach($item->formatChanges() as $change)
                        <div class="text-sm">• {{ $change }}</div>
                    @endforeach
                </div>
            @endif

            <time class="text-xs text-gray-500">
                {{ $item->occurred_at->diffForHumans() }}
            </time>
        </div>
    @endforeach
</div>
```

## Best Practices

1. ✅ Use `withModelChanges($model)` - easiest and most reliable
2. ✅ Check `$model->wasChanged()` before logging
3. ✅ Use `{changes_summary}` in templates for automatic summaries
4. ✅ Show expandable UI for change details
5. ❌ Don't log password changes or sensitive data
6. ❌ Don't create separate feed items for each field

## See Also

- Full documentation: [GROUPED_CHANGES.md](GROUPED_CHANGES.md)
- Main README: [README.md](README.md)
- Examples: [EXAMPLES.md](EXAMPLES.md)
