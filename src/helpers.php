<?php

use PedroSantiago\ActivityFeed\Builders\FeedItemBuilder;

if (!function_exists('feed')) {
    /**
     * Create a new feed item builder instance.
     *
     * @return \PedroSantiago\ActivityFeed\Builders\FeedItemBuilder
     */
    function feed(): FeedItemBuilder
    {
        return app(FeedItemBuilder::class);
    }
}
