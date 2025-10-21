<?php

namespace YourVendor\ActivityFeed;

use Illuminate\Support\ServiceProvider;
use YourVendor\ActivityFeed\Commands\CleanupFeedItemsCommand;

class ActivityFeedServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/feed.php',
            'feed'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/feed.php' => config_path('feed.php'),
        ], 'feed-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/create_feed_items_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_feed_items_table.php'),
            __DIR__ . '/../database/migrations/create_feed_item_entities_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time() + 1) . '_create_feed_item_entities_table.php'),
            __DIR__ . '/../database/migrations/create_feed_subscriptions_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time() + 2) . '_create_feed_subscriptions_table.php'),
        ], 'feed-migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupFeedItemsCommand::class,
            ]);
        }
    }
}
