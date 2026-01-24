<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UsageLimitReachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $limitType,
        private readonly int $limit,
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
        $limitName = $this->formatLimitName($this->limitType);

        return (new MailMessage)
            ->subject('Usage Limit Reached - '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('You have reached 100% of your '.$limitName.' limit for '.$this->tenant->name.'.')
            ->line('**Limit:** '.$this->limit.' '.$limitName)
            ->line('You will not be able to create new '.$limitName.' until the next billing period or until you upgrade your plan.')
            ->action('Upgrade Your Plan', $frontendUrl.'/settings/subscription')
            ->line('Upgrade now to get higher limits and continue using all features without interruption.');
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
            'limit_type' => $this->limitType,
            'limit' => $this->limit,
            'type' => 'usage_limit_reached',
        ];
    }

    private function formatLimitName(string $limitType): string
    {
        return ucwords(str_replace('_', ' ', $limitType));
    }
}
