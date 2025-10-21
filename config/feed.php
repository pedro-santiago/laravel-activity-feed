<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feed Items Table
    |--------------------------------------------------------------------------
    |
    | The name of the table that will store feed items.
    |
    */
    'table_name' => 'feed_items',

    /*
    |--------------------------------------------------------------------------
    | Feed Item Entities Table
    |--------------------------------------------------------------------------
    |
    | The name of the table that will store feed item entity relationships.
    |
    */
    'entities_table_name' => 'feed_item_entities',

    /*
    |--------------------------------------------------------------------------
    | Feed Subscriptions Table
    |--------------------------------------------------------------------------
    |
    | The name of the table that will store feed subscriptions.
    |
    */
    'subscriptions_table_name' => 'feed_subscriptions',

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to cache rendered feed item descriptions.
    | Default: 900 seconds (15 minutes)
    |
    */
    'cache_ttl' => env('FEED_CACHE_TTL', 900),

    /*
    |--------------------------------------------------------------------------
    | Retention Days
    |--------------------------------------------------------------------------
    |
    | How many days to keep feed items before they can be cleaned up.
    | Set to null to keep items indefinitely.
    |
    */
    'retention_days' => env('FEED_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Default Actions
    |--------------------------------------------------------------------------
    |
    | Common action types that can be used in your application.
    | You can add custom actions as needed.
    |
    */
    'actions' => [
        'created',
        'updated',
        'deleted',
        'approved',
        'declined',
        'pending',
        'completed',
        'cancelled',
        'restored',
        'archived',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Cleanup
    |--------------------------------------------------------------------------
    |
    | Enable automatic cleanup of old feed items based on retention_days.
    | This requires scheduling the cleanup command.
    |
    */
    'auto_cleanup' => env('FEED_AUTO_CLEANUP', false),

    /*
    |--------------------------------------------------------------------------
    | Default Pagination
    |--------------------------------------------------------------------------
    |
    | Default number of feed items to show per page.
    |
    */
    'per_page' => 20,

    /*
    |--------------------------------------------------------------------------
    | Eager Load Relations
    |--------------------------------------------------------------------------
    |
    | Always eager load these relationships when querying feed items
    | to prevent N+1 query issues.
    |
    */
    'eager_load' => [
        'entities.entity',
    ],

];
