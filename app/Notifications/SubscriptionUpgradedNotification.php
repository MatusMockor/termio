<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionUpgradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Plan $oldPlan,
        private readonly Plan $newPlan,
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
            ->subject('Subscription Upgraded - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Great news! Your subscription for '.$this->tenant->name.' has been upgraded.')
            ->line('**Previous plan:** '.$this->oldPlan->name)
            ->line('**New plan:** '.$this->newPlan->name);

        $newFeatures = $this->getNewFeatures();

        if (count($newFeatures) > 0) {
            $message->line('You now have access to these new features:');

            foreach ($newFeatures as $feature) {
                $message->line('- '.$feature);
            }
        }

        return $message
            ->action('Explore Your New Features', $frontendUrl.'/dashboard')
            ->line('Thank you for upgrading! If you have any questions, our support team is here to help.');
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
            'old_plan_id' => $this->oldPlan->id,
            'old_plan_name' => $this->oldPlan->name,
            'new_plan_id' => $this->newPlan->id,
            'new_plan_name' => $this->newPlan->name,
            'type' => 'subscription_upgraded',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getNewFeatures(): array
    {
        $newFeatures = [];
        $oldFeatures = $this->oldPlan->features;
        $newPlanFeatures = $this->newPlan->features;

        foreach ($newPlanFeatures as $feature => $value) {
            if ($value === true && ($oldFeatures[$feature] ?? false) === false) {
                $newFeatures[] = $this->formatFeatureName($feature);
            }
        }

        // Check for increased limits
        $oldLimits = $this->oldPlan->limits;
        $newLimits = $this->newPlan->limits;

        foreach ($newLimits as $limit => $value) {
            $oldValue = $oldLimits[$limit] ?? 0;

            if ($value === -1 && $oldValue !== -1) {
                $newFeatures[] = 'Unlimited '.$this->formatFeatureName($limit);

                continue;
            }

            if ($value > $oldValue) {
                $newFeatures[] = 'Increased '.$this->formatFeatureName($limit).' ('.$oldValue.' -> '.$value.')';
            }
        }

        return $newFeatures;
    }

    private function formatFeatureName(string $feature): string
    {
        return ucwords(str_replace('_', ' ', $feature));
    }
}
