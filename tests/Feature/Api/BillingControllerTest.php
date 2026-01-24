<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
            'monthly_price' => 14.90,
            'yearly_price' => 134.00,
            'is_active' => true,
            'is_public' => true,
        ]);

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
        // Create invoices for the authenticated user's tenant
        Invoice::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create invoices for another tenant
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
    // List Payment Methods Tests
    // ==========================================

    public function test_user_can_list_payment_methods(): void
    {
        PaymentMethod::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('billing.payment-methods.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'card_brand',
                    'card_last4',
                    'is_default',
                ],
            ],
        ]);
    }

    public function test_user_sees_only_own_tenant_payment_methods(): void
    {
        PaymentMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        PaymentMethod::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('billing.payment-methods.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

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
    // Add Payment Method Tests (Validation & Auth)
    // ==========================================

    public function test_add_payment_method_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('billing.payment-methods.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['payment_method_id']);
    }

    public function test_add_payment_method_fails_without_stripe_customer(): void
    {
        $this->tenant->update(['stripe_id' => null]);

        $response = $this->actingAs($this->user)->postJson(route('billing.payment-methods.store'), [
            'payment_method_id' => 'pm_'.fake()->regexify('[A-Za-z0-9]{24}'),
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'No Stripe customer found. Please contact support.');
    }

    public function test_add_payment_method_requires_authentication(): void
    {
        $response = $this->postJson(route('billing.payment-methods.store'), [
            'payment_method_id' => 'pm_test',
        ]);

        $response->assertUnauthorized();
    }

    public function test_add_payment_method_requires_owner_role(): void
    {
        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->postJson(route('billing.payment-methods.store'), [
            'payment_method_id' => 'pm_test',
        ]);

        $response->assertForbidden();
    }

    // ==========================================
    // Remove Payment Method Tests
    // ==========================================

    public function test_user_cannot_remove_payment_method_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherPaymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('billing.payment-methods.destroy', $otherPaymentMethod->id));

        $response->assertNotFound();
    }

    public function test_user_cannot_remove_default_payment_method_with_active_subscription(): void
    {
        $paymentMethod = PaymentMethod::factory()->default()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('billing.payment-methods.destroy', $paymentMethod->id));

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'cannot_remove_default');
    }

    public function test_remove_payment_method_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->user)->deleteJson(route('billing.payment-methods.destroy', 99999));

        $response->assertNotFound();
    }

    public function test_remove_payment_method_requires_authentication(): void
    {
        $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->deleteJson(route('billing.payment-methods.destroy', $paymentMethod->id));

        $response->assertUnauthorized();
    }

    public function test_remove_payment_method_requires_owner_role(): void
    {
        $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);

        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->deleteJson(route('billing.payment-methods.destroy', $paymentMethod->id));

        $response->assertForbidden();
    }

    // ==========================================
    // Set Default Payment Method Tests
    // ==========================================

    public function test_user_cannot_set_default_payment_method_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherPaymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('billing.payment-methods.default', $otherPaymentMethod->id));

        $response->assertNotFound();
    }

    public function test_set_default_fails_without_stripe_customer(): void
    {
        $this->tenant->update(['stripe_id' => null]);

        $paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('billing.payment-methods.default', $paymentMethod->id));

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'No Stripe customer found. Please contact support.');
    }

    public function test_set_default_returns_404_for_nonexistent_payment_method(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('billing.payment-methods.default', 99999));

        $response->assertNotFound();
    }

    public function test_set_default_payment_method_requires_authentication(): void
    {
        $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson(route('billing.payment-methods.default', $paymentMethod->id));

        $response->assertUnauthorized();
    }

    public function test_set_default_payment_method_requires_owner_role(): void
    {
        $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);

        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->postJson(route('billing.payment-methods.default', $paymentMethod->id));

        $response->assertForbidden();
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

    // ==========================================
    // Payment Method Model Tests
    // ==========================================

    public function test_payment_method_is_expired_when_date_is_past(): void
    {
        $paymentMethod = PaymentMethod::factory()->expired()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($paymentMethod->isExpired());
    }

    public function test_payment_method_is_not_expired_when_date_is_future(): void
    {
        $paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'card_exp_month' => 12,
            'card_exp_year' => now()->year + 3,
        ]);

        $this->assertFalse($paymentMethod->isExpired());
    }

    public function test_payment_method_is_expiring_soon(): void
    {
        $paymentMethod = PaymentMethod::factory()->expiringSoon()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($paymentMethod->isExpiringSoon());
    }
}
