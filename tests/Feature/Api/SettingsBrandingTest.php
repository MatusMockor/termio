<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SettingsBrandingTest extends TestCase
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

    public function test_owner_can_update_primary_branding_color(): void
    {
        $primaryColor = fake()->hexColor();

        $response = $this->actingAs($this->owner)
            ->putJson(route('settings.branding.update'), [
                'primary_color' => $primaryColor,
            ]);

        $response->assertOk()
            ->assertJsonPath('branding.primary_color', $primaryColor)
            ->assertJsonPath('logo_url', null);

        $this->tenant->refresh();
        $this->assertSame($primaryColor, $this->tenant->getBrandingPrimaryColor());
    }

    public function test_update_branding_validates_primary_color_format(): void
    {
        $invalidPrimaryColor = ltrim(fake()->hexColor(), '#');

        $response = $this->actingAs($this->owner)
            ->putJson(route('settings.branding.update'), [
                'primary_color' => $invalidPrimaryColor,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color']);
    }

    public function test_non_owner_cannot_update_branding(): void
    {
        $staff = User::factory()->forTenant($this->tenant)->staff()->create();
        $primaryColor = fake()->hexColor();

        $response = $this->actingAs($staff)
            ->putJson(route('settings.branding.update'), [
                'primary_color' => $primaryColor,
            ]);

        $response->assertForbidden();
    }

    public function test_settings_index_returns_branding_and_logo_url(): void
    {
        $primaryColor = fake()->hexColor();

        $this->tenant->update([
            'settings' => [
                'branding' => [
                    'primary_color' => $primaryColor,
                ],
            ],
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('settings.index'));

        $response->assertOk()
            ->assertJsonPath('branding.primary_color', $primaryColor)
            ->assertJsonPath('logo_url', null);
    }

    public function test_update_branding_preserves_existing_settings_keys(): void
    {
        $existingPrimaryColor = fake()->hexColor();
        $updatedPrimaryColor = fake()->hexColor();

        $this->tenant->update([
            'settings' => [
                'notifications' => [
                    'email' => true,
                ],
                'branding' => [
                    'primary_color' => $existingPrimaryColor,
                ],
            ],
        ]);

        $this->actingAs($this->owner)
            ->putJson(route('settings.branding.update'), [
                'primary_color' => $updatedPrimaryColor,
            ])
            ->assertOk();

        $this->tenant->refresh();
        $this->assertSame($updatedPrimaryColor, $this->tenant->getBrandingPrimaryColor());
        $this->assertIsArray($this->tenant->settings);
        $this->assertArrayHasKey('notifications', $this->tenant->settings);
        $this->assertSame(true, $this->tenant->settings['notifications']['email'] ?? null);
    }
}
