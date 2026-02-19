<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

final class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a plan with google_calendar_sync feature enabled
        $this->plan = Plan::factory()->create([
            'name' => 'EASY',
            'slug' => 'easy',
            'features' => [
                'google_calendar_sync' => true,
            ],
        ]);
    }

    private function createSubscriptionForTenant(Tenant $tenant): void
    {
        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $this->plan->id,
            'stripe_status' => 'active',
        ]);
    }

    public function test_status_returns_not_connected_by_default(): void
    {
        $this->actingAsOwner();
        $this->createSubscriptionForTenant($this->tenant);

        $response = $this->getJson(route('google-calendar.status'));

        $response->assertOk()
            ->assertJson([
                'connected' => false,
                'expires_at' => null,
            ]);
    }

    public function test_status_returns_connected_when_token_exists(): void
    {
        $tenant = Tenant::factory()->create();
        $this->createSubscriptionForTenant($tenant);
        $user = User::factory()
            ->forTenant($tenant)
            ->owner()
            ->withGoogleCalendar()
            ->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('google-calendar.status'));

        $response->assertOk()
            ->assertJson([
                'connected' => true,
            ])
            ->assertJsonStructure([
                'connected',
                'expires_at',
            ]);
    }

    public function test_connect_returns_auth_url(): void
    {
        $this->actingAsOwner();
        $this->createSubscriptionForTenant($this->tenant);

        $authUrl = fake()->url();

        $googleCalendarService = $this->createMock(GoogleCalendarService::class);
        $googleCalendarService->expects($this->once())
            ->method('getAuthUrl')
            ->willReturn($authUrl);
        $this->instance(GoogleCalendarService::class, $googleCalendarService);

        $response = $this->getJson(route('google-calendar.connect'));

        $response->assertOk()
            ->assertJsonStructure(['auth_url'])
            ->assertJsonPath('auth_url', $authUrl);
    }

    public function test_callback_stores_tokens(): void
    {
        $this->actingAsOwner();
        $this->createSubscriptionForTenant($this->tenant);

        $authCode = fake()->uuid();
        $accessToken = fake()->sha256();
        $refreshToken = fake()->sha256();

        $tokens = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
        ];

        $googleCalendarService = $this->createMock(GoogleCalendarService::class);
        $googleCalendarService->expects($this->once())
            ->method('exchangeCode')
            ->with($authCode)
            ->willReturn($tokens);
        $this->instance(GoogleCalendarService::class, $googleCalendarService);

        $response = $this->postJson(route('google-calendar.callback'), [
            'code' => $authCode,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Google Calendar connected successfully.',
                'connected' => true,
            ]);

        $this->user->refresh();

        $this->assertEquals($accessToken, $this->user->google_access_token);
        $this->assertEquals($refreshToken, $this->user->google_refresh_token);
        $this->assertNotNull($this->user->google_token_expires_at);
    }

    public function test_callback_validates_code_is_required(): void
    {
        $this->actingAsOwner();
        $this->createSubscriptionForTenant($this->tenant);

        $response = $this->postJson(route('google-calendar.callback'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_callback_handles_exchange_error(): void
    {
        $this->actingAsOwner();
        $this->createSubscriptionForTenant($this->tenant);

        $invalidCode = fake()->uuid();

        $googleCalendarService = $this->createMock(GoogleCalendarService::class);
        $googleCalendarService->expects($this->once())
            ->method('exchangeCode')
            ->with($invalidCode)
            ->willThrowException(new RuntimeException('Invalid code'));
        $this->instance(GoogleCalendarService::class, $googleCalendarService);

        $response = $this->postJson(route('google-calendar.callback'), [
            'code' => $invalidCode,
        ]);

        $response->assertBadRequest()
            ->assertJson([
                'message' => 'Failed to connect Google Calendar.',
            ]);
    }

    public function test_disconnect_removes_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $this->createSubscriptionForTenant($tenant);
        $user = User::factory()
            ->forTenant($tenant)
            ->owner()
            ->withGoogleCalendar()
            ->create();

        Sanctum::actingAs($user);

        $this->assertNotNull($user->google_refresh_token);

        $response = $this->deleteJson(route('google-calendar.disconnect'));

        $response->assertOk()
            ->assertJson([
                'message' => 'Google Calendar disconnected successfully.',
                'connected' => false,
            ]);

        $user->refresh();

        $this->assertNull($user->google_access_token);
        $this->assertNull($user->google_refresh_token);
        $this->assertNull($user->google_token_expires_at);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson(route('google-calendar.status'))->assertUnauthorized();
        $this->getJson(route('google-calendar.connect'))->assertUnauthorized();
        $this->postJson(route('google-calendar.callback'), ['code' => fake()->uuid()])->assertUnauthorized();
        $this->deleteJson(route('google-calendar.disconnect'))->assertUnauthorized();
    }
}
