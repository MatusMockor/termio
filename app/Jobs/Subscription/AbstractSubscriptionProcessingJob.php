<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base class for subscription processing jobs.
 *
 * Implements the Template Method pattern to define the skeleton of the
 * subscription processing algorithm while allowing subclasses to customize
 * specific steps.
 */
abstract class AbstractSubscriptionProcessingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Template method - defines the algorithm skeleton.
     *
     * This method orchestrates the processing flow:
     * 1. Calls beforeProcessing() hook
     * 2. Builds the query using buildQuery()
     * 3. Processes items in chunks
     * 4. Calls afterProcessing() hook
     */
    public function handle(): void
    {
        $this->beforeProcessing();

        $query = $this->buildQuery();

        $query->chunk($this->getChunkSize(), function (mixed $items): void {
            foreach ($items as $item) {
                $this->processItem($item);
            }
        });

        $this->afterProcessing();
    }

    /**
     * Build the query to fetch items for processing.
     *
     * Subclasses must implement this to define which subscriptions
     * should be processed.
     *
     * @return Builder<covariant Model>
     */
    abstract protected function buildQuery(): Builder;

    /**
     * Process a single item.
     *
     * Subclasses must implement this to define the processing logic
     * for each subscription.
     */
    abstract protected function processItem(Model $item): void;

    /**
     * Get the job name for logging purposes.
     *
     * Subclasses must implement this to provide a descriptive name.
     */
    abstract protected function getJobName(): string;

    /**
     * Hook method called before processing starts.
     *
     * Subclasses can override this to add custom pre-processing logic.
     */
    protected function beforeProcessing(): void
    {
        Log::info("{$this->getJobName()} started");
    }

    /**
     * Hook method called after processing completes.
     *
     * Subclasses can override this to add custom post-processing logic.
     */
    protected function afterProcessing(): void
    {
        Log::info("{$this->getJobName()} completed");
    }

    /**
     * Handle errors during item processing.
     *
     * Provides consistent error logging across all subscription jobs.
     */
    protected function handleError(\Throwable $exception, Model $item): void
    {
        Log::error("{$this->getJobName()} failed for item", [
            'item_id' => $item->getKey(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the chunk size for processing.
     *
     * Subclasses can override this to customize the batch size.
     */
    protected function getChunkSize(): int
    {
        return (int) config('subscription.job_chunk_size', 100);
    }
}
