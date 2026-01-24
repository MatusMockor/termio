<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TrialEndingNotification extends Notification implements ShouldQueue
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
        $trialDaysRemaining = $this->tenant->trialDaysRemaining();

        return (new MailMessage)
            ->subject('Trial Ending Soon - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your trial period for '.$this->tenant->name.' is ending soon.')
            ->line('You have '.$trialDaysRemaining.' days remaining in your trial.')
            ->line('To continue using all features without interruption, please add a payment method and choose a plan.')
            ->action('Choose Your Plan', $frontendUrl.'/settings/subscription')
            ->line('If you have any questions about our plans, feel free to contact our support team.');
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
            'trial_days_remaining' => $this->tenant->trialDaysRemaining(),
            'type' => 'trial_ending',
        ];
    }
}
