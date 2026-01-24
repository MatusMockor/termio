<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionDowngradeScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Plan $currentPlan,
        private readonly Plan $newPlan,
        private readonly Carbon $effectiveDate,
    ) {}

    /**
     * @return array<int, string>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $frontendUrl = (string) config('app.frontend_url');

        $message = (new MailMessage)
            ->subject('Downgrade Scheduled - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your subscription downgrade for '.$this->tenant->name.' has been scheduled.')
            ->line('**Current plan:** '.$this->currentPlan->name)
            ->line('**New plan:** '.$this->newPlan->name)
            ->line('**Effective date:** '.$this->effectiveDate->format('F j, Y'));

        $lostFeatures = $this->getLostFeatures();

        if (count($lostFeatures) > 0) {
            $message->line('The following features will no longer be available after the downgrade:');

            foreach ($lostFeatures as $feature) {
                $message->line('- '.$feature);
            }
        }

        return $message
            ->line('You will continue to have full access to your current plan until '.$this->effectiveDate->format('F j, Y').'.')
            ->action('Cancel Downgrade', $frontendUrl.'/settings/subscription')
            ->line('Changed your mind? You can cancel this downgrade anytime before the effective date.');
    }

    /**
     * @return array<string, mixed>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function toArray(User $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'current_plan_id' => $this->currentPlan->id,
            'current_plan_name' => $this->currentPlan->name,
            'new_plan_id' => $this->newPlan->id,
            'new_plan_name' => $this->newPlan->name,
            'effective_date' => $this->effectiveDate->toIso8601String(),
            'type' => 'subscription_downgrade_scheduled',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getLostFeatures(): array
    {
        $lostFeatures = [];
        $currentFeatures = $this->currentPlan->features;
        $newFeatures = $this->newPlan->features;

        foreach ($currentFeatures as $feature => $value) {
            if ($value === true && ($newFeatures[$feature] ?? false) === false) {
                $lostFeatures[] = $this->formatFeatureName($feature);
            }
        }

        // Check for reduced limits
        $currentLimits = $this->currentPlan->limits;
        $newLimits = $this->newPlan->limits;

        foreach ($currentLimits as $limit => $value) {
            $newValue = $newLimits[$limit] ?? 0;

            if ($value === -1 && $newValue !== -1) {
                $lostFeatures[] = 'Unlimited '.$this->formatFeatureName($limit).' (reduced to '.$newValue.')';

                continue;
            }

            if ($newValue < $value && $newValue !== -1) {
                $lostFeatures[] = 'Reduced '.$this->formatFeatureName($limit).' ('.$value.' -> '.$newValue.')';
            }
        }

        return $lostFeatures;
    }

    private function formatFeatureName(string $feature): string
    {
        return ucwords(str_replace('_', ' ', $feature));
    }
}
