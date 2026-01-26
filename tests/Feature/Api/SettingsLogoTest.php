<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SettingsLogoTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->owner()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_owner_can_upload_logo(): void
    {
        $logo = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($this->owner)
            ->postJson(route('settings.logo.upload'), [
                'logo' => $logo,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas(Tenant::class, [
            'id' => $this->tenant->id,
        ]);

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->logo);

        Storage::disk('public')->assertExists($this->tenant->logo);
    }

    public function test_logo_validation_requires_image(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->owner)
            ->postJson(route('settings.logo.upload'), [
                'logo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_logo_validation_requires_file(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('settings.logo.upload'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_logo_validation_max_size(): void
    {
        $logo = UploadedFile::fake()->image('logo.png')->size(3000);

        $response = $this->actingAs($this->owner)
            ->postJson(route('settings.logo.upload'), [
                'logo' => $logo,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_uploading_new_logo_deletes_old_logo(): void
    {
        $oldLogo = UploadedFile::fake()->image('old-logo.png', 200, 200);
        $newLogo = UploadedFile::fake()->image('new-logo.png', 200, 200);

        // Upload first logo
        $this->actingAs($this->owner)
            ->postJson(route('settings.logo.upload'), [
                'logo' => $oldLogo,
            ]);

        $this->tenant->refresh();
        $oldLogoPath = $this->tenant->logo;

        Storage::disk('public')->assertExists($oldLogoPath);

        // Upload second logo
        $this->actingAs($this->owner)
            ->postJson(route('settings.logo.upload'), [
                'logo' => $newLogo,
            ]);

        $this->tenant->refresh();

        Storage::disk('public')->assertMissing($oldLogoPath);
        Storage::disk('public')->assertExists($this->tenant->logo);
    }

    public function test_owner_can_delete_logo(): void
    {
        $logo = UploadedFile::fake()->image('logo.png', 200, 200);

        // Upload logo
        $this->actingAs($this->owner)
            ->postJson(route('settings.logo.upload'), [
                'logo' => $logo,
            ]);

        $this->tenant->refresh();
        $logoPath = $this->tenant->logo;

        Storage::disk('public')->assertExists($logoPath);

        // Delete logo
        $response = $this->actingAs($this->owner)
            ->deleteJson(route('settings.logo.delete'));

        $response->assertOk();

        $this->assertDatabaseHas(Tenant::class, [
            'id' => $this->tenant->id,
            'logo' => null,
        ]);

        Storage::disk('public')->assertMissing($logoPath);
    }

    public function test_non_owner_cannot_upload_logo(): void
    {
        $staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($staff)
            ->postJson(route('settings.logo.upload'), [
                'logo' => $logo,
            ]);

        $response->assertForbidden();
    }

    public function test_non_owner_cannot_delete_logo(): void
    {
        $staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staff)
            ->deleteJson(route('settings.logo.delete'));

        $response->assertForbidden();
    }

    public function test_deleting_logo_when_none_exists_does_not_error(): void
    {
        $response = $this->actingAs($this->owner)
            ->deleteJson(route('settings.logo.delete'));

        $response->assertOk();

        $this->assertDatabaseHas(Tenant::class, [
            'id' => $this->tenant->id,
            'logo' => null,
        ]);
    }
}
