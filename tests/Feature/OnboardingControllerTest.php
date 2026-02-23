<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkingHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'onboarding_completed_at' => null,
            'onboarding_step' => null,
            'onboarding_data' => null,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($this->user);
    }

    public function test_can_get_onboarding_status(): void
    {
        $response = $this->getJson(route('onboarding.status'));

        $response->assertOk()
            ->assertJsonStructure([
                'completed',
                'business_type',
                'current_step',
                'data',
                'completed_at',
            ]);
    }

    public function test_can_get_service_templates_for_salon(): void
    {
        $response = $this->getJson(route('onboarding.templates', ['businessType' => 'salon']));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'name',
                        'duration_minutes',
                        'price',
                    ],
                ],
            ]);

        $this->assertCount(6, $response->json('data'));
    }

    public function test_can_get_service_templates_for_massage(): void
    {
        $response = $this->getJson(route('onboarding.templates', ['businessType' => 'massage']));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'name',
                        'duration_minutes',
                        'price',
                    ],
                ],
            ]);

        $this->assertCount(7, $response->json('data'));
    }

    public function test_cannot_get_templates_for_invalid_business_type(): void
    {
        $response = $this->getJson(route('onboarding.templates', ['businessType' => 'invalid']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['businessType']);
    }

    public function test_can_start_onboarding(): void
    {
        $response = $this->postJson(route('onboarding.start'), [
            'business_type' => 'salon',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Onboarding started successfully',
                'business_type' => 'salon',
            ]);

        $this->tenant->refresh();
        $this->assertEquals(BusinessType::Salon, $this->tenant->business_type);
        $this->assertEquals('business_details', $this->tenant->onboarding_step);
        $this->assertNotNull($this->tenant->onboarding_data);
    }

    public function test_cannot_start_onboarding_without_business_type(): void
    {
        $response = $this->postJson(route('onboarding.start'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['business_type']);
    }

    public function test_cannot_start_onboarding_with_invalid_business_type(): void
    {
        $response = $this->postJson(route('onboarding.start'), [
            'business_type' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['business_type']);
    }

    public function test_can_save_onboarding_progress(): void
    {
        $this->tenant->update([
            'business_type' => BusinessType::Salon,
            'onboarding_step' => 'business_details',
        ]);

        $response = $this->postJson(route('onboarding.save-progress'), [
            'step' => 'services',
            'data' => [
                'selected_services' => ['Haircut', 'Beard Trim'],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Progress saved successfully',
                'step' => 'services',
            ]);

        $this->tenant->refresh();
        $this->assertEquals('services', $this->tenant->onboarding_step);
        $this->assertArrayHasKey('services', $this->tenant->onboarding_data);
    }

    public function test_cannot_save_progress_without_step(): void
    {
        $response = $this->postJson(route('onboarding.save-progress'), [
            'data' => ['test' => 'value'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['step']);
    }

    public function test_cannot_save_progress_without_data(): void
    {
        $response = $this->postJson(route('onboarding.save-progress'), [
            'step' => 'services',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['data']);
    }

    public function test_cannot_save_progress_with_duplicate_working_hours_days(): void
    {
        $response = $this->postJson(route('onboarding.save-progress'), [
            'step' => 'working_hours',
            'data' => [
                'working_hours' => [
                    [
                        'day_of_week' => 1,
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                        'is_active' => true,
                    ],
                    [
                        'day_of_week' => 1,
                        'start_time' => '10:00',
                        'end_time' => '18:00',
                        'is_active' => true,
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['data.working_hours.1.day_of_week']);
    }

    public function test_can_complete_onboarding(): void
    {
        $this->tenant->update([
            'business_type' => BusinessType::Salon,
            'onboarding_step' => 'review',
        ]);

        $response = $this->postJson(route('onboarding.complete'));

        $response->assertOk()
            ->assertJson([
                'message' => 'Onboarding completed successfully',
            ]);

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->onboarding_completed_at);
        $this->assertNull($this->tenant->onboarding_step);
        $this->assertNull($this->tenant->onboarding_data);
    }

    public function test_complete_onboarding_syncs_business_working_hours_from_progress(): void
    {
        $this->tenant->update([
            'business_type' => BusinessType::Salon,
            'onboarding_step' => 'working_hours',
            'onboarding_data' => [
                'working_hours' => [
                    'working_hours' => [
                        [
                            'day_of_week' => 1,
                            'start_time' => '09:00',
                            'end_time' => '17:00',
                            'is_active' => true,
                        ],
                        [
                            'day_of_week' => 2,
                            'start_time' => '10:00',
                            'end_time' => '18:00',
                            'is_active' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson(route('onboarding.complete'));

        $response->assertOk();

        $this->assertDatabaseHas(WorkingHours::class, [
            'tenant_id' => $this->tenant->id,
            'staff_id' => null,
            'day_of_week' => 1,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas(WorkingHours::class, [
            'tenant_id' => $this->tenant->id,
            'staff_id' => null,
            'day_of_week' => 2,
            'is_active' => true,
        ]);

        $mondayHours = WorkingHours::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('staff_id')
            ->where('day_of_week', 1)
            ->first();

        $this->assertNotNull($mondayHours);
        $this->assertEquals('09:00', substr((string) $mondayHours->start_time, 0, 5));
        $this->assertEquals('17:00', substr((string) $mondayHours->end_time, 0, 5));

        $tuesdayHours = WorkingHours::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('staff_id')
            ->where('day_of_week', 2)
            ->first();

        $this->assertNotNull($tuesdayHours);
        $this->assertEquals('10:00', substr((string) $tuesdayHours->start_time, 0, 5));
        $this->assertEquals('18:00', substr((string) $tuesdayHours->end_time, 0, 5));

        $this->assertEquals(
            2,
            WorkingHours::query()
                ->where('tenant_id', $this->tenant->id)
                ->whereNull('staff_id')
                ->count()
        );
    }

    public function test_complete_onboarding_fails_for_invalid_working_hours_progress_data(): void
    {
        $this->tenant->update([
            'business_type' => BusinessType::Salon,
            'onboarding_step' => 'working_hours',
            'onboarding_data' => [
                'working_hours' => [
                    'working_hours' => [
                        [
                            'day_of_week' => 1,
                            'start_time' => 'invalid',
                            'end_time' => '17:00',
                            'is_active' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson(route('onboarding.complete'));

        $response->assertStatus(500);

        $this->tenant->refresh();
        $this->assertNull($this->tenant->onboarding_completed_at);
        $this->assertNotNull($this->tenant->onboarding_data);
    }

    public function test_can_skip_onboarding(): void
    {
        $this->tenant->update(['business_type' => null]);

        $response = $this->postJson(route('onboarding.skip'));

        $response->assertOk()
            ->assertJson([
                'message' => 'Onboarding skipped successfully',
            ]);

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->onboarding_completed_at);
        $this->assertEquals(BusinessType::Other, $this->tenant->business_type);
    }

    public function test_skip_onboarding_preserves_existing_business_type(): void
    {
        $this->tenant->update([
            'business_type' => BusinessType::Salon,
        ]);

        $response = $this->postJson(route('onboarding.skip'));

        $response->assertOk();

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->onboarding_completed_at);
        $this->assertEquals(BusinessType::Salon, $this->tenant->business_type);
    }

    public function test_onboarding_endpoints_require_authentication(): void
    {
        auth()->logout();

        $this->getJson(route('onboarding.status'))->assertUnauthorized();
        $this->getJson(route('onboarding.templates', ['businessType' => 'salon']))->assertUnauthorized();
        $this->postJson(route('onboarding.start'))->assertUnauthorized();
        $this->postJson(route('onboarding.save-progress'))->assertUnauthorized();
        $this->postJson(route('onboarding.complete'))->assertUnauthorized();
        $this->postJson(route('onboarding.skip'))->assertUnauthorized();
    }
}
