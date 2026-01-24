<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Models\PaymentMethod;
use App\Notifications\CardExpiringNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SendCardExpiryRemindersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const REMINDER_DAYS = 30;

    public function handle(): void
    {
        $targetMonth = now()->addDays(self::REMINDER_DAYS)->month;
        $targetYear = now()->addDays(self::REMINDER_DAYS)->year;

        PaymentMethod::with(['tenant.owner'])
            ->where('is_default', true)
            ->where('type', 'card')
            ->where('card_exp_year', $targetYear)
            ->where('card_exp_month', $targetMonth)
            ->chunk(100, static function ($paymentMethods): void {
                /** @var PaymentMethod $paymentMethod */
                foreach ($paymentMethods as $paymentMethod) {
                    /** @var \App\Models\Tenant|null $tenant */
                    $tenant = $paymentMethod->tenant;

                    if ($tenant === null) {
                        continue;
                    }

                    $owner = $tenant->owner;

                    if ($owner === null) {
                        Log::warning('No owner found for tenant when sending card expiry reminder', [
                            'tenant_id' => $tenant->id,
                            'payment_method_id' => $paymentMethod->id,
                        ]);

                        continue;
                    }

                    if ($paymentMethod->card_last4 === null || $paymentMethod->card_exp_month === null || $paymentMethod->card_exp_year === null) {
                        continue;
                    }

                    $owner->notify(new CardExpiringNotification(
                        $tenant,
                        $paymentMethod->card_last4,
                        $paymentMethod->card_exp_month,
                        $paymentMethod->card_exp_year,
                    ));

                    Log::info('Card expiry reminder sent', [
                        'tenant_id' => $tenant->id,
                        'user_id' => $owner->id,
                        'card_last4' => $paymentMethod->card_last4,
                        'expiry_month' => $paymentMethod->card_exp_month,
                        'expiry_year' => $paymentMethod->card_exp_year,
                    ]);
                }
            });
    }
}
