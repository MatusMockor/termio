<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TrialEndedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function __construct(
        private readonly Tenant $tenant,
        private readonly bool $converted = false,
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

        if ($this->converted) {
            return (new MailMessage)
                ->subject('Welcome to Termio - Your Subscription is Now Active!')
                ->greeting('Thank you for choosing Termio, '.$notifiable->name.'!')
                ->line('Your trial has ended and your subscription for '.$this->tenant->name.' is now active.')
                ->line('You will be charged automatically each billing period.')
                ->action('View Your Subscription', $frontendUrl.'/settings/subscription')
                ->line('Thank you for using Termio!');
        }

        return (new MailMessage)
            ->subject('Your Termio Trial Has Ended - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your free trial for '.$this->tenant->name.' has ended.')
            ->line('Your account has been moved to the FREE plan with limited features.')
            ->line('Upgrade anytime to unlock all features again!')
            ->action('View Plans', $frontendUrl.'/settings/subscription')
            ->line('We hope to see you back soon!');
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
            'converted' => $this->converted,
            'type' => 'trial_ended',
        ];
    }
}
