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

final class SubscriptionDowngradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Plan $plan,
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
            ->subject('Subscription Downgraded - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your subscription for '.$this->tenant->name.' has been downgraded to the '.$this->plan->name.' plan.')
            ->line('This downgrade occurred due to unsuccessful payment attempts.')
            ->line('Some features may no longer be available with your current plan.')
            ->action('Upgrade Your Plan', $frontendUrl.'/settings/subscription')
            ->line('If you have any questions or need assistance, please contact our support team.');
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
            'plan_id' => $this->plan->id,
            'plan_name' => $this->plan->name,
            'type' => 'subscription_downgraded',
        ];
    }
}
