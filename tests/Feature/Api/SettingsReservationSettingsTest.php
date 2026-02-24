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
        $response = $this->actingAs($this->owner)
            ->putJson(route('settings.update'), [
                'reservation_lead_time_hours' => 2,
                'reservation_max_days_in_advance' => 60,
                'reservation_slot_interval_minutes' => 15,
            ]);

        $response->assertOk()
            ->assertJsonPath('reservation_settings.lead_time_hours', 2)
            ->assertJsonPath('reservation_settings.max_days_in_advance', 60)
            ->assertJsonPath('reservation_settings.slot_interval_minutes', 15);

        $this->assertDatabaseHas(Tenant::class, [
            'id' => $this->tenant->id,
            'reservation_lead_time_hours' => 2,
            'reservation_max_days_in_advance' => 60,
            'reservation_slot_interval_minutes' => 15,
        ]);
    }

    public function test_update_reservation_settings_validates_input_values(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson(route('settings.update'), [
                'reservation_lead_time_hours' => -1,
                'reservation_max_days_in_advance' => 0,
                'reservation_slot_interval_minutes' => 7,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reservation_lead_time_hours',
                'reservation_max_days_in_advance',
                'reservation_slot_interval_minutes',
            ]);
    }
}
