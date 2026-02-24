<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SettingsReservationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->owner()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_settings_index_returns_reservation_settings(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('settings.index'));

        $response->assertOk()
            ->assertJsonPath('reservation_settings.lead_time_hours', $this->tenant->reservation_lead_time_hours)
            ->assertJsonPath('reservation_settings.max_days_in_advance', $this->tenant->reservation_max_days_in_advance)
            ->assertJsonPath('reservation_settings.slot_interval_minutes', $this->tenant->reservation_slot_interval_minutes);
    }

    public function test_owner_can_update_reservation_settings(): void
    {
        $leadTimeMin = (int) config('reservation.limits.lead_time_hours.min');
        $leadTimeMax = (int) config('reservation.limits.lead_time_hours.max');
        $leadTime = min($leadTimeMax, $leadTimeMin + 1);
        $maxDaysMin = (int) config('reservation.limits.max_days_in_advance.min');
        $maxDaysMax = (int) config('reservation.limits.max_days_in_advance.max');
        $maxDays = min($maxDaysMax, $maxDaysMin + 10);
        $slotIntervalMultipleOf = (int) config('reservation.limits.slot_interval_minutes.multiple_of');
        $slotIntervalMax = (int) config('reservation.limits.slot_interval_minutes.max');
        $slotInterval = intdiv($slotIntervalMax, $slotIntervalMultipleOf) * $slotIntervalMultipleOf;

        if (! $slotInterval) {
            $slotInterval = $slotIntervalMultipleOf;
        }

        $response = $this->actingAs($this->owner)
            ->putJson(route('settings.update'), [
                'reservation_lead_time_hours' => $leadTime,
                'reservation_max_days_in_advance' => $maxDays,
                'reservation_slot_interval_minutes' => $slotInterval,
            ]);

        $response->assertOk()
            ->assertJsonPath('reservation_settings.lead_time_hours', $leadTime)
            ->assertJsonPath('reservation_settings.max_days_in_advance', $maxDays)
            ->assertJsonPath('reservation_settings.slot_interval_minutes', $slotInterval);

        $this->assertDatabaseHas(Tenant::class, [
            'id' => $this->tenant->id,
            'reservation_lead_time_hours' => $leadTime,
            'reservation_max_days_in_advance' => $maxDays,
            'reservation_slot_interval_minutes' => $slotInterval,
        ]);
    }

    public function test_update_reservation_settings_validates_input_values(): void
    {
        $invalidLeadTime = (int) config('reservation.limits.lead_time_hours.min') - 1;
        $invalidMaxDays = (int) config('reservation.limits.max_days_in_advance.min') - 1;
        $invalidSlotInterval = (int) config('reservation.limits.slot_interval_minutes.min') + 1;

        $response = $this->actingAs($this->owner)
            ->putJson(route('settings.update'), [
                'reservation_lead_time_hours' => $invalidLeadTime,
                'reservation_max_days_in_advance' => $invalidMaxDays,
                'reservation_slot_interval_minutes' => $invalidSlotInterval,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reservation_lead_time_hours',
                'reservation_max_days_in_advance',
                'reservation_slot_interval_minutes',
            ]);
    }
}
