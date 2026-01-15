<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewBookingReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Appointment $appointment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $appointment = $this->appointment;
        $client = $appointment->client;

        return (new MailMessage)
            ->subject('Nová rezervácia - '.$client->name)
            ->greeting('Dobrý deň!')
            ->markdown('emails.appointments.new-booking', [
                'appointment' => $appointment,
                'client' => $client,
                'dayName' => $this->getSlovakDayName($appointment->starts_at->dayOfWeek),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'client_name' => $this->appointment->client->name,
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
