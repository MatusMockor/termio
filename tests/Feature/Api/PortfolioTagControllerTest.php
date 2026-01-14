<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PortfolioTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PortfolioTagControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_tags_list(): void
    {
        $this->actingAsOwner();

        PortfolioTag::factory()->forTenant($this->tenant)->count(3)->create();

        $response = $this->getJson(route('portfolio-tags.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'color'],
                ],
            ]);
    }

    public function test_store_creates_tag(): void
    {
        $this->actingAsOwner();

        $name = fake()->word().' '.fake()->randomNumber(2);

        $response = $this->postJson(route('portfolio-tags.store'), [
            'name' => $name,
            'color' => '#FF5733',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.color', '#FF5733');

        $this->assertDatabaseHas(PortfolioTag::class, [
            'name' => $name,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('portfolio-tags.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_color_format(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('portfolio-tags.store'), [
            'name' => fake()->word(),
            'color' => 'invalid-color',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['color']);
    }

    public function test_store_generates_slug_automatically(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('portfolio-tags.store'), [
            'name' => 'Svadobné účesy',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'svadobne-ucesy');
    }

    public function test_update_modifies_tag(): void
    {
        $this->actingAsOwner();

        $tag = PortfolioTag::factory()->forTenant($this->tenant)->create();

        $newName = fake()->word().' updated';

        $response = $this->putJson(route('portfolio-tags.update', $tag), [
            'name' => $newName,
            'color' => '#00FF00',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', $newName)
            ->assertJsonPath('data.color', '#00FF00');
    }

    public function test_destroy_deletes_tag(): void
    {
        $this->actingAsOwner();

        $tag = PortfolioTag::factory()->forTenant($this->tenant)->create();

        $response = $this->deleteJson(route('portfolio-tags.destroy', $tag));

        $response->assertNoContent();

        $this->assertDatabaseMissing(PortfolioTag::class, [
            'id' => $tag->id,
        ]);
    }

    public function test_endpoints_require_authentication(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $tag = PortfolioTag::factory()->forTenant($tenant)->create();

        $this->getJson(route('portfolio-tags.index'))->assertUnauthorized();
        $this->postJson(route('portfolio-tags.store'))->assertUnauthorized();
        $this->putJson(route('portfolio-tags.update', $tag))->assertUnauthorized();
        $this->deleteJson(route('portfolio-tags.destroy', $tag))->assertUnauthorized();
    }
}
