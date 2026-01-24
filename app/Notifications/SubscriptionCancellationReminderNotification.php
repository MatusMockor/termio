<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionCancellationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Carbon $accessEndsAt,
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
        $daysRemaining = (int) now()->diffInDays($this->accessEndsAt, false);

        return (new MailMessage)
            ->subject('Subscription Ending Soon - '.$daysRemaining.' Days Left')
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('This is a reminder that your subscription for '.$this->tenant->name.' ends in '.$daysRemaining.' days.')
            ->line('**Access ends on:** '.$this->accessEndsAt->format('F j, Y'))
            ->line('After this date, your account will be downgraded to our free plan and you will lose access to:')
            ->line('- Premium features')
            ->line('- Extended storage')
            ->line('- Priority support')
            ->action('Reactivate Now', $frontendUrl.'/settings/subscription')
            ->line('Reactivate before '.$this->accessEndsAt->format('F j, Y').' to keep all your features and data.');
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
            'access_ends_at' => $this->accessEndsAt->toIso8601String(),
            'days_remaining' => (int) now()->diffInDays($this->accessEndsAt, false),
            'type' => 'subscription_cancellation_reminder',
        ];
    }
}
