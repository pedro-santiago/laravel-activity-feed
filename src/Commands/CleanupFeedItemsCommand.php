<?php

namespace PedroSantiago\ActivityFeed\Commands;

use Illuminate\Console\Command;
use PedroSantiago\ActivityFeed\Models\FeedItem;

class CleanupFeedItemsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feed:cleanup
                            {--days= : Number of days to keep (overrides config)}
                            {--dry-run : Show what would be deleted without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old feed items based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = $this->option('days') ?? config('feed.retention_days');

        if (is_null($retentionDays)) {
            $this->info('Feed retention is set to indefinite. No cleanup needed.');
            return self::SUCCESS;
        }

        $cutoffDate = now()->subDays($retentionDays);

        $query = FeedItem::where('occurred_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No feed items to clean up.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} feed items older than {$retentionDays} days (before {$cutoffDate->toDateTimeString()})");

            // Show sample of what would be deleted
            $sample = $query->limit(5)->get(['id', 'action', 'occurred_at']);
            $this->table(
                ['ID', 'Action', 'Occurred At'],
                $sample->map(fn($item) => [
                    $item->id,
                    $item->action,
                    $item->occurred_at->toDateTimeString(),
                ])
            );

            if ($count > 5) {
                $this->info("... and " . ($count - 5) . " more items.");
            }

            return self::SUCCESS;
        }

        $this->info("Deleting {$count} feed items older than {$retentionDays} days...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        // Delete in chunks to avoid memory issues
        $deleted = 0;
        $query->chunkById(500, function ($items) use (&$deleted, $bar) {
            foreach ($items as $item) {
                $item->delete();
                $deleted++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Successfully deleted {$deleted} feed items.");

        return self::SUCCESS;
    }
}
