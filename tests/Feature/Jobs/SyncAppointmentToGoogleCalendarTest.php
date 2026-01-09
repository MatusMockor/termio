<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class SyncAppointmentToGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerWithCalendar;

    private Client $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->ownerWithCalendar = User::factory()
            ->forTenant($this->tenant)
            ->owner()
            ->withGoogleCalendar()
            ->create();

        $this->client = Client::factory()
            ->forTenant($this->tenant)
            ->create();

        $this->service = Service::factory()
            ->forTenant($this->tenant)
            ->create(['duration_minutes' => 30]);
    }

    public function test_create_action_creates_google_event(): void
    {
        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($this->client)
            ->forService($this->service)
            ->make();

        $appointment->tenant_id = $this->tenant->id;
        $appointment->save();

        $this->mock(GoogleCalendarService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createEvent')
                ->once()
                ->andReturn('google_event_123');
        });

        $job = new SyncAppointmentToGoogleCalendar($appointment, 'create');
        $job->handle(app(GoogleCalendarService::class));

        $appointment->refresh();
        $this->assertEquals('google_event_123', $appointment->google_event_id);
    }

    public function test_update_action_updates_google_event(): void
    {
        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($this->client)
            ->forService($this->service)
            ->withGoogleEvent()
            ->make();

        $appointment->tenant_id = $this->tenant->id;
        $appointment->save();

        $this->mock(GoogleCalendarService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('updateEvent')
                ->once()
                ->andReturn(true);
        });

        $job = new SyncAppointmentToGoogleCalendar($appointment, 'update');
        $job->handle(app(GoogleCalendarService::class));
    }

    public function test_delete_action_deletes_google_event(): void
    {
        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($this->client)
            ->forService($this->service)
            ->withGoogleEvent()
            ->make();

        $appointment->tenant_id = $this->tenant->id;
        $appointment->save();

        $googleEventId = $appointment->google_event_id;

        $this->mock(GoogleCalendarService::class, function (MockInterface $mock) use ($googleEventId): void {
            $mock->shouldReceive('deleteEvent')
                ->with(Mockery::any(), $googleEventId)
                ->once()
                ->andReturn(true);
        });

        $job = new SyncAppointmentToGoogleCalendar($appointment, 'delete');
        $job->handle(app(GoogleCalendarService::class));

        $appointment->refresh();
        $this->assertNull($appointment->google_event_id);
    }

    public function test_job_skips_when_no_user_with_calendar(): void
    {
        $tenantWithoutCalendar = Tenant::factory()->create();

        User::factory()
            ->forTenant($tenantWithoutCalendar)
            ->owner()
            ->create();

        $client = Client::factory()
            ->forTenant($tenantWithoutCalendar)
            ->create();

        $appointment = Appointment::factory()
            ->forTenant($tenantWithoutCalendar)
            ->forClient($client)
            ->forService($this->service)
            ->make();

        $appointment->tenant_id = $tenantWithoutCalendar->id;
        $appointment->save();

        $this->mock(GoogleCalendarService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createEvent');
            $mock->shouldNotReceive('updateEvent');
            $mock->shouldNotReceive('deleteEvent');
        });

        $job = new SyncAppointmentToGoogleCalendar($appointment, 'create');
        $job->handle(app(GoogleCalendarService::class));
    }

    public function test_update_creates_event_when_none_exists(): void
    {
        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($this->client)
            ->forService($this->service)
            ->make(['google_event_id' => null]);

        $appointment->tenant_id = $this->tenant->id;
        $appointment->save();

        $this->mock(GoogleCalendarService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createEvent')
                ->once()
                ->andReturn('new_google_event');
        });

        $job = new SyncAppointmentToGoogleCalendar($appointment, 'update');
        $job->handle(app(GoogleCalendarService::class));

        $appointment->refresh();
        $this->assertEquals('new_google_event', $appointment->google_event_id);
    }
}
