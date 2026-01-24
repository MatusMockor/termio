<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Repositories\UsageRecordRepository;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class ResetMonthlyUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:reset-monthly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create new usage records for all active tenants for the new month';

    public function __construct(
        private readonly UsageRecordRepository $usageRecords,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $currentPeriod = now()->format('Y-m');

        $this->info(sprintf('Creating usage records for period: %s', $currentPeriod));

        $processedCount = 0;

        Tenant::whereHas('localSubscription', static function ($query): void {
            $query->whereIn('stripe_status', ['active', 'trialing']);
        })->chunk(100, function ($tenants) use ($currentPeriod, &$processedCount): void {
            /** @var Tenant $tenant */
            foreach ($tenants as $tenant) {
                $this->usageRecords->findOrCreateForPeriod($tenant, $currentPeriod);
                $processedCount++;
            }
        });

        $this->info(sprintf('Created usage records for %d tenants', $processedCount));

        return self::SUCCESS;
    }
}
