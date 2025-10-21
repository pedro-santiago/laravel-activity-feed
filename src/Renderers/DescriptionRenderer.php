<?php

namespace YourVendor\ActivityFeed\Renderers;

use Illuminate\Database\Eloquent\Model;
use YourVendor\ActivityFeed\Models\FeedItem;
use YourVendor\ActivityFeed\Models\FeedItemEntity;

class DescriptionRenderer
{
    /**
     * Render the description template with dynamic entity resolution.
     */
    public function render(FeedItem $feedItem, ?Model $viewer = null): string
    {
        $template = $feedItem->description_template;
        $entities = $feedItem->entities->keyBy('role');
        $replacements = [];

        // Replace entity placeholders
        foreach ($entities as $role => $feedItemEntity) {
            $placeholder = "{{$role}}";
            $replacements[$placeholder] = $this->resolveEntity($feedItemEntity, $viewer);
        }

        // Replace property placeholders
        if ($feedItem->properties) {
            foreach ($feedItem->properties as $key => $value) {
                $placeholder = "{{$key}}";
                $replacements[$placeholder] = $this->formatValue($value);
            }
        }

        return strtr($template, $replacements);
    }

    /**
     * Resolve an entity to its display string.
     */
    protected function resolveEntity(FeedItemEntity $feedItemEntity, ?Model $viewer = null): string
    {
        $entity = $feedItemEntity->entity;

        if (!$entity) {
            return '[Unknown]';
        }

        // Special handling for actors - check if it's the viewer
        if ($feedItemEntity->role === 'actor' && $viewer) {
            if ($this->isSameEntity($entity, $viewer)) {
                return 'You';
            }
        }

        // Use custom display name method if available
        return $this->getEntityDisplayName($entity);
    }

    /**
     * Get the display name for an entity.
     */
    protected function getEntityDisplayName(Model $entity): string
    {
        // Check for custom method
        if (method_exists($entity, 'getFeedDisplayName')) {
            return $entity->getFeedDisplayName();
        }

        // Common attribute patterns
        $attributes = ['name', 'title', 'display_name', 'full_name', 'username'];

        foreach ($attributes as $attribute) {
            if (isset($entity->{$attribute})) {
                return $entity->{$attribute};
            }
        }

        // Fallback to class name and ID
        return class_basename($entity) . ' #' . $entity->getKey();
    }

    /**
     * Check if two entities are the same.
     */
    protected function isSameEntity(Model $entity1, Model $entity2): bool
    {
        return get_class($entity1) === get_class($entity2)
            && $entity1->getKey() == $entity2->getKey();
    }

    /**
     * Format a value for display.
     */
    protected function formatValue($value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * Render with custom replacements.
     */
    public function renderWithReplacements(
        FeedItem $feedItem,
        array $customReplacements,
        ?Model $viewer = null
    ): string {
        $description = $this->render($feedItem, $viewer);
        return strtr($description, $customReplacements);
    }
}
