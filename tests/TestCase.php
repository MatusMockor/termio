<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected function createTenantWithOwner(): void
    {
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->owner()
            ->create();
    }

    protected function actingAsOwner(): static
    {
        if (! isset($this->tenant)) {
            $this->createTenantWithOwner();
        }

        Sanctum::actingAs($this->user);

        return $this;
    }

    protected function actingAsStaff(): static
    {
        if (! isset($this->tenant)) {
            $this->tenant = Tenant::factory()->create();
        }

        $staff = User::factory()
            ->forTenant($this->tenant)
            ->staff()
            ->create();

        Sanctum::actingAs($staff);

        return $this;
    }
}
