<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'staff',
            'is_active' => true,
            'is_admin' => false,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_admin' => true,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'owner',
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'staff',
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function withGoogleCalendar(): static
    {
        return $this->state(fn (array $attributes): array => [
            'google_id' => fake()->uuid(),
            'google_access_token' => 'test_access_token',
            'google_refresh_token' => 'test_refresh_token',
            'google_token_expires_at' => now()->addHour(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
