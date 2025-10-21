<?php

use YourVendor\ActivityFeed\Builders\FeedItemBuilder;

if (!function_exists('feed')) {
    /**
     * Create a new feed item builder instance.
     *
     * @return \YourVendor\ActivityFeed\Builders\FeedItemBuilder
     */
    function feed(): FeedItemBuilder
    {
        return app(FeedItemBuilder::class);
    }
}
