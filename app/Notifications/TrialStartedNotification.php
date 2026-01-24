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

final class TrialStartedNotification extends Notification implements ShouldQueue
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
            ->subject('Welcome to Termio - Your 14-Day Trial Has Started!')
            ->greeting('Welcome to Termio, '.$notifiable->name.'!')
            ->line('You have started a 14-day free trial of the '.$this->plan->name.' plan for '.$this->tenant->name.'.')
            ->line('During your trial, you have full access to all '.$this->plan->name.' features:')
            ->line('- Unlimited service creation')
            ->line('- Advanced calendar views')
            ->line('- Email reminders for clients')
            ->line('- And much more!')
            ->action('Explore Your Dashboard', $frontendUrl.'/dashboard')
            ->line('Your trial ends in 14 days. Add a payment method anytime to continue after the trial.')
            ->line('If you have any questions, our support team is here to help.');
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
            'type' => 'trial_started',
        ];
    }
}
