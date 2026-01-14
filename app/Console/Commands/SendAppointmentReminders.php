<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class SendAppointmentReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * @var string
     */
    protected $description = 'Send reminder emails for appointments scheduled for tomorrow';

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow();

        $appointments = Appointment::query()
            ->with(['client', 'service', 'staff', 'tenant'])
            ->whereDate('starts_at', $tomorrow)
            ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
            ->get();

        $sentCount = 0;

        foreach ($appointments as $appointment) {
            $client = $appointment->client;

            if (! $client->email) {
                continue;
            }

            $client->notify(new AppointmentReminder($appointment));
            $sentCount++;

            $this->info("Reminder sent to {$client->email} for appointment #{$appointment->id}");
        }

        $this->info("Total reminders sent: {$sentCount}");

        return self::SUCCESS;
    }
}
