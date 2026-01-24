<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Invoice $invoice,
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
            ->subject('Payment Received - Invoice '.$this->invoice->invoice_number)
            ->greeting('Payment Confirmed, '.$notifiable->name.'!')
            ->line('We have received your payment of '.$this->invoice->amount_gross.' '.$this->invoice->currency.' for '.$this->tenant->name.'.')
            ->line('Invoice Number: '.$this->invoice->invoice_number)
            ->line('Date: '.$this->invoice->created_at->format('d.m.Y'))
            ->action('Download Invoice', $frontendUrl.'/billing/invoices/'.$this->invoice->id.'/download')
            ->line('Thank you for using Termio!');
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
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->amount_gross,
            'currency' => $this->invoice->currency,
            'type' => 'payment_success',
        ];
    }
}
