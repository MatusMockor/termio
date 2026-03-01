<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\Services\StripeService;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Customer;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\InvalidRequestException;
use Tests\TestCase;

final class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTenantWithOwner();
        $this->tenant->update(['stripe_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}')]);
    }

    // ==========================================
    // List Invoices Tests
    // ==========================================

    public function test_user_can_list_invoices(): void
    {
        Invoice::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('billing.invoices.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'amount_gross',
                    'currency',
                    'status',
                ],
            ],
        ]);
    }

    public function test_user_sees_only_own_tenant_invoices(): void
    {
        Invoice::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        Invoice::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('billing.invoices.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_list_invoices_returns_empty_array_when_no_invoices(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('billing.invoices.index'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_list_invoices_requires_authentication(): void
    {
        $response = $this->getJson(route('billing.invoices.index'));

        $response->assertUnauthorized();
    }

    public function test_list_invoices_requires_owner_role(): void
    {
        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->getJson(route('billing.invoices.index'));

        $response->assertForbidden();
    }

    // ==========================================
    // View Single Invoice Tests
    // ==========================================

    public function test_user_can_view_single_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-'.now()->format('Y-m').'-0001',
            'amount_gross' => 17.88,
            'currency' => 'EUR',
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('billing.invoices.show', $invoice->id));

        $response->assertOk();
        $response->assertJsonPath('data.id', $invoice->id);
        $response->assertJsonPath('data.invoice_number', $invoice->invoice_number);
        $response->assertJsonPath('data.status', 'paid');
    }

    public function test_user_cannot_view_other_tenant_invoice(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherInvoice = Invoice::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('billing.invoices.show', $otherInvoice->id));

        $response->assertNotFound();
        $response->assertJsonPath('error', 'Invoice not found.');
    }

    public function test_view_invoice_returns_404_for_nonexistent_invoice(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('billing.invoices.show', 99999));

        $response->assertNotFound();
    }

    public function test_view_invoice_requires_authentication(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson(route('billing.invoices.show', $invoice->id));

        $response->assertUnauthorized();
    }

    public function test_view_invoice_requires_owner_role(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);

        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->getJson(route('billing.invoices.show', $invoice->id));

        $response->assertForbidden();
    }

    // ==========================================
    // Download Invoice PDF Tests
    // ==========================================

    public function test_user_cannot_download_other_tenant_invoice_pdf(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherInvoice = Invoice::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('billing.invoices.download', $otherInvoice->id));

        $response->assertNotFound();
    }

    public function test_download_invoice_pdf_requires_authentication(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson(route('billing.invoices.download', $invoice->id));

        $response->assertUnauthorized();
    }

    public function test_download_invoice_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('billing.invoices.download', 99999));

        $response->assertNotFound();
    }

    // ==========================================
    // Payment Methods Tests
    // ==========================================

    public function test_list_payment_methods_returns_empty_when_none(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('billing.payment-methods.index'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_list_payment_methods_requires_authentication(): void
    {
        $response = $this->getJson(route('billing.payment-methods.index'));

        $response->assertUnauthorized();
    }

    public function test_list_payment_methods_requires_owner_role(): void
    {
        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->getJson(route('billing.payment-methods.index'));

        $response->assertForbidden();
    }

    // ==========================================
    // Billing Portal Session Tests
    // ==========================================

    public function test_owner_can_create_portal_session(): void
    {
        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->never())
            ->method('createCustomer');
        $stripeService->expects($this->once())
            ->method('createBillingPortalSession')
            ->with($this->tenant->stripe_id, 'https://app.termio.sk/billing')
            ->willReturn('https://billing.stripe.com/p/session/test_123');
        $this->app->instance(StripeService::class, $stripeService);

        $response = $this->actingAs($this->user)->postJson(route('billing.portal-session'), [
            'return_url' => 'https://app.termio.sk/billing',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.url', 'https://billing.stripe.com/p/session/test_123');
    }

    public function test_non_owner_cannot_create_portal_session(): void
    {
        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->postJson(route('billing.portal-session'), [
            'return_url' => 'https://app.termio.sk/billing',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Access denied. Owner role required.');
    }

    public function test_unauthenticated_user_cannot_create_portal_session(): void
    {
        $response = $this->postJson(route('billing.portal-session'), [
            'return_url' => 'https://app.termio.sk/billing',
        ]);

        $response->assertUnauthorized();
    }

    public function test_create_portal_session_requires_return_url(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('billing.portal-session'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['return_url']);
    }

    public function test_create_portal_session_validates_return_url_format(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('billing.portal-session'), [
            'return_url' => 'invalid-url',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['return_url']);
    }

    public function test_tenant_without_stripe_id_gets_customer_created_for_portal_session(): void
    {
        $this->tenant->update(['stripe_id' => null]);

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->once())
            ->method('createCustomer')
            ->with($this->callback(fn (Tenant $tenant): bool => $tenant->id === $this->tenant->id))
            ->willReturn(Customer::constructFrom(['id' => 'cus_new_123']));
        $stripeService->expects($this->once())
            ->method('createBillingPortalSession')
            ->with('cus_new_123', 'https://app.termio.sk/billing')
            ->willReturn('https://billing.stripe.com/p/session/test_123');
        $this->app->instance(StripeService::class, $stripeService);

        $response = $this->actingAs($this->user)->postJson(route('billing.portal-session'), [
            'return_url' => 'https://app.termio.sk/billing',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.url', 'https://billing.stripe.com/p/session/test_123');
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'stripe_id' => 'cus_new_123',
        ]);
    }

    public function test_create_portal_session_returns_502_when_customer_invalid(): void
    {
        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->never())
            ->method('createCustomer');
        $stripeService->expects($this->once())
            ->method('createBillingPortalSession')
            ->willThrowException(InvalidRequestException::factory(
                'No such customer',
                404,
                null,
                ['error' => ['code' => 'resource_missing', 'param' => 'customer']],
                null,
                'resource_missing',
                'customer',
            ));
        $this->app->instance(StripeService::class, $stripeService);

        $response = $this->actingAs($this->user)->postJson(route('billing.portal-session'), [
            'return_url' => 'https://app.termio.sk/billing',
        ]);

        $response->assertStatus(502);
        $response->assertJsonPath('error', 'Unable to create billing portal session.');
    }

    public function test_create_portal_session_returns_503_when_stripe_unavailable(): void
    {
        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects($this->never())
            ->method('createCustomer');
        $stripeService->expects($this->once())
            ->method('createBillingPortalSession')
            ->willThrowException(ApiConnectionException::factory('Stripe API is unavailable', 503));
        $this->app->instance(StripeService::class, $stripeService);

        $response = $this->actingAs($this->user)->postJson(route('billing.portal-session'), [
            'return_url' => 'https://app.termio.sk/billing',
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath('error', 'Billing service temporarily unavailable.');
    }

    // ==========================================
    // Invoice Model Tests
    // ==========================================

    public function test_invoice_is_paid_returns_true_for_paid_status(): void
    {
        $invoice = Invoice::factory()->paid()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($invoice->isPaid());
    }

    public function test_invoice_is_open_returns_true_for_open_status(): void
    {
        $invoice = Invoice::factory()->open()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($invoice->isOpen());
    }

    public function test_invoice_is_draft_returns_true_for_draft_status(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($invoice->isDraft());
    }

    public function test_invoice_is_void_returns_true_for_void_status(): void
    {
        $invoice = Invoice::factory()->void()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($invoice->isVoid());
    }
}
