<?php

namespace YourVendor\ActivityFeed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use YourVendor\ActivityFeed\Renderers\DescriptionRenderer;

class FeedItem extends Model
{
    protected $fillable = [
        'action',
        'description_template',
        'properties',
        'occurred_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get all entities associated with this feed item.
     */
    public function entities(): HasMany
    {
        return $this->hasMany(FeedItemEntity::class);
    }

    /**
     * Get entities by role.
     */
    public function entitiesByRole(string $role): Collection
    {
        return $this->entities->where('role', $role);
    }

    /**
     * Get the first entity with the specified role.
     */
    public function entityByRole(string $role): ?FeedItemEntity
    {
        return $this->entities->firstWhere('role', $role);
    }

    /**
     * Get the actor entity (who performed the action).
     */
    public function actor(): ?FeedItemEntity
    {
        return $this->entityByRole('actor');
    }

    /**
     * Get the subject entity (what was acted upon).
     */
    public function subject(): ?FeedItemEntity
    {
        return $this->entityByRole('subject');
    }

    /**
     * Render the description with dynamic entity resolution.
     */
    public function renderDescription(?Model $viewer = null, bool $useCache = true): string
    {
        if (!$useCache) {
            return $this->renderDescriptionInternal($viewer);
        }

        $cacheKey = $this->getCacheKey($viewer);
        $cacheTtl = config('feed.cache_ttl', 900); // 15 minutes default

        return Cache::remember($cacheKey, $cacheTtl, function () use ($viewer) {
            return $this->renderDescriptionInternal($viewer);
        });
    }

    /**
     * Internal rendering logic.
     */
    protected function renderDescriptionInternal(?Model $viewer = null): string
    {
        $renderer = app(DescriptionRenderer::class);
        return $renderer->render($this, $viewer);
    }

    /**
     * Get cache key for rendered description.
     */
    protected function getCacheKey(?Model $viewer = null): string
    {
        $viewerId = $viewer ? get_class($viewer) . ':' . $viewer->getKey() : 'guest';
        return "feed_item:{$this->id}:rendered:{$viewerId}";
    }

    /**
     * Invalidate the cached descriptions for this feed item.
     */
    public function invalidateCache(): void
    {
        // For simplicity, we'll use cache tags if available, or a wildcard pattern
        // In production, you might want to store viewer IDs who've seen this item
        Cache::forget($this->getCacheKey());
    }

    /**
     * Scope: Filter by action type.
     */
    public function scopeOfAction($query, string|array $action)
    {
        return is_array($action)
            ? $query->whereIn('action', $action)
            : $query->where('action', $action);
    }

    /**
     * Scope: Filter by date range.
     */
    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter by entities (multiple entities).
     */
    public function scopeForEntities($query, $entities, string $role = null)
    {
        $entityMap = collect($entities)->groupBy(fn($entity) => get_class($entity));

        return $query->whereHas('entities', function ($q) use ($entityMap, $role) {
            $q->where(function ($query) use ($entityMap, $role) {
                foreach ($entityMap as $type => $items) {
                    $ids = $items->pluck('id')->toArray();
                    $query->orWhere(function ($q) use ($type, $ids, $role) {
                        $q->where('entity_type', $type)
                          ->whereIn('entity_id', $ids);

                        if ($role) {
                            $q->where('role', $role);
                        }
                    });
                }
            });
        });
    }

    /**
     * Scope: Filter by a single entity.
     */
    public function scopeForEntity($query, Model $entity, string $role = null)
    {
        return $query->whereHas('entities', function ($q) use ($entity, $role) {
            $q->where('entity_type', get_class($entity))
              ->where('entity_id', $entity->getKey());

            if ($role) {
                $q->where('role', $role);
            }
        });
    }

    /**
     * Scope: Order by most recent first.
     */
    public function scopeLatestOccurred($query)
    {
        return $query->orderBy('occurred_at', 'desc');
    }

    /**
     * Check if this feed item has tracked changes.
     */
    public function hasChanges(): bool
    {
        return isset($this->properties['changes']) && is_array($this->properties['changes']);
    }

    /**
     * Get the tracked changes.
     */
    public function getChanges(): array
    {
        return $this->properties['changes'] ?? [];
    }

    /**
     * Get the number of changes.
     */
    public function getChangesCount(): int
    {
        return $this->properties['changes_count'] ?? count($this->getChanges());
    }

    /**
     * Get a specific change by field name.
     */
    public function getChange(string $field): ?array
    {
        $changes = $this->getChanges();
        foreach ($changes as $change) {
            if ($change['field'] === $field) {
                return $change;
            }
        }
        return null;
    }

    /**
     * Format changes for display.
     */
    public function formatChanges(bool $includeFieldNames = true): array
    {
        $changes = $this->getChanges();
        $formatted = [];

        foreach ($changes as $change) {
            $field = $change['field'];
            $old = $this->formatChangeValue($change['old']);
            $new = $this->formatChangeValue($change['new']);

            if ($includeFieldNames) {
                $fieldLabel = $this->formatFieldName($field);
                $formatted[] = "{$fieldLabel}: {$old} → {$new}";
            } else {
                $formatted[] = "{$old} → {$new}";
            }
        }

        return $formatted;
    }

    /**
     * Format a single change value.
     */
    protected function formatChangeValue($value): string
    {
        if (is_null($value)) {
            return '(empty)';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Format field name from snake_case to Title Case.
     */
    protected function formatFieldName(string $field): string
    {
        return str_replace('_', ' ', ucfirst($field));
    }

    /**
     * Get a summary of changes (e.g., "updated 3 fields").
     */
    public function getChangesSummary(): string
    {
        $count = $this->getChangesCount();

        if ($count === 0) {
            return 'no changes';
        }

        if ($count === 1) {
            $changes = $this->getChanges();
            $field = $this->formatFieldName($changes[0]['field']);
            return "updated {$field}";
        }

        return "updated {$count} fields";
    }

    /**
     * Boot method to set default occurred_at.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($feedItem) {
            if (!$feedItem->occurred_at) {
                $feedItem->occurred_at = now();
            }
        });

        static::deleting(function ($feedItem) {
            $feedItem->invalidateCache();
        });

        static::updated(function ($feedItem) {
            $feedItem->invalidateCache();
        });
    }
}
