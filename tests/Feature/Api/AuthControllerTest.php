<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_tenant_and_user(): void
    {
        $name = fake()->name();
        $email = fake()->unique()->safeEmail();
        $businessName = fake()->company();
        $businessType = fake()->word();
        $password = fake()->password(minLength: 8);

        $response = $this->postJson(route('auth.register'), [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'business_name' => $businessName,
            'business_type' => $businessType,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'tenant' => ['id', 'name', 'slug'],
                'token',
            ]);

        $this->assertDatabaseHas(User::class, [
            'email' => $email,
            'name' => $name,
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas(Tenant::class, [
            'name' => $businessName,
            'business_type' => $businessType,
        ]);
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson(route('auth.register'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'business_name']);
    }

    public function test_register_validates_unique_email(): void
    {
        $existingUser = User::factory()->create();
        $password = fake()->password(minLength: 8);

        $response = $this->postJson(route('auth.register'), [
            'name' => fake()->name(),
            'email' => $existingUser->email,
            'password' => $password,
            'password_confirmation' => $password,
            'business_name' => fake()->company(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_validates_password_confirmation(): void
    {
        $response = $this->postJson(route('auth.register'), [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
            'password_confirmation' => 'different_password',
            'business_name' => fake()->company(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $password = fake()->password(minLength: 8);
        $user = User::factory()->create([
            'password' => $password,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'tenant',
                'token',
            ]);
    }

    public function test_login_fails_for_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'wrong_password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $password = fake()->password(minLength: 8);
        $user = User::factory()->create([
            'password' => $password,
            'is_active' => false,
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson(route('auth.login'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_invalidates_token(): void
    {
        $this->actingAsOwner();

        $response = $this->postJson(route('auth.logout'));

        $response->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson(route('auth.logout'));

        $response->assertUnauthorized();
    }

    public function test_me_returns_current_user(): void
    {
        $this->actingAsOwner();

        $response = $this->getJson(route('auth.me'));

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'tenant' => ['id', 'name', 'slug'],
            ])
            ->assertJsonPath('user.id', $this->user->id)
            ->assertJsonPath('tenant.id', $this->tenant->id);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson(route('auth.me'));

        $response->assertUnauthorized();
    }
}
