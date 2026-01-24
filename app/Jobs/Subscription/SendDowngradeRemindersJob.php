<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Models\Subscription;
use App\Notifications\SubscriptionDowngradeReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SendDowngradeRemindersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const REMINDER_DAYS = 3;

    public function handle(): void
    {
        $targetDate = now()->addDays(self::REMINDER_DAYS)->startOfDay();

        Subscription::with(['tenant.owner', 'plan', 'scheduledPlan'])
            ->whereNotNull('scheduled_plan_id')
            ->whereNotNull('scheduled_change_at')
            ->whereDate('scheduled_change_at', $targetDate)
            ->chunk(100, static function ($subscriptions): void {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    /** @var \App\Models\Tenant|null $tenant */
                    $tenant = $subscription->tenant;

                    if ($tenant === null) {
                        continue;
                    }

                    $owner = $tenant->owner;

                    if ($owner === null) {
                        Log::warning('No owner found for tenant when sending downgrade reminder', [
                            'tenant_id' => $tenant->id,
                            'subscription_id' => $subscription->id,
                        ]);

                        continue;
                    }

                    /** @var \App\Models\Plan|null $scheduledPlan */
                    $scheduledPlan = $subscription->scheduledPlan;

                    if ($scheduledPlan === null) {
                        Log::warning('Scheduled plan not found for subscription', [
                            'subscription_id' => $subscription->id,
                            'scheduled_plan_id' => $subscription->scheduled_plan_id,
                        ]);

                        continue;
                    }

                    $owner->notify(new SubscriptionDowngradeReminderNotification(
                        $tenant,
                        $subscription->plan,
                        $scheduledPlan,
                        $subscription->scheduled_change_at,
                    ));

                    Log::info('Downgrade reminder sent', [
                        'tenant_id' => $tenant->id,
                        'user_id' => $owner->id,
                        'scheduled_plan_id' => $scheduledPlan->id,
                        'scheduled_change_at' => $subscription->scheduled_change_at->toIso8601String(),
                    ]);
                }
            });
    }
}
