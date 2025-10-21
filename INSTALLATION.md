# Installation Guide - Laravel Activity Feed

Complete installation and setup instructions for `pedro-santiago/laravel-activity-feed`.

## Requirements

- PHP 8.1, 8.2, or 8.3
- Laravel 10.x or 11.x
- MySQL 5.7+ or PostgreSQL 9.6+ (for JSON support)

## Step 1: Install via Composer

```bash
composer require pedro-santiago/laravel-activity-feed
```

## Step 2: Publish Configuration

Publish the package configuration file:

```bash
php artisan vendor:publish --tag=feed-config
```

This creates `config/feed.php` where you can customize:
- Cache TTL
- Retention days
- Default actions
- Pagination settings

## Step 3: Publish and Run Migrations

Publish the migration files:

```bash
php artisan vendor:publish --tag=feed-migrations
```

This creates three migration files:
- `create_feed_items_table.php`
- `create_feed_item_entities_table.php`
- `create_feed_subscriptions_table.php`

Run the migrations:

```bash
php artisan migrate
```

## Step 4: Add Trait to Your Models

Add the `HasFeed` trait to any models that should have activity feeds:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PedroSantiago\ActivityFeed\Traits\HasFeed;

class Order extends Model
{
    use HasFeed;

    // Optional: Customize the display name for feeds
    public function getFeedDisplayName(): string
    {
        return "Order #{$this->order_number}";
    }
}
```

## Step 5: Start Logging Activities

```php
use function PedroSantiago\ActivityFeed\feed;

// Simple example
feed()
    ->withAction('created')
    ->withTemplate('{actor} created {subject}')
    ->causedBy(auth()->user())
    ->performedOn($order)
    ->log();
```

## Configuration Options

Edit `config/feed.php` to customize the package:

```php
return [
    // How long to cache rendered descriptions (seconds)
    'cache_ttl' => env('FEED_CACHE_TTL', 900),

    // How many days to keep feed items (null = forever)
    'retention_days' => env('FEED_RETENTION_DAYS', 90),

    // Default pagination size
    'per_page' => 20,

    // Predefined actions
    'actions' => [
        'created', 'updated', 'deleted',
        'approved', 'declined', 'pending',
        // Add your custom actions here
    ],

    // Always eager load these relationships
    'eager_load' => [
        'entities.entity',
    ],
];
```

## Environment Variables

Add these to your `.env` file:

```env
# Feed package settings
FEED_CACHE_TTL=900
FEED_RETENTION_DAYS=90
FEED_AUTO_CLEANUP=false
```

## Optional: Schedule Cleanup Command

To automatically clean up old feed items, add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Clean up feed items daily
    $schedule->command('feed:cleanup')->daily();
}
```

## Verify Installation

Test the installation by creating a feed item:

```php
use function PedroSantiago\ActivityFeed\feed;

$feedItem = feed()
    ->withAction('test')
    ->withTemplate('Testing feed installation')
    ->log();

dd($feedItem); // Should show a FeedItem model
```

## Namespace Reference

All package classes are under the `PedroSantiago\ActivityFeed` namespace:

```php
use PedroSantiago\ActivityFeed\Models\FeedItem;
use PedroSantiago\ActivityFeed\Models\FeedItemEntity;
use PedroSantiago\ActivityFeed\Traits\HasFeed;
use PedroSantiago\ActivityFeed\Builders\FeedItemBuilder;
use function PedroSantiago\ActivityFeed\feed;
```

## Troubleshooting

### Issue: "Class 'PedroSantiago\ActivityFeed\ActivityFeedServiceProvider' not found"

**Solution:** Run `composer dump-autoload`

### Issue: Migration files not found

**Solution:**
```bash
php artisan vendor:publish --tag=feed-migrations --force
```

### Issue: Config not loading

**Solution:**
```bash
php artisan config:clear
php artisan cache:clear
```

### Issue: Namespace errors in IDE

**Solution:** Regenerate IDE helper files if using Laravel IDE Helper:
```bash
php artisan ide-helper:generate
```

## Next Steps

- Read the [Quick Start Guide](QUICKSTART.md)
- Check out [Examples](EXAMPLES.md) for real-world use cases
- Learn about [Grouped Changes](GROUPED_CHANGES.md) for tracking multiple field edits
- Review the [Full Documentation](README.md)

## Upgrading

When a new version is released:

```bash
# Update the package
composer update pedro-santiago/laravel-activity-feed

# Publish new migrations if any
php artisan vendor:publish --tag=feed-migrations

# Run new migrations
php artisan migrate

# Clear config cache
php artisan config:clear
```

## Uninstalling

To remove the package:

```bash
# Remove the package
composer remove pedro-santiago/laravel-activity-feed

# Optionally, rollback migrations
php artisan migrate:rollback --step=3

# Remove config file
rm config/feed.php
```

## Support

- GitHub: https://github.com/pedro-santiago/laravel-activity-feed
- Issues: https://github.com/pedro-santiago/laravel-activity-feed/issues
- Author: Pedro Santiago (contato@pedrosantiago.com.br)
