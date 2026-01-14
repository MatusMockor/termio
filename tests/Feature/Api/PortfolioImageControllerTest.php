<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PortfolioImage;
use App\Models\PortfolioTag;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PortfolioImageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_index_returns_images_list(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();
        PortfolioImage::factory()->forStaff($staff)->count(3)->create();

        $response = $this->getJson(route('portfolio-images.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'url', 'is_public'],
                ],
            ]);
    }

    public function test_store_creates_image_with_file_upload(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();
        $file = UploadedFile::fake()->image('test-photo.jpg', 800, 600);

        $response = $this->postJson(route('portfolio-images.store'), [
            'image' => $file,
            'staff_id' => $staff->id,
            'title' => 'Krásny účes',
            'is_public' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Krásny účes')
            ->assertJsonPath('data.is_public', true);

        $this->assertDatabaseHas(PortfolioImage::class, [
            'title' => 'Krásny účes',
            'staff_id' => $staff->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('portfolio-images.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image', 'staff_id']);
    }

    public function test_store_validates_image_type(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson(route('portfolio-images.store'), [
            'image' => $file,
            'staff_id' => $staff->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_store_attaches_tags(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();
        $tags = PortfolioTag::factory()->forTenant($this->tenant)->count(2)->create();
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson(route('portfolio-images.store'), [
            'image' => $file,
            'staff_id' => $staff->id,
            'tag_ids' => $tags->pluck('id')->toArray(),
        ]);

        $response->assertCreated();

        $image = PortfolioImage::latest()->first();
        $this->assertCount(2, $image->tags);
    }

    public function test_show_returns_single_image(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();
        $image = PortfolioImage::factory()->forStaff($staff)->create();

        $response = $this->getJson(route('portfolio-images.show', $image));

        $response->assertOk()
            ->assertJsonPath('data.id', $image->id)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'description', 'url', 'is_public', 'tags', 'staff'],
            ]);
    }

    public function test_update_modifies_image(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();
        $image = PortfolioImage::factory()->forStaff($staff)->create();

        $response = $this->putJson(route('portfolio-images.update', $image), [
            'title' => 'Nový názov',
            'description' => 'Popis fotky',
            'is_public' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Nový názov')
            ->assertJsonPath('data.description', 'Popis fotky')
            ->assertJsonPath('data.is_public', false);
    }

    public function test_update_syncs_tags(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();
        $image = PortfolioImage::factory()->forStaff($staff)->create();
        $oldTags = PortfolioTag::factory()->forTenant($this->tenant)->count(2)->create();
        $newTags = PortfolioTag::factory()->forTenant($this->tenant)->count(3)->create();

        $image->tags()->attach($oldTags->pluck('id'));

        $response = $this->putJson(route('portfolio-images.update', $image), [
            'tag_ids' => $newTags->pluck('id')->toArray(),
        ]);

        $response->assertOk();

        $image->refresh();
        $this->assertCount(3, $image->tags);
        $this->assertTrue($image->tags->pluck('id')->diff($newTags->pluck('id'))->isEmpty());
    }

    public function test_destroy_deletes_image(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();
        $image = PortfolioImage::factory()->forStaff($staff)->create();

        $response = $this->deleteJson(route('portfolio-images.destroy', $image));

        $response->assertNoContent();

        $this->assertSoftDeleted(PortfolioImage::class, [
            'id' => $image->id,
        ]);
    }

    public function test_reorder_updates_sort_order(): void
    {
        $this->actingAsOwner();

        $staff = StaffProfile::factory()->forTenant($this->tenant)->create();
        $images = PortfolioImage::factory()->forStaff($staff)->count(3)->create();

        // Order format: array of image IDs in desired order (index = sort_order)
        $newOrder = [
            $images[2]->id,
            $images[0]->id,
            $images[1]->id,
        ];

        $response = $this->postJson(route('portfolio-images.reorder'), [
            'order' => $newOrder,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Images reordered successfully.');

        $this->assertDatabaseHas(PortfolioImage::class, [
            'id' => $images[2]->id,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas(PortfolioImage::class, [
            'id' => $images[0]->id,
            'sort_order' => 1,
        ]);
    }

    public function test_by_staff_returns_staff_portfolio(): void
    {
        $this->actingAsOwner();

        $staff1 = StaffProfile::factory()->forTenant($this->tenant)->create();
        $staff2 = StaffProfile::factory()->forTenant($this->tenant)->create();

        PortfolioImage::factory()->forStaff($staff1)->count(3)->create();
        PortfolioImage::factory()->forStaff($staff2)->count(2)->create();

        $response = $this->getJson(route('staff.portfolio', $staff1));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_endpoints_require_authentication(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = StaffProfile::factory()->forTenant($tenant)->create();
        $image = PortfolioImage::factory()->forStaff($staff)->create();

        $this->getJson(route('portfolio-images.index'))->assertUnauthorized();
        $this->postJson(route('portfolio-images.store'))->assertUnauthorized();
        $this->getJson(route('portfolio-images.show', $image))->assertUnauthorized();
        $this->putJson(route('portfolio-images.update', $image))->assertUnauthorized();
        $this->deleteJson(route('portfolio-images.destroy', $image))->assertUnauthorized();
        $this->postJson(route('portfolio-images.reorder'))->assertUnauthorized();
        $this->getJson(route('staff.portfolio', $staff))->assertUnauthorized();
    }
}
