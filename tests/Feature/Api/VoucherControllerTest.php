<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VoucherControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOwner();
        $this->enableFeatures(['gift_vouchers' => true]);
        $this->tenant->update(['slug' => 'voucher-tenant']);
    }

    public function test_owner_can_create_list_adjust_and_deactivate_voucher(): void
    {
        $storeResponse = $this->postJson(route('vouchers.store'), [
            'code' => 'GIFT-TEST-0001',
            'initial_amount' => 100,
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('data.code', 'GIFT-TEST-0001')
            ->assertJsonPath('data.balance_amount', 100);

        $voucherId = (int) $storeResponse->json('data.id');

        $listResponse = $this->getJson(route('vouchers.index'));
        $listResponse->assertOk()->assertJsonCount(1, 'data');

        $adjustResponse = $this->postJson(route('vouchers.adjust-balance', $voucherId), [
            'amount' => -20,
            'reason' => 'Manual correction',
        ]);

        $adjustResponse->assertOk()
            ->assertJsonPath('data.balance_amount', 80);

        $deactivateResponse = $this->postJson(route('vouchers.deactivate', $voucherId));

        $deactivateResponse->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_public_validate_endpoint_returns_success_and_failure_payloads(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create(['price' => 40]);
        $voucher = Voucher::factory()->forTenant($this->tenant)->create([
            'code' => 'GIFT-VALID-1',
            'balance_amount' => 30,
            'status' => 'active',
            'expires_at' => Carbon::now()->addWeek(),
        ]);

        $successResponse = $this->postJson(route('booking.vouchers.validate', ['tenantSlug' => $this->tenant->slug]), [
            'code' => $voucher->code,
            'service_id' => $service->id,
        ]);

        $successResponse->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('discount_amount', 30)
            ->assertJsonPath('remaining_balance', 0);

        $voucher->update(['status' => 'inactive']);

        $failureResponse = $this->postJson(route('booking.vouchers.validate', ['tenantSlug' => $this->tenant->slug]), [
            'code' => $voucher->code,
            'service_id' => $service->id,
        ]);

        $failureResponse->assertOk()
            ->assertJsonPath('valid', false);
    }

    public function test_booking_with_voucher_redeems_and_cancel_restores_once(): void
    {
        $service = Service::factory()->forTenant($this->tenant)->create(['price' => 60, 'duration_minutes' => 30]);
        $voucher = Voucher::factory()->forTenant($this->tenant)->create([
            'code' => 'GIFT-BOOK-1',
            'initial_amount' => 50,
            'balance_amount' => 50,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMonth(),
        ]);

        $startsAt = Carbon::now()->addDays(2)->setHour(10)->setMinute(0)->toIso8601String();

        $createResponse = $this->postJson(route('booking.create', ['tenantSlug' => $this->tenant->slug]), [
            'service_id' => $service->id,
            'starts_at' => $startsAt,
            'client_name' => 'Voucher User',
            'client_phone' => '+421900000009',
            'client_email' => 'voucher@example.com',
            'voucher_code' => $voucher->code,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('pricing.voucher_discount_amount', 50)
            ->assertJsonPath('pricing.final_amount_due', 10);

        $appointmentId = (int) $createResponse->json('appointment_id');

        $this->assertSame(0.0, (float) $voucher->fresh()->balance_amount);

        $cancelResponse = $this->postJson(route('appointments.cancel', $appointmentId), [
            'reason' => 'Client requested cancellation',
        ]);

        $cancelResponse->assertOk();
        $this->assertSame(50.0, (float) $voucher->fresh()->balance_amount);

        $this->postJson(route('appointments.cancel', $appointmentId), [
            'reason' => 'Duplicate cancellation',
        ])->assertOk();

        $this->assertSame(50.0, (float) $voucher->fresh()->balance_amount);

        $appointment = Appointment::findOrFail($appointmentId);
        $this->assertSame($voucher->id, $appointment->voucher_id);
    }

    /**
     * @param  array<string, bool>  $features
     */
    private function enableFeatures(array $features): void
    {
        $plan = Plan::factory()->create([
            'features' => $features,
        ]);

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'stripe_status' => 'active',
        ]);
    }
}
