<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionEndedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
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
            ->subject('Subscription Ended - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your subscription for '.$this->tenant->name.' has ended.')
            ->line('Your account has been downgraded to the FREE plan.')
            ->line('You can continue using Termio with limited features, or upgrade to a paid plan to unlock all features.')
            ->action('View Plans & Upgrade', $frontendUrl.'/settings/subscription')
            ->line('Thank you for using Termio. We hope to see you back soon!');
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
            'type' => 'subscription_ended',
        ];
    }
}
