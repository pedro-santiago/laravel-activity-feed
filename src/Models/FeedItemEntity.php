<?php

namespace PedroSantiago\ActivityFeed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FeedItemEntity extends Model
{
    const UPDATED_AT = null; // Only track created_at

    protected $fillable = [
        'feed_item_id',
        'entity_type',
        'entity_id',
        'role',
    ];

    /**
     * Get the feed item that owns this entity.
     */
    public function feedItem(): BelongsTo
    {
        return $this->belongsTo(FeedItem::class);
    }

    /**
     * Get the actual entity (polymorphic relation).
     */
    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    /**
     * Check if this entity has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if the entity matches the given model.
     */
    public function isEntity(Model $model): bool
    {
        return $this->entity_type === get_class($model)
            && $this->entity_id == $model->getKey();
    }

    /**
     * Scope: Filter by role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope: Filter by entity type.
     */
    public function scopeOfType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }
}
