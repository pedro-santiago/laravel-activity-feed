<?php

namespace PedroSantiago\ActivityFeed\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PedroSantiago\ActivityFeed\Builders\FeedItemBuilder;
use PedroSantiago\ActivityFeed\Tests\TestCase;

class GroupedChangesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_track_a_single_change()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('{actor} updated {subject}')
            ->withChange('status', 'pending', 'approved')
            ->log();

        $this->assertTrue($feedItem->hasTrackedChanges());
        $this->assertEquals(1, $feedItem->getTrackedChangesCount());

        $changes = $feedItem->getTrackedChanges();
        $this->assertEquals('status', $changes[0]['field']);
        $this->assertEquals('pending', $changes[0]['old']);
        $this->assertEquals('approved', $changes[0]['new']);
    }

    /** @test */
    public function it_can_track_multiple_changes()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('{actor} updated {subject}')
            ->withChange('status', 'pending', 'approved')
            ->withChange('amount', '$500', '$550')
            ->withChange('priority', 'normal', 'high')
            ->log();

        $this->assertTrue($feedItem->hasTrackedChanges());
        $this->assertEquals(3, $feedItem->getTrackedChangesCount());
    }

    /** @test */
    public function it_can_track_changes_from_array()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('{actor} updated {subject}')
            ->withChanges([
                'status' => ['old' => 'pending', 'new' => 'approved'],
                'amount' => ['old' => '$500', 'new' => '$550'],
            ])
            ->log();

        $this->assertEquals(2, $feedItem->getTrackedChangesCount());
    }

    /** @test */
    public function it_can_get_a_specific_change()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('{actor} updated {subject}')
            ->withChange('status', 'pending', 'approved')
            ->withChange('amount', '$500', '$550')
            ->log();

        $statusChange = $feedItem->getTrackedChange('status');
        $this->assertNotNull($statusChange);
        $this->assertEquals('pending', $statusChange['old']);
        $this->assertEquals('approved', $statusChange['new']);

        $this->assertNull($feedItem->getTrackedChange('nonexistent'));
    }

    /** @test */
    public function it_can_format_changes_for_display()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('{actor} updated {subject}')
            ->withChange('status', 'pending', 'approved')
            ->withChange('amount', '$500', '$550')
            ->log();

        $formatted = $feedItem->formatTrackedChanges();

        $this->assertCount(2, $formatted);
        $this->assertEquals('Status: pending → approved', $formatted[0]);
        $this->assertEquals('Amount: $500 → $550', $formatted[1]);
    }

    /** @test */
    public function it_can_format_changes_without_field_names()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('{actor} updated {subject}')
            ->withChange('status', 'pending', 'approved')
            ->log();

        $formatted = $feedItem->formatTrackedChanges(false);

        $this->assertEquals('pending → approved', $formatted[0]);
    }

    /** @test */
    public function it_generates_correct_changes_summary()
    {
        // No changes
        $feedItem1 = (new FeedItemBuilder())
            ->withAction('created')
            ->withTemplate('Created')
            ->log();

        $this->assertEquals('no changes', $feedItem1->getChangesSummary());

        // One change
        $feedItem2 = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('Updated')
            ->withChange('status', 'pending', 'approved')
            ->log();

        $this->assertEquals('updated Status', $feedItem2->getChangesSummary());

        // Multiple changes
        $feedItem3 = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('Updated')
            ->withChange('status', 'pending', 'approved')
            ->withChange('amount', '$500', '$550')
            ->withChange('priority', 'normal', 'high')
            ->log();

        $this->assertEquals('updated 3 fields', $feedItem3->getChangesSummary());
    }

    /** @test */
    public function it_formats_null_values_correctly()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('Updated')
            ->withChange('notes', null, 'New note')
            ->log();

        $formatted = $feedItem->formatTrackedChanges();
        $this->assertEquals('Notes: (empty) → New note', $formatted[0]);
    }

    /** @test */
    public function it_formats_boolean_values_correctly()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('Updated')
            ->withChange('is_active', false, true)
            ->log();

        $formatted = $feedItem->formatTrackedChanges();
        $this->assertEquals('Is active: No → Yes', $formatted[0]);
    }

    /** @test */
    public function it_formats_field_names_correctly()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('Updated')
            ->withChange('shipping_method', 'standard', 'express')
            ->log();

        $formatted = $feedItem->formatTrackedChanges();
        $this->assertEquals('Shipping method: standard → express', $formatted[0]);
    }

    /** @test */
    public function builder_has_changes_returns_correct_value()
    {
        $builder = new FeedItemBuilder();
        $this->assertFalse($builder->hasChanges());

        $builder->withChange('status', 'old', 'new');
        $this->assertTrue($builder->hasChanges());
    }

    /** @test */
    public function it_stores_changes_count_in_properties()
    {
        $feedItem = (new FeedItemBuilder())
            ->withAction('updated')
            ->withTemplate('Updated')
            ->withChange('field1', 'old1', 'new1')
            ->withChange('field2', 'old2', 'new2')
            ->log();

        $this->assertEquals(2, $feedItem->properties['changes_count']);
    }
}
