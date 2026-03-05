<?php

declare(strict_types=1);

namespace App\Services\Waitlist;

use App\Enums\WaitlistEntryStatus;
use App\Models\Appointment;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class WaitlistMatchingService
{
    /**
     * @return Collection<int, WaitlistEntry>
     */
    public function getReplacementCandidates(Appointment $appointment): Collection
    {
        $appointmentDate = Carbon::parse($appointment->starts_at)->toDateString();

        $baseQuery = WaitlistEntry::with('service')
            ->where('service_id', $appointment->service_id)
            ->whereIn('status', [WaitlistEntryStatus::Pending->value, WaitlistEntryStatus::Contacted->value]);

        $strictQuery = (clone $baseQuery)
            ->where(static function ($query) use ($appointmentDate): void {
                $query->whereNull('preferred_date')
                    ->orWhere('preferred_date', $appointmentDate);
            })
            ->where(static function ($query) use ($appointment): void {
                if ($appointment->staff_id === null) {
                    $query->whereNull('preferred_staff_id');

                    return;
                }

                $query->whereNull('preferred_staff_id')
                    ->orWhere('preferred_staff_id', $appointment->staff_id);
            })
            ->ordered()
            ->get();

        if ($strictQuery->isNotEmpty()) {
            return $strictQuery;
        }

        return $baseQuery
            ->ordered()
            ->get();
    }
}
