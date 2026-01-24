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

final class SubscriptionResumedNotification extends Notification implements ShouldQueue
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
            ->subject('Subscription Reactivated - '.$this->tenant->name)
            ->greeting('Welcome back, '.$notifiable->name.'!')
            ->line('Great news! Your subscription for '.$this->tenant->name.' has been reactivated.')
            ->line('**Your plan:** '.$this->plan->name)
            ->line('Your cancellation has been reversed and you will continue to be billed according to your current plan.')
            ->line('All your features are now fully restored and your data remains intact.')
            ->action('Go to Dashboard', $frontendUrl.'/dashboard')
            ->line('Thank you for staying with us! If you have any questions, our support team is here to help.');
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
            'type' => 'subscription_resumed',
        ];
    }
}
