<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_public_active_plans(): void
    {
        Plan::factory()->create([
            'name' => 'FREE',
            'slug' => 'free',
            'sort_order' => 0,
            'is_active' => true,
            'is_public' => true,
        ]);

        Plan::factory()->create([
            'name' => 'EASY',
            'slug' => 'easy',
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);

        Plan::factory()->create([
            'name' => 'HIDDEN',
            'slug' => 'hidden',
            'sort_order' => 2,
            'is_active' => true,
            'is_public' => false,
        ]);

        Plan::factory()->create([
            'name' => 'INACTIVE',
            'slug' => 'inactive',
            'sort_order' => 3,
            'is_active' => false,
            'is_public' => true,
        ]);

        $response = $this->getJson(route('plans.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'monthly_price',
                        'yearly_price',
                        'pricing' => [
                            'monthly',
                            'yearly',
                            'yearly_monthly_equivalent',
                            'yearly_discount_percent',
                            'currency',
                        ],
                        'pricing_details' => [
                            'monthly' => ['amount', 'currency'],
                            'yearly' => ['amount', 'monthly_equivalent', 'discount_percentage', 'currency'],
                        ],
                        'features',
                        'limits',
                        'is_popular',
                    ],
                ],
            ]);
    }

    public function test_index_returns_plans_ordered_by_sort_order(): void
    {
        Plan::factory()->create(['slug' => 'third', 'sort_order' => 2, 'is_active' => true, 'is_public' => true]);
        Plan::factory()->create(['slug' => 'first', 'sort_order' => 0, 'is_active' => true, 'is_public' => true]);
        Plan::factory()->create(['slug' => 'second', 'sort_order' => 1, 'is_active' => true, 'is_public' => true]);

        $response = $this->getJson(route('plans.index'));

        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertEquals(['first', 'second', 'third'], $slugs);
    }

    public function test_index_does_not_require_authentication(): void
    {
        Plan::factory()->create(['is_active' => true, 'is_public' => true]);

        $response = $this->getJson(route('plans.index'));

        $response->assertOk();
    }

    public function test_show_returns_plan_by_slug(): void
    {
        $plan = Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
            'monthly_price' => 19.90,
            'yearly_price' => 179.00,
            'is_active' => true,
            'is_public' => true,
        ]);

        $response = $this->getJson(route('plans.show', ['plan' => 'smart']));

        $response->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonPath('data.name', 'SMART')
            ->assertJsonPath('data.slug', 'smart')
            ->assertJsonPath('data.is_popular', true);
    }

    public function test_show_returns_404_for_inactive_plan(): void
    {
        Plan::factory()->create([
            'slug' => 'inactive-plan',
            'is_active' => false,
            'is_public' => true,
        ]);

        $response = $this->getJson(route('plans.show', ['plan' => 'inactive-plan']));

        $response->assertNotFound()
            ->assertJsonPath('error', 'plan_not_found');
    }

    public function test_show_returns_404_for_hidden_plan(): void
    {
        Plan::factory()->create([
            'slug' => 'hidden-plan',
            'is_active' => true,
            'is_public' => false,
        ]);

        $response = $this->getJson(route('plans.show', ['plan' => 'hidden-plan']));

        $response->assertNotFound()
            ->assertJsonPath('error', 'plan_not_found');
    }

    public function test_show_returns_404_for_non_existent_plan(): void
    {
        $response = $this->getJson(route('plans.show', ['plan' => 'non-existent']));

        $response->assertNotFound();
    }

    public function test_show_does_not_require_authentication(): void
    {
        Plan::factory()->create([
            'slug' => 'public-plan',
            'is_active' => true,
            'is_public' => true,
        ]);

        $response = $this->getJson(route('plans.show', ['plan' => 'public-plan']));

        $response->assertOk();
    }

    public function test_show_calculates_yearly_discount_correctly(): void
    {
        Plan::factory()->create([
            'slug' => 'discount-test',
            'monthly_price' => 20.00,
            'yearly_price' => 180.00,
            'is_active' => true,
            'is_public' => true,
        ]);

        $response = $this->getJson(route('plans.show', ['plan' => 'discount-test']));

        $response->assertOk();

        $this->assertEquals(15.0, $response->json('data.pricing.yearly_monthly_equivalent'));
        $this->assertEquals(25.0, $response->json('data.pricing.yearly_discount_percent'));
    }

    public function test_show_formats_unlimited_limits(): void
    {
        Plan::factory()->create([
            'slug' => 'unlimited-test',
            'limits' => [
                'reservations_per_month' => -1,
                'users' => 5,
                'services' => -1,
            ],
            'is_active' => true,
            'is_public' => true,
        ]);

        $response = $this->getJson(route('plans.show', ['plan' => 'unlimited-test']));

        $response->assertOk()
            ->assertJsonPath('data.limits.reservations_per_month', 'unlimited')
            ->assertJsonPath('data.limits.users', 5)
            ->assertJsonPath('data.limits.services', 'unlimited');
    }

    public function test_compare_returns_all_plans_with_feature_matrix(): void
    {
        Plan::factory()->create([
            'name' => 'FREE',
            'slug' => 'free',
            'sort_order' => 0,
            'is_active' => true,
            'is_public' => true,
            'features' => [
                'google_calendar_sync' => false,
                'custom_logo' => false,
            ],
        ]);

        Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => true,
            'features' => [
                'google_calendar_sync' => true,
                'custom_logo' => true,
            ],
        ]);

        $response = $this->getJson(route('plans.compare'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'plans' => [
                        '*' => ['id', 'name', 'slug'],
                    ],
                    'features' => [
                        '*' => [
                            'label',
                            'category',
                            'availability',
                        ],
                    ],
                ],
            ]);
    }

    public function test_compare_does_not_require_authentication(): void
    {
        Plan::factory()->create(['is_active' => true, 'is_public' => true]);

        $response = $this->getJson(route('plans.compare'));

        $response->assertOk();
    }

    public function test_compare_excludes_hidden_and_inactive_plans(): void
    {
        Plan::factory()->create([
            'slug' => 'active-public',
            'is_active' => true,
            'is_public' => true,
        ]);

        Plan::factory()->create([
            'slug' => 'hidden',
            'is_active' => true,
            'is_public' => false,
        ]);

        Plan::factory()->create([
            'slug' => 'inactive',
            'is_active' => false,
            'is_public' => true,
        ]);

        $response = $this->getJson(route('plans.compare'));

        $response->assertOk()
            ->assertJsonCount(1, 'data.plans');
    }
}
