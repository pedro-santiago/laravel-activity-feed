<?php

namespace PedroSantiago\ActivityFeed\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PedroSantiago\ActivityFeed\Models\FeedItem;
use PedroSantiago\ActivityFeed\Models\FeedItemEntity;

class FeedItemBuilder
{
    protected string $action;
    protected string $descriptionTemplate = '';
    protected array $entities = [];
    protected array $properties = [];
    protected ?Carbon $occurredAt = null;
    protected array $changes = [];

    /**
     * Set the action type.
     */
    public function withAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Set the description template.
     */
    public function withTemplate(string $template): self
    {
        $this->descriptionTemplate = $template;
        return $this;
    }

    /**
     * Set the description template (alias).
     */
    public function withDescription(string $template): self
    {
        return $this->withTemplate($template);
    }

    /**
     * Add an entity with a specific role.
     */
    public function addEntity(?Model $entity, string $role): self
    {
        if ($entity) {
            $this->entities[] = [
                'entity' => $entity,
                'role' => $role,
            ];
        }
        return $this;
    }

    /**
     * Add the actor (who performed the action).
     */
    public function causedBy(?Model $actor): self
    {
        return $this->addEntity($actor, 'actor');
    }

    /**
     * Add the actor (alias).
     */
    public function by(?Model $actor): self
    {
        return $this->causedBy($actor);
    }

    /**
     * Add the subject (what was acted upon).
     */
    public function performedOn(?Model $subject): self
    {
        return $this->addEntity($subject, 'subject');
    }

    /**
     * Add the subject (alias).
     */
    public function on(?Model $subject): self
    {
        return $this->performedOn($subject);
    }

    /**
     * Add a target entity.
     */
    public function targeting(?Model $target): self
    {
        return $this->addEntity($target, 'target');
    }

    /**
     * Add a mentioned entity.
     */
    public function mentioning(?Model $mentioned): self
    {
        return $this->addEntity($mentioned, 'mentioned');
    }

    /**
     * Add a related entity.
     */
    public function relatedTo(?Model $related): self
    {
        return $this->addEntity($related, 'related');
    }

    /**
     * Add multiple entities with the same role.
     */
    public function addEntities(array $entities, string $role): self
    {
        foreach ($entities as $entity) {
            $this->addEntity($entity, $role);
        }
        return $this;
    }

    /**
     * Set additional properties/metadata.
     */
    public function withProperties(array $properties): self
    {
        $this->properties = array_merge($this->properties, $properties);
        return $this;
    }

    /**
     * Add a single property.
     */
    public function withProperty(string $key, $value): self
    {
        $this->properties[$key] = $value;
        return $this;
    }

    /**
     * Set when the action occurred.
     */
    public function occurredAt(Carbon|string $occurredAt): self
    {
        $this->occurredAt = $occurredAt instanceof Carbon
            ? $occurredAt
            : Carbon::parse($occurredAt);
        return $this;
    }

    /**
     * Track a field change (for grouped updates).
     */
    public function withChange(string $field, $oldValue, $newValue): self
    {
        $this->changes[] = [
            'field' => $field,
            'old' => $oldValue,
            'new' => $newValue,
        ];
        return $this;
    }

    /**
     * Track multiple field changes at once.
     */
    public function withChanges(array $changes): self
    {
        foreach ($changes as $field => $values) {
            if (is_array($values) && isset($values['old'], $values['new'])) {
                $this->withChange($field, $values['old'], $values['new']);
            }
        }
        return $this;
    }

    /**
     * Automatically detect changes from a model's dirty attributes.
     */
    public function withModelChanges(Model $model): self
    {
        if (!$model->wasChanged()) {
            return $this;
        }

        foreach ($model->getChanges() as $field => $newValue) {
            $oldValue = $model->getOriginal($field);
            $this->withChange($field, $oldValue, $newValue);
        }

        return $this;
    }

    /**
     * Create and save the feed item.
     */
    public function log(): FeedItem
    {
        $this->validate();

        // Merge changes into properties if any exist
        $properties = $this->properties;
        if (!empty($this->changes)) {
            $properties['changes'] = $this->changes;
            $properties['changes_count'] = count($this->changes);
        }

        $feedItem = FeedItem::create([
            'action' => $this->action,
            'description_template' => $this->descriptionTemplate,
            'properties' => $properties,
            'occurred_at' => $this->occurredAt ?? now(),
        ]);

        // Create entity relationships
        foreach ($this->entities as $entityData) {
            FeedItemEntity::create([
                'feed_item_id' => $feedItem->id,
                'entity_type' => get_class($entityData['entity']),
                'entity_id' => $entityData['entity']->getKey(),
                'role' => $entityData['role'],
            ]);
        }

        // Reload with relationships
        return $feedItem->load('entities.entity');
    }

    /**
     * Validate the builder state before logging.
     */
    protected function validate(): void
    {
        if (empty($this->action)) {
            throw new \InvalidArgumentException('Action is required. Use withAction() to set it.');
        }

        if (empty($this->descriptionTemplate)) {
            throw new \InvalidArgumentException('Description template is required. Use withTemplate() to set it.');
        }
    }

    /**
     * Reset the builder state.
     */
    public function reset(): self
    {
        $this->action = '';
        $this->descriptionTemplate = '';
        $this->entities = [];
        $this->properties = [];
        $this->occurredAt = null;
        $this->changes = [];
        return $this;
    }

    /**
     * Get the tracked changes.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Check if there are any tracked changes.
     */
    public function hasChanges(): bool
    {
        return !empty($this->changes);
    }
}
