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
            ->create();

        $googleCalendarService = $this->createMock(GoogleCalendarService::class);
        $googleCalendarService->expects($this->once())
            ->method('createEvent')
            ->willReturn('google_event_123');
        $this->instance(GoogleCalendarService::class, $googleCalendarService);

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
            ->create();

        $googleCalendarService = $this->createMock(GoogleCalendarService::class);
        $googleCalendarService->expects($this->once())
            ->method('updateEvent')
            ->willReturn(true);
        $this->instance(GoogleCalendarService::class, $googleCalendarService);

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
            ->create();

        $googleEventId = $appointment->google_event_id;

        $googleCalendarService = $this->createMock(GoogleCalendarService::class);
        $googleCalendarService->expects($this->once())
            ->method('deleteEvent')
            ->with($this->isInstanceOf(User::class), $googleEventId)
            ->willReturn(true);
        $this->instance(GoogleCalendarService::class, $googleCalendarService);

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
            ->create();

        $googleCalendarService = $this->createMock(GoogleCalendarService::class);
        $googleCalendarService->expects($this->never())->method('createEvent');
        $googleCalendarService->expects($this->never())->method('updateEvent');
        $googleCalendarService->expects($this->never())->method('deleteEvent');
        $this->instance(GoogleCalendarService::class, $googleCalendarService);

        $job = new SyncAppointmentToGoogleCalendar($appointment, 'create');
        $job->handle(app(GoogleCalendarService::class));
    }

    public function test_update_creates_event_when_none_exists(): void
    {
        $appointment = Appointment::factory()
            ->forTenant($this->tenant)
            ->forClient($this->client)
            ->forService($this->service)
            ->create(['google_event_id' => null]);

        $googleCalendarService = $this->createMock(GoogleCalendarService::class);
        $googleCalendarService->expects($this->once())
            ->method('createEvent')
            ->willReturn('new_google_event');
        $this->instance(GoogleCalendarService::class, $googleCalendarService);

        $job = new SyncAppointmentToGoogleCalendar($appointment, 'update');
        $job->handle(app(GoogleCalendarService::class));

        $appointment->refresh();
        $this->assertEquals('new_google_event', $appointment->google_event_id);
    }
}
