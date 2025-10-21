<?php

namespace PedroSantiago\ActivityFeed\Tests\Unit;

use PedroSantiago\ActivityFeed\Builders\FeedItemBuilder;
use PedroSantiago\ActivityFeed\Models\FeedItem;
use PedroSantiago\ActivityFeed\Tests\TestCase;

class FeedItemBuilderTest extends TestCase
{
    /** @test */
    public function it_can_build_a_basic_feed_item()
    {
        $builder = new FeedItemBuilder();

        $feedItem = $builder
            ->withAction('created')
            ->withTemplate('A new item was created')
            ->log();

        $this->assertInstanceOf(FeedItem::class, $feedItem);
        $this->assertEquals('created', $feedItem->action);
        $this->assertEquals('A new item was created', $feedItem->description_template);
    }

    /** @test */
    public function it_can_add_properties_to_feed_item()
    {
        $builder = new FeedItemBuilder();

        $feedItem = $builder
            ->withAction('updated')
            ->withTemplate('Updated with new amount')
            ->withProperties(['amount' => '$500', 'status' => 'approved'])
            ->log();

        $this->assertEquals('$500', $feedItem->properties['amount']);
        $this->assertEquals('approved', $feedItem->properties['status']);
    }

    /** @test */
    public function it_requires_action_and_template()
    {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new FeedItemBuilder();
        $builder->log();
    }

    /** @test */
    public function it_can_set_occurred_at()
    {
        $builder = new FeedItemBuilder();
        $occurredAt = now()->subHours(2);

        $feedItem = $builder
            ->withAction('created')
            ->withTemplate('Item created')
            ->occurredAt($occurredAt)
            ->log();

        // Use diffInSeconds instead of equalTo due to timestamp precision
        $this->assertTrue($feedItem->occurred_at->diffInSeconds($occurredAt) < 2);
    }
}
