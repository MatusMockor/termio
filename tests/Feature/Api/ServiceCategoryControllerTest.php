<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\ServiceCategory;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ServiceCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOwner();
        $this->enableFeatures(['service_taxonomy_priority' => true]);
    }

    public function test_owner_can_crud_and_reorder_categories(): void
    {
        $response = $this->postJson(route('service-categories.store'), [
            'name' => 'Hair',
            'priority' => 50,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Hair')
            ->assertJsonPath('data.priority', 50);

        $categoryId = (int) $response->json('data.id');

        $updateResponse = $this->putJson(route('service-categories.update', $categoryId), [
            'name' => 'Hair Updated',
            'sort_order' => 2,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Hair Updated')
            ->assertJsonPath('data.sort_order', 2);

        $secondCategory = ServiceCategory::factory()->forTenant($this->tenant)->create([
            'sort_order' => 0,
        ]);

        $reorderResponse = $this->postJson(route('service-categories.reorder'), [
            'order' => [$secondCategory->id, $categoryId],
        ]);

        $reorderResponse->assertOk();

        $this->assertSame(0, ServiceCategory::findOrFail($secondCategory->id)->sort_order);
        $this->assertSame(1, ServiceCategory::findOrFail($categoryId)->sort_order);

        $deleteResponse = $this->deleteJson(route('service-categories.destroy', $categoryId));

        $deleteResponse->assertNoContent();
        $this->assertSoftDeleted('service_categories', ['id' => $categoryId]);
    }

    public function test_staff_is_forbidden(): void
    {
        $this->actingAsStaff();

        $response = $this->postJson(route('service-categories.store'), [
            'name' => 'Hair',
        ]);

        $response->assertForbidden();
    }

    public function test_tenant_isolation_blocks_cross_tenant_update(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCategory = ServiceCategory::factory()->forTenant($otherTenant)->create();

        $response = $this->putJson(route('service-categories.update', $otherCategory->id), [
            'name' => 'Cross Tenant Update',
        ]);

        $response->assertNotFound();
    }

    /**
     * @param  array<string, bool>  $features
     */
    private function enableFeatures(array $features): void
    {
        $plan = Plan::factory()->create([
            'features' => $features,
        ]);

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'stripe_status' => 'active',
        ]);
    }
}
