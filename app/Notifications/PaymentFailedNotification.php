<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly int $attemptCount,
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
        $remainingAttempts = config('subscription.payment.max_retry_attempts') - $this->attemptCount;
        $frontendUrl = (string) config('app.frontend_url');

        $message = (new MailMessage)
            ->subject('Payment Failed - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('We were unable to process your subscription payment for '.$this->tenant->name.'.');

        if ($remainingAttempts > 0) {
            $message->line('We will retry the payment automatically. Remaining attempts: '.$remainingAttempts.'.')
                ->line('Please ensure your payment method is valid and has sufficient funds.');
        }

        if ($remainingAttempts <= 0) {
            $message->line('This was our final payment attempt. Your subscription will be downgraded to the FREE plan.')
                ->line('To continue with your current plan, please update your payment method.');
        }

        return $message
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
            'attempt_count' => $this->attemptCount,
            'type' => 'payment_failed',
        ];
    }
}
