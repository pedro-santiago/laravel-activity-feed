<?php

namespace PedroSantiago\ActivityFeed\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PedroSantiago\ActivityFeed\ActivityFeedServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runMigrations();
    }

    protected function runMigrations(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        // Create feed_items table
        $schema->create('feed_items', function ($table) {
            $table->id();
            $table->string('action', 50)->index();
            $table->text('description_template');
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['occurred_at', 'action'], 'feed_items_occurred_action_index');
        });

        // Create feed_item_entities table
        $schema->create('feed_item_entities', function ($table) {
            $table->id();
            $table->unsignedBigInteger('feed_item_id');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('role', 50);
            $table->timestamp('created_at')->nullable();
            $table->foreign('feed_item_id')->references('id')->on('feed_items')->onDelete('cascade');
            $table->index(['entity_type', 'entity_id', 'role'], 'feed_entities_type_id_role_index');
            $table->index(['feed_item_id', 'role'], 'feed_entities_item_role_index');
        });

        // Create feed_subscriptions table
        $schema->create('feed_subscriptions', function ($table) {
            $table->id();
            $table->string('subscriber_type');
            $table->unsignedBigInteger('subscriber_id');
            $table->string('feedable_type');
            $table->unsignedBigInteger('feedable_id');
            $table->timestamps();
            $table->index(['subscriber_type', 'subscriber_id'], 'feed_subs_subscriber_index');
            $table->index(['feedable_type', 'feedable_id'], 'feed_subs_feedable_index');
            $table->unique(['subscriber_type', 'subscriber_id', 'feedable_type', 'feedable_id'], 'feed_subs_unique');
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActivityFeedServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}
