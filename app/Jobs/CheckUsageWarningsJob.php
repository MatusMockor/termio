<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\UsageLimitServiceContract;
use App\Models\Tenant;
use App\Notifications\UsageWarningNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CheckUsageWarningsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(UsageLimitServiceContract $usageLimitService): void
    {
        Tenant::with('owner')
            ->whereHas('localSubscription', static function ($query): void {
                $query->whereIn('stripe_status', ['active', 'trialing']);
            })
            ->chunk(100, static function ($tenants) use ($usageLimitService): void {
                /** @var Tenant $tenant */
                foreach ($tenants as $tenant) {
                    if (! $usageLimitService->isNearLimit($tenant, 'reservations')) {
                        continue;
                    }

                    $owner = $tenant->owner;

                    if (! $owner) {
                        continue;
                    }

                    $owner->notify(new UsageWarningNotification($tenant));
                }
            });
    }
}
