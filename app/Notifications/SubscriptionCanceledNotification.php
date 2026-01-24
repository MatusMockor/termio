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

final class SubscriptionCanceledNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject('Subscription Canceled - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your subscription cancellation for '.$this->tenant->name.' has been confirmed.')
            ->line('**Access ends on:** '.$this->accessEndsAt->format('F j, Y'))
            ->line('You will continue to have full access to all your current features until this date.')
            ->line('After '.$this->accessEndsAt->format('F j, Y').', your account will be downgraded to our free plan with limited features.')
            ->action('Reactivate Subscription', $frontendUrl.'/settings/subscription')
            ->line('Changed your mind? You can reactivate your subscription anytime before '.$this->accessEndsAt->format('F j, Y').' to keep all your features.');
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
            'type' => 'subscription_canceled',
        ];
    }
}
