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

final class SubscriptionDowngradeReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Plan $currentPlan,
        private readonly Plan $scheduledPlan,
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
        $daysRemaining = (int) now()->diffInDays($this->effectiveDate, false);

        return (new MailMessage)
            ->subject('Downgrade Reminder - '.$daysRemaining.' Days Left')
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('This is a reminder that your subscription for '.$this->tenant->name.' will be downgraded in '.$daysRemaining.' days.')
            ->line('**Current plan:** '.$this->currentPlan->name)
            ->line('**New plan:** '.$this->scheduledPlan->name)
            ->line('**Downgrade date:** '.$this->effectiveDate->format('F j, Y'))
            ->line('After the downgrade, you will lose access to some features included in your current plan.')
            ->action('Cancel Downgrade', $frontendUrl.'/settings/subscription')
            ->line('If you want to keep your current plan, you can cancel the downgrade before '.$this->effectiveDate->format('F j, Y').'.');
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
            'scheduled_plan_id' => $this->scheduledPlan->id,
            'scheduled_plan_name' => $this->scheduledPlan->name,
            'effective_date' => $this->effectiveDate->toIso8601String(),
            'type' => 'subscription_downgrade_reminder',
        ];
    }
}
