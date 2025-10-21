<?php

namespace YourVendor\ActivityFeed\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use YourVendor\ActivityFeed\Builders\FeedItemBuilder;
use YourVendor\ActivityFeed\Models\FeedItemEntity;

trait HasFeed
{
    /**
     * Get all feed item entities where this model is referenced.
     */
    public function feedItemEntities(): MorphMany
    {
        return $this->morphMany(FeedItemEntity::class, 'entity');
    }

    /**
     * Get all feed items where this model is referenced.
     */
    public function feedItems()
    {
        return $this->hasManyThrough(
            \YourVendor\ActivityFeed\Models\FeedItem::class,
            FeedItemEntity::class,
            'entity_id', // Foreign key on feed_item_entities
            'id',        // Foreign key on feed_items
            'id',        // Local key on this model
            'feed_item_id' // Local key on feed_item_entities
        )->where('feed_item_entities.entity_type', static::class);
    }

    /**
     * Get feed items where this model has a specific role.
     */
    public function feedItemsAs(string $role)
    {
        return $this->feedItems()->whereHas('entities', function ($query) use ($role) {
            $query->where('role', $role)
                  ->where('entity_type', static::class)
                  ->where('entity_id', $this->getKey());
        });
    }

    /**
     * Get feed items where this model is the subject.
     */
    public function feedItemsAsSubject()
    {
        return $this->feedItemsAs('subject');
    }

    /**
     * Get feed items where this model is the actor.
     */
    public function feedItemsAsActor()
    {
        return $this->feedItemsAs('actor');
    }

    /**
     * Create a new feed item for this model.
     */
    public function createFeedItem(): FeedItemBuilder
    {
        return (new FeedItemBuilder())->performedOn($this);
    }

    /**
     * Log an activity for this model.
     */
    public function logActivity(string $action, string $template, ?array $properties = null): \YourVendor\ActivityFeed\Models\FeedItem
    {
        $builder = $this->createFeedItem()
            ->withAction($action)
            ->withTemplate($template);

        if ($properties) {
            $builder->withProperties($properties);
        }

        return $builder->log();
    }

    /**
     * Get the display name for feed rendering.
     * Override this method in your model to customize.
     */
    public function getFeedDisplayName(): string
    {
        return $this->name ?? $this->title ?? class_basename($this) . ' #' . $this->getKey();
    }
}
