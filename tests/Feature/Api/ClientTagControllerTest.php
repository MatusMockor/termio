<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ClientTag;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientTagControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOwner();
        $this->enableFeatures(['client_segmentation' => true]);
    }

    public function test_owner_can_crud_client_tags(): void
    {
        $storeResponse = $this->postJson(route('client-tags.store'), [
            'name' => 'Frequent',
            'color' => '#2563EB',
            'sort_order' => 10,
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('data.name', 'Frequent')
            ->assertJsonPath('data.color', '#2563EB');

        $tagId = (int) $storeResponse->json('data.id');

        $indexResponse = $this->getJson(route('client-tags.index'));

        $indexResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tagId);

        $updateResponse = $this->putJson(route('client-tags.update', $tagId), [
            'name' => 'Priority',
            'color' => '#059669',
            'sort_order' => 20,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Priority')
            ->assertJsonPath('data.color', '#059669')
            ->assertJsonPath('data.sort_order', 20);

        $deleteResponse = $this->deleteJson(route('client-tags.destroy', $tagId));

        $deleteResponse->assertNoContent();
        $this->assertSoftDeleted(ClientTag::class, ['id' => $tagId]);
    }

    public function test_tag_names_are_unique_per_tenant_but_can_repeat_across_tenants(): void
    {
        ClientTag::factory()->forTenant($this->tenant)->create([
            'name' => 'VIP Returner',
        ]);

        $duplicateResponse = $this->postJson(route('client-tags.store'), [
            'name' => 'VIP Returner',
            'color' => '#2563EB',
        ]);

        $duplicateResponse->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $otherTenant = Tenant::factory()->create();
        ClientTag::factory()->forTenant($otherTenant)->create([
            'name' => 'VIP Returner',
        ]);

        $this->assertDatabaseCount('client_tags', 2);
    }

    public function test_cross_tenant_tag_access_is_blocked(): void
    {
        $otherTenant = Tenant::factory()->create();
        $tag = ClientTag::factory()->forTenant($otherTenant)->create();

        $this->putJson(route('client-tags.update', $tag), [
            'name' => 'Blocked',
        ])->assertNotFound();

        $this->deleteJson(route('client-tags.destroy', $tag))->assertNotFound();
    }

    /**
     * @param  array<string, bool>  $features
     */
    private function enableFeatures(array $features): void
    {
        $plan = Plan::factory()->create([
            'features' => array_merge(Plan::factory()->raw()['features'], $features),
        ]);

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'stripe_status' => 'active',
        ]);
    }
}
