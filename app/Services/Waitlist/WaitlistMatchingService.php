<?php

declare(strict_types=1);

namespace App\Services\Waitlist;

use App\Enums\WaitlistEntryStatus;
use App\Models\Appointment;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final class WaitlistMatchingService
{
    /**
     * @return Collection<int, WaitlistEntry>
     */
    public function getReplacementCandidates(Appointment $appointment): Collection
    {
        $appointmentDate = Carbon::parse($appointment->starts_at)->toDateString();

        $candidates = WaitlistEntry::with(['service', 'preferredStaff', 'convertedAppointment'])
            ->forTenant($appointment->tenant_id)
            ->where('service_id', $appointment->service_id)
            ->whereIn('status', [WaitlistEntryStatus::Pending->value, WaitlistEntryStatus::Contacted->value])
            ->orderBy('created_at')
            ->get();

        /** @var SupportCollection<int, WaitlistEntry> $annotatedCandidates */
        $annotatedCandidates = $candidates
            ->map(function (WaitlistEntry $entry) use ($appointmentDate, $appointment): WaitlistEntry {
                $matchesPreferredDate = $entry->preferred_date === null
                    || $entry->preferred_date->toDateString() === $appointmentDate;

                $matchesPreferredStaff = $entry->preferred_staff_id === null
                    || $entry->preferred_staff_id === $appointment->staff_id;
                $matchPriority = $matchesPreferredDate && $matchesPreferredStaff ? 0 : 1;

                $entry->setAttribute('match', [
                    'matched_by' => $matchPriority === 0 ? 'strict' : 'fallback',
                    'matches_preferred_date' => $matchesPreferredDate,
                    'matches_preferred_staff' => $matchesPreferredStaff,
                ]);
                $entry->setAttribute('match_priority', $matchPriority);
                $entry->setAttribute('created_at_timestamp', $entry->created_at->getTimestamp());

                return $entry;
            })
            ->sortBy([
                ['match_priority', 'asc'],
                ['created_at_timestamp', 'asc'],
            ])
            ->values();

        return new Collection($annotatedCandidates->all());
    }
}
