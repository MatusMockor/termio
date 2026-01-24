<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Models\Subscription;
use App\Notifications\TrialEndingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SendTrialEndingRemindersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        /** @var array<int, int> $reminderDays */
        $reminderDays = config('subscription.reminders.trial_ending_days');

        foreach ($reminderDays as $days) {
            $this->sendRemindersForDay($days);
        }
    }

    private function sendRemindersForDay(int $days): void
    {
        $targetDate = now()->addDays($days)->startOfDay();

        Subscription::with(['tenant.owner'])
            ->where('stripe_status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', $targetDate)
            ->chunk(100, static function ($subscriptions) use ($days): void {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    /** @var \App\Models\Tenant|null $tenant */
                    $tenant = $subscription->tenant;

                    if ($tenant === null) {
                        continue;
                    }

                    $owner = $tenant->owner;

                    if (! $owner) {
                        Log::warning('No owner found for tenant when sending trial ending reminder', [
                            'tenant_id' => $tenant->id,
                            'subscription_id' => $subscription->id,
                        ]);

                        continue;
                    }

                    $owner->notify(new TrialEndingNotification($tenant));

                    Log::info('Trial ending reminder sent', [
                        'tenant_id' => $tenant->id,
                        'user_id' => $owner->id,
                        'days_remaining' => $days,
                    ]);
                }
            });
    }
}
