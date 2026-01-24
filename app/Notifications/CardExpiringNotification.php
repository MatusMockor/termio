<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CardExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $cardLast4,
        private readonly int $expiryMonth,
        private readonly int $expiryYear,
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
        $expiryFormatted = sprintf('%02d/%d', $this->expiryMonth, $this->expiryYear);

        return (new MailMessage)
            ->subject('Your Card is Expiring Soon - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('The card ending in '.$this->cardLast4.' for '.$this->tenant->name.' expires on '.$expiryFormatted.'.')
            ->line('Please update your payment method to avoid any service interruption.')
            ->action('Update Payment Method', $frontendUrl.'/settings/billing')
            ->line('If you have any questions, please contact our support team.');
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
            'card_last4' => $this->cardLast4,
            'expiry_month' => $this->expiryMonth,
            'expiry_year' => $this->expiryYear,
            'type' => 'card_expiring',
        ];
    }
}
