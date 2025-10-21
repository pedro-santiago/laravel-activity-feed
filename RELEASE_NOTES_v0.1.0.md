# Release Notes - v0.1.0

**Laravel Activity Feed** - Initial Beta Release

**Package:** `pedro-santiago/laravel-activity-feed`
**Release Date:** January 21, 2025
**Status:** Beta (Ready for testing and feedback)

---

## 🎉 Welcome!

This is the first beta release of Laravel Activity Feed, a robust package for creating activity feeds with dynamic entity resolution and multiple relationships. The package is feature-complete and ready for testing in your Laravel applications.

## 🚀 Installation

```bash
composer require pedro-santiago/laravel-activity-feed:^0.1
```

Full installation guide: [INSTALLATION.md](INSTALLATION.md)

## ✨ Key Features

### 1. Dynamic Entity Resolution
Entity names automatically update when models change - no stale data in your feeds!

```php
// If user "John Doe" changes name to "John Smith"
// ALL feed items automatically show "John Smith"
```

### 2. Contextual "You" Rendering
Personalized feed experience - shows "You" when viewing your own activities.

```php
// When viewing your own activity
"You approved Order #123"

// When viewing someone else's activity
"John Doe approved Order #123"
```

### 3. Multiple Entities Per Feed Item
Track unlimited entities with flexible roles (actor, subject, target, mentioned, related).

```php
feed()
    ->causedBy($approver)      // Actor
    ->performedOn($order)      // Subject
    ->mentioning($requester)   // Mentioned
    ->targeting($vendor)       // Target
    ->log();
```

### 4. Grouped Field Changes ⭐ NEW
Track multiple field edits in a single feed item with automatic detection.

```php
$order->update($request->validated());

feed()
    ->withAction('updated')
    ->withTemplate('{actor} {changes_summary} on {subject}')
    ->causedBy(auth()->user())
    ->performedOn($order)
    ->withModelChanges($order)  // Automatically tracks all changed fields!
    ->log();

// Renders: "You updated 3 fields on Order #123"
// With expandable details showing each field change
```

### 5. Performance Optimized
- Built-in caching (15-minute TTL)
- Composite database indexes for fast queries
- Eager loading support to prevent N+1 queries
- Cursor pagination for large datasets
- Designed for medium-high traffic (1K-100K items/day)

### 6. System Actions Support
Activities can be logged without an actor (system-generated events).

```php
feed()
    ->withAction('system.timeout')
    ->withTemplate('Order {subject} expired due to timeout')
    ->performedOn($order)
    ->log();
```

### 7. Configurable Retention
Auto-cleanup old feed items with scheduled commands.

```bash
php artisan feed:cleanup --days=90
```

## 📦 What's Included

- **Models:** FeedItem, FeedItemEntity
- **Builders:** FeedItemBuilder with fluent API
- **Traits:** HasFeed for your models
- **Renderers:** Dynamic description rendering
- **Commands:** Cleanup command for retention
- **Migrations:** Optimized database schema with indexes
- **Configuration:** Fully customizable via config file
- **Tests:** Comprehensive unit tests
- **Documentation:** Complete guides and examples

## 📚 Documentation

- **README.md** - Complete package documentation
- **INSTALLATION.md** - Step-by-step installation guide
- **QUICKSTART.md** - Get started in 5 minutes
- **EXAMPLES.md** - Real-world use cases
- **GROUPED_CHANGES.md** - Field changes tracking guide
- **ARCHITECTURE.md** - System architecture and design

## 🎯 Use Cases

Perfect for:
- Purchase order tracking systems
- Social media activity feeds
- E-commerce order history
- Project management timelines
- Audit logs and system events
- CRM activity tracking

## 🔧 Requirements

- PHP 8.1, 8.2, or 8.3
- Laravel 10.x or 11.x
- MySQL 5.7+ or PostgreSQL 9.6+ (for JSON support)

## 📖 Quick Example

```php
use PedroSantiago\ActivityFeed\Traits\HasFeed;
use function PedroSantiago\ActivityFeed\feed;

// Add trait to your model
class Order extends Model
{
    use HasFeed;
}

// Log an activity
feed()
    ->withAction('approved')
    ->withTemplate('{actor} approved {subject} for {amount}')
    ->causedBy(auth()->user())
    ->performedOn($order)
    ->withProperty('amount', '$500')
    ->log();

// Query the feed
$feed = $order->feedItems()
    ->with('entities.entity')
    ->latest('occurred_at')
    ->get();

// Display
foreach ($feed as $item) {
    echo $item->renderDescription(auth()->user());
    // Output: "You approved Order #123 for $500"
}
```

## 🐛 Known Issues

None at this time. This is a beta release - please report any issues you encounter!

## 🔮 What's Next

Planned for v0.2.0:
- Read/unread tracking per user
- Notifications integration
- Real-time broadcasting support
- Feed aggregation/grouping options

## 📞 Support & Feedback

We'd love to hear your feedback on this beta release!

- **GitHub Issues:** https://github.com/pedro-santiago/laravel-activity-feed/issues
- **Email:** contato@pedrosantiago.com.br
- **Discussions:** https://github.com/pedro-santiago/laravel-activity-feed/discussions

## 🤝 Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## 📄 License

MIT License - Free for personal and commercial use.

## 🙏 Acknowledgments

- Inspired by Spatie's Laravel Activity Log
- Built for modern Laravel applications
- Created with ❤️ by Pedro Santiago

---

**Enjoy building amazing activity feeds!** 🚀

If you find this package useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting issues
- 💡 Suggesting features
- 🤝 Contributing code
