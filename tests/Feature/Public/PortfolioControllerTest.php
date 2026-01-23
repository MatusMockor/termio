<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\PortfolioImage;
use App\Models\PortfolioTag;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PortfolioControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_returns_public_images(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'test-salon']);
        $staff = StaffProfile::factory()->forTenant($tenant)->create();

        PortfolioImage::factory()->forStaff($staff)->count(3)->create(['is_public' => true]);
        PortfolioImage::factory()->forStaff($staff)->count(2)->create(['is_public' => false]);

        $response = $this->getJson(route('booking.gallery', ['tenantSlug' => 'test-salon']));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_gallery_filters_by_tags(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'test-salon']);
        $staff = StaffProfile::factory()->forTenant($tenant)->create();

        $tag1 = PortfolioTag::factory()->forTenant($tenant)->create();
        $tag2 = PortfolioTag::factory()->forTenant($tenant)->create();

        $imageWithTag1 = PortfolioImage::factory()->forStaff($staff)->create(['is_public' => true]);
        $imageWithTag2 = PortfolioImage::factory()->forStaff($staff)->create(['is_public' => true]);
        $imageWithBoth = PortfolioImage::factory()->forStaff($staff)->create(['is_public' => true]);
        PortfolioImage::factory()->forStaff($staff)->create(['is_public' => true]);

        $imageWithTag1->tags()->attach($tag1);
        $imageWithTag2->tags()->attach($tag2);
        $imageWithBoth->tags()->attach([$tag1->id, $tag2->id]);

        $response = $this->getJson(route('booking.gallery', [
            'tenantSlug' => 'test-salon',
            'tags' => $tag1->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_gallery_returns_404_for_invalid_tenant(): void
    {
        $response = $this->getJson(route('booking.gallery', ['tenantSlug' => 'non-existent']));

        $response->assertNotFound();
    }

    public function test_staff_gallery_returns_staff_public_images(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'test-salon']);
        $staff1 = StaffProfile::factory()->forTenant($tenant)->create();
        $staff2 = StaffProfile::factory()->forTenant($tenant)->create();

        PortfolioImage::factory()->forStaff($staff1)->count(3)->create(['is_public' => true]);
        PortfolioImage::factory()->forStaff($staff1)->count(1)->create(['is_public' => false]);
        PortfolioImage::factory()->forStaff($staff2)->count(2)->create(['is_public' => true]);

        $response = $this->getJson(route('booking.gallery.staff', [
            'tenantSlug' => 'test-salon',
            'staffId' => $staff1->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_tags_returns_tenant_tags(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'test-salon']);
        $otherTenant = Tenant::factory()->create(['slug' => 'other-salon']);

        PortfolioTag::factory()->forTenant($tenant)->count(4)->create();
        PortfolioTag::factory()->forTenant($otherTenant)->count(2)->create();

        $response = $this->getJson(route('booking.gallery.tags', ['tenantSlug' => 'test-salon']));

        $response->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'color'],
                ],
            ]);
    }

    public function test_tags_returns_404_for_invalid_tenant(): void
    {
        $response = $this->getJson(route('booking.gallery.tags', ['tenantSlug' => 'non-existent']));

        $response->assertNotFound();
    }

    public function test_gallery_does_not_require_authentication(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'test-salon']);
        $staff = StaffProfile::factory()->forTenant($tenant)->create();
        PortfolioImage::factory()->forStaff($staff)->create(['is_public' => true]);

        $response = $this->getJson(route('booking.gallery', ['tenantSlug' => 'test-salon']));

        $response->assertOk();
    }

    public function test_gallery_returns_images_ordered(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'test-salon']);
        $staff = StaffProfile::factory()->forTenant($tenant)->create();

        PortfolioImage::factory()->forStaff($staff)->create([
            'is_public' => true,
            'sort_order' => 3,
            'title' => 'Third',
        ]);
        PortfolioImage::factory()->forStaff($staff)->create([
            'is_public' => true,
            'sort_order' => 1,
            'title' => 'First',
        ]);
        PortfolioImage::factory()->forStaff($staff)->create([
            'is_public' => true,
            'sort_order' => 2,
            'title' => 'Second',
        ]);

        $response = $this->getJson(route('booking.gallery', ['tenantSlug' => 'test-salon']));

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals('First', $data[0]['title']);
        $this->assertEquals('Second', $data[1]['title']);
        $this->assertEquals('Third', $data[2]['title']);
    }
}
