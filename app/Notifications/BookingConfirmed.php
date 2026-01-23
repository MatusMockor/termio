<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Appointment $appointment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(Client $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(Client $notifiable): MailMessage
    {
        $appointment = $this->appointment;
        $tenant = $appointment->tenant;

        return (new MailMessage)
            ->subject('Potvrdenie rezervácie - '.$tenant->name)
            ->greeting('Dobrý deň, '.$notifiable->name.'!')
            ->markdown('emails.appointments.confirmed', [
                'appointment' => $appointment,
                'dayName' => $this->getSlovakDayName($appointment->starts_at->dayOfWeek),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Client $notifiable): array
    {
        return [
            'client_id' => $notifiable->id,
            'appointment_id' => $this->appointment->id,
            'service_name' => $this->appointment->service->name,
            'starts_at' => $this->appointment->starts_at->toIso8601String(),
        ];
    }

    private function getSlovakDayName(int $dayOfWeek): string
    {
        $days = [
            0 => 'Nedeľa',
            1 => 'Pondelok',
            2 => 'Utorok',
            3 => 'Streda',
            4 => 'Štvrtok',
            5 => 'Piatok',
            6 => 'Sobota',
        ];

        return $days[$dayOfWeek] ?? '';
    }
}
