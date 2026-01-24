# Billing and Invoicing Automation

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Backend
**Complexity**: Large
**Dependencies**: `database_schema.md`, `backend_stripe_integration.md`, `backend_subscription_service.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement billing automation, invoice generation, VAT handling, and payment method management. Includes EU-compliant invoicing with proper VAT calculations, VIES validation, and PDF generation per PRD REQ-04, REQ-10, REQ-15.

**Architecture Impact**: Adds billing services, invoice generation, VAT service, and scheduled jobs for recurring billing. Integrates with Stripe webhooks for payment events.

**Risk Assessment**:
- **High**: VAT calculation must be accurate for EU compliance
- **High**: Invoice numbering must be sequential and unique
- **Medium**: PDF generation reliability
- **Medium**: VIES API availability for VAT validation

## Data Layer

### Invoice Model

**File**: `app/Models/Invoice.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $subscription_id
 * @property string|null $stripe_invoice_id
 * @property string $invoice_number
 * @property float $amount_net
 * @property float $vat_rate
 * @property float $vat_amount
 * @property float $amount_gross
 * @property string $currency
 * @property string $customer_name
 * @property string|null $customer_address
 * @property string|null $customer_country
 * @property string|null $customer_vat_id
 * @property array $line_items
 * @property string $status
 * @property Carbon|null $paid_at
 * @property string|null $pdf_path
 * @property string|null $notes
 * @property Carbon|null $billing_period_start
 * @property Carbon|null $billing_period_end
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Tenant $tenant
 * @property-read Subscription|null $subscription
 */
final class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'stripe_invoice_id',
        'invoice_number',
        'amount_net',
        'vat_rate',
        'vat_amount',
        'amount_gross',
        'currency',
        'customer_name',
        'customer_address',
        'customer_country',
        'customer_vat_id',
        'line_items',
        'status',
        'paid_at',
        'pdf_path',
        'notes',
        'billing_period_start',
        'billing_period_end',
    ];

    protected function casts(): array
    {
        return [
            'amount_net' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'amount_gross' => 'decimal:2',
            'line_items' => 'array',
            'paid_at' => 'datetime',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Get formatted invoice number.
     */
    public function getFormattedNumber(): string
    {
        return $this->invoice_number;
    }
}
```

### Invoice Repository

**File**: `app/Contracts/Repositories/InvoiceRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Collection;

interface InvoiceRepository
{
    public function create(array $data): Invoice;

    public function update(Invoice $invoice, array $data): Invoice;

    public function findById(int $id): ?Invoice;

    public function findByStripeId(string $stripeId): ?Invoice;

    public function findByNumber(string $number): ?Invoice;

    public function getByTenant(Tenant $tenant, int $limit = 50): Collection;

    public function getNextInvoiceNumber(): string;
}
```

**File**: `app/Repositories/Eloquent/EloquentInvoiceRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\InvoiceRepository;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentInvoiceRepository implements InvoiceRepository
{
    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);

        return $invoice->fresh() ?? $invoice;
    }

    public function findById(int $id): ?Invoice
    {
        return Invoice::find($id);
    }

    public function findByStripeId(string $stripeId): ?Invoice
    {
        return Invoice::where('stripe_invoice_id', $stripeId)->first();
    }

    public function findByNumber(string $number): ?Invoice
    {
        return Invoice::where('invoice_number', $number)->first();
    }

    public function getByTenant(Tenant $tenant, int $limit = 50): Collection
    {
        return Invoice::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getNextInvoiceNumber(): string
    {
        return DB::transaction(function (): string {
            $yearMonth = now()->format('Y-m');
            $prefix = 'INV-' . $yearMonth . '-';

            $lastInvoice = Invoice::where('invoice_number', 'like', $prefix . '%')
                ->orderByDesc('invoice_number')
                ->lockForUpdate()
                ->first();

            if ($lastInvoice) {
                $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        });
    }
}
```

## Component Architecture

### VAT Service

**File**: `app/Services/Billing/VatService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Services\VatServiceContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class VatService implements VatServiceContract
{
    private const SLOVAKIA_VAT_RATE = 20.00;
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * Calculate VAT rate based on customer location and VAT ID.
     */
    public function calculateVatRate(string $country, ?string $vatId = null): float
    {
        // Slovakia - always 20% VAT
        if ($country === 'SK') {
            return self::SLOVAKIA_VAT_RATE;
        }

        // EU with valid VAT ID - reverse charge (0%)
        if ($this->isEuCountry($country) && $vatId && $this->isValidVatId($vatId)) {
            return 0.00;
        }

        // EU without valid VAT ID - Slovak VAT rate
        if ($this->isEuCountry($country)) {
            return self::SLOVAKIA_VAT_RATE;
        }

        // Non-EU - no VAT
        return 0.00;
    }

    /**
     * Validate EU VAT ID via VIES API.
     */
    public function isValidVatId(string $vatId): bool
    {
        $vatId = strtoupper(preg_replace('/\s+/', '', $vatId));

        if (strlen($vatId) < 4) {
            return false;
        }

        $countryCode = substr($vatId, 0, 2);
        $vatNumber = substr($vatId, 2);

        if (!$this->isEuCountry($countryCode)) {
            return false;
        }

        // Check cache first
        $cacheKey = 'vat_validation_' . $vatId;
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->validateViaVies($countryCode, $vatNumber);
            Cache::put($cacheKey, $result, now()->addHours(24));

            return $result;
        } catch (\Exception $e) {
            Log::warning('VIES validation failed', [
                'vat_id' => $vatId,
                'error' => $e->getMessage(),
            ]);

            // Return false on API failure - treat as invalid
            return false;
        }
    }

    /**
     * Get VAT details for invoice.
     *
     * @return array{rate: float, amount: float, note: string|null}
     */
    public function getVatDetails(float $netAmount, string $country, ?string $vatId = null): array
    {
        $rate = $this->calculateVatRate($country, $vatId);
        $amount = round($netAmount * ($rate / 100), 2);

        $note = null;
        if ($rate === 0.00 && $vatId && $this->isEuCountry($country)) {
            $note = 'Reverse charge - VAT to be paid by customer';
        } elseif ($rate === 0.00 && !$this->isEuCountry($country)) {
            $note = 'Export - VAT exempt';
        }

        return [
            'rate' => $rate,
            'amount' => $amount,
            'note' => $note,
        ];
    }

    /**
     * Check if country is in EU.
     */
    public function isEuCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::EU_COUNTRIES, true);
    }

    /**
     * Validate VAT ID via VIES SOAP API.
     */
    private function validateViaVies(string $countryCode, string $vatNumber): bool
    {
        $wsdl = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

        $client = new \SoapClient($wsdl, [
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 10,
        ]);

        $result = $client->checkVat([
            'countryCode' => $countryCode,
            'vatNumber' => $vatNumber,
        ]);

        return $result->valid === true;
    }
}
```

### Billing Service

**File**: `app/Services/Billing/BillingService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Services\BillingServiceContract;
use App\Contracts\Services\VatServiceContract;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class BillingService implements BillingServiceContract
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly VatServiceContract $vatService,
    ) {}

    /**
     * Create invoice from Stripe payment.
     */
    public function createInvoiceFromStripe(
        Tenant $tenant,
        Subscription $subscription,
        \Stripe\Invoice $stripeInvoice
    ): Invoice {
        // Check if invoice already exists
        $existing = $this->invoices->findByStripeId($stripeInvoice->id);
        if ($existing) {
            return $existing;
        }

        $netAmount = $stripeInvoice->subtotal / 100; // Convert from cents
        $vatDetails = $this->vatService->getVatDetails(
            $netAmount,
            $tenant->country ?? 'SK',
            $tenant->vat_id
        );

        $lineItems = $this->extractLineItems($stripeInvoice);

        $invoice = $this->invoices->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => $stripeInvoice->id,
            'invoice_number' => $this->invoices->getNextInvoiceNumber(),
            'amount_net' => $netAmount,
            'vat_rate' => $vatDetails['rate'],
            'vat_amount' => $vatDetails['amount'],
            'amount_gross' => $netAmount + $vatDetails['amount'],
            'currency' => strtoupper($stripeInvoice->currency),
            'customer_name' => $tenant->name,
            'customer_address' => $tenant->address,
            'customer_country' => $tenant->country,
            'customer_vat_id' => $tenant->vat_id,
            'line_items' => $lineItems,
            'status' => $stripeInvoice->paid ? 'paid' : 'open',
            'paid_at' => $stripeInvoice->paid ? now() : null,
            'notes' => $vatDetails['note'],
            'billing_period_start' => $stripeInvoice->period_start
                ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->period_start)->toDateString()
                : null,
            'billing_period_end' => $stripeInvoice->period_end
                ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->period_end)->toDateString()
                : null,
        ]);

        // Generate PDF
        $this->generateInvoicePdf($invoice);

        return $invoice;
    }

    /**
     * Generate PDF for invoice.
     */
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'company' => $this->getCompanyDetails(),
        ]);

        $filename = 'invoices/' . $invoice->invoice_number . '.pdf';
        Storage::disk('local')->put($filename, $pdf->output());

        $invoice->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Get invoice PDF contents.
     */
    public function getInvoicePdf(Invoice $invoice): ?string
    {
        if (!$invoice->pdf_path) {
            $this->generateInvoicePdf($invoice);
        }

        if (Storage::disk('local')->exists($invoice->pdf_path)) {
            return Storage::disk('local')->get($invoice->pdf_path);
        }

        return null;
    }

    /**
     * Extract line items from Stripe invoice.
     *
     * @return array<int, array{description: string, quantity: int, unit_price: float, amount: float}>
     */
    private function extractLineItems(\Stripe\Invoice $stripeInvoice): array
    {
        $items = [];

        foreach ($stripeInvoice->lines->data as $line) {
            $items[] = [
                'description' => $line->description ?? 'Subscription',
                'quantity' => $line->quantity ?? 1,
                'unit_price' => $line->unit_amount / 100,
                'amount' => $line->amount / 100,
                'period_start' => $line->period?->start
                    ? \Carbon\Carbon::createFromTimestamp($line->period->start)->toDateString()
                    : null,
                'period_end' => $line->period?->end
                    ? \Carbon\Carbon::createFromTimestamp($line->period->end)->toDateString()
                    : null,
            ];
        }

        return $items;
    }

    /**
     * Get company details for invoice header.
     *
     * @return array{name: string, address: string, vat_id: string, email: string}
     */
    private function getCompanyDetails(): array
    {
        return [
            'name' => config('app.company_name', 'Termio s.r.o.'),
            'address' => config('app.company_address', 'Address, Bratislava, Slovakia'),
            'vat_id' => config('app.company_vat_id', 'SK1234567890'),
            'email' => config('app.company_email', 'billing@termio.sk'),
        ];
    }
}
```

### Payment Method Service

**File**: `app/Services/Billing/PaymentMethodService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Services\StripeServiceContract;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class PaymentMethodService
{
    public function __construct(
        private readonly StripeServiceContract $stripeService,
    ) {}

    /**
     * Add a new payment method to tenant.
     */
    public function addPaymentMethod(Tenant $tenant, string $paymentMethodId, bool $setDefault = true): PaymentMethod
    {
        // Attach to Stripe customer
        $this->stripeService->attachPaymentMethod($paymentMethodId, $tenant->stripe_id);

        // Get payment method details
        $stripePaymentMethod = $this->stripeService->getPaymentMethod($paymentMethodId);

        // If setting as default, update Stripe and unset other defaults
        if ($setDefault) {
            $this->stripeService->setDefaultPaymentMethod($tenant->stripe_id, $paymentMethodId);
            PaymentMethod::where('tenant_id', $tenant->id)->update(['is_default' => false]);
        }

        // Create local record
        return PaymentMethod::create([
            'tenant_id' => $tenant->id,
            'stripe_payment_method_id' => $paymentMethodId,
            'type' => $stripePaymentMethod->type,
            'card_brand' => $stripePaymentMethod->card?->brand,
            'card_last4' => $stripePaymentMethod->card?->last4,
            'card_exp_month' => $stripePaymentMethod->card?->exp_month,
            'card_exp_year' => $stripePaymentMethod->card?->exp_year,
            'is_default' => $setDefault,
        ]);
    }

    /**
     * Remove a payment method.
     */
    public function removePaymentMethod(PaymentMethod $paymentMethod): void
    {
        // Detach from Stripe
        $this->stripeService->detachPaymentMethod($paymentMethod->stripe_payment_method_id);

        // Delete local record
        $paymentMethod->delete();
    }

    /**
     * Set a payment method as default.
     */
    public function setDefaultPaymentMethod(Tenant $tenant, PaymentMethod $paymentMethod): void
    {
        // Update Stripe
        $this->stripeService->setDefaultPaymentMethod($tenant->stripe_id, $paymentMethod->stripe_payment_method_id);

        // Update local records
        PaymentMethod::where('tenant_id', $tenant->id)->update(['is_default' => false]);
        $paymentMethod->update(['is_default' => true]);
    }

    /**
     * Get all payment methods for tenant.
     */
    public function getPaymentMethods(Tenant $tenant): Collection
    {
        return PaymentMethod::where('tenant_id', $tenant->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Check if card is expiring soon (within 30 days).
     */
    public function isCardExpiringSoon(PaymentMethod $paymentMethod): bool
    {
        if (!$paymentMethod->card_exp_month || !$paymentMethod->card_exp_year) {
            return false;
        }

        $expiryDate = \Carbon\Carbon::createFromDate(
            $paymentMethod->card_exp_year,
            $paymentMethod->card_exp_month,
            1
        )->endOfMonth();

        return $expiryDate->diffInDays(now()) <= 30;
    }
}
```

### Billing Controller

**File**: `app/Http/Controllers/BillingController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\InvoiceRepository;
use App\Http\Requests\Billing\AddPaymentMethodRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentMethodResource;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentMethodService;
use App\Services\Tenant\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class BillingController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly BillingService $billingService,
        private readonly PaymentMethodService $paymentMethodService,
        private readonly TenantContextService $tenantContext,
    ) {}

    /**
     * Get billing history (invoices).
     */
    public function invoices(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();
        $invoices = $this->invoices->getByTenant($tenant);

        return response()->json([
            'invoices' => InvoiceResource::collection($invoices),
        ]);
    }

    /**
     * Download invoice PDF.
     */
    public function downloadInvoice(int $invoiceId): Response
    {
        $invoice = $this->invoices->findById($invoiceId);

        if (!$invoice || $invoice->tenant_id !== $this->tenantContext->getTenant()->id) {
            abort(404);
        }

        $pdf = $this->billingService->getInvoicePdf($invoice);

        if (!$pdf) {
            abort(404);
        }

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $invoice->invoice_number . '.pdf"');
    }

    /**
     * Get payment methods.
     */
    public function paymentMethods(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();
        $methods = $this->paymentMethodService->getPaymentMethods($tenant);

        return response()->json([
            'payment_methods' => PaymentMethodResource::collection($methods),
        ]);
    }

    /**
     * Add a new payment method.
     */
    public function addPaymentMethod(AddPaymentMethodRequest $request): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        $paymentMethod = $this->paymentMethodService->addPaymentMethod(
            $tenant,
            $request->getPaymentMethodId(),
            $request->getSetAsDefault()
        );

        return response()->json([
            'payment_method' => new PaymentMethodResource($paymentMethod),
        ], 201);
    }

    /**
     * Remove a payment method.
     */
    public function removePaymentMethod(int $paymentMethodId): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        $paymentMethod = \App\Models\PaymentMethod::where('tenant_id', $tenant->id)
            ->findOrFail($paymentMethodId);

        // Cannot remove default payment method if there's an active subscription
        if ($paymentMethod->is_default && $tenant->activeSubscription()) {
            return response()->json([
                'error' => 'cannot_remove_default',
                'message' => 'Cannot remove default payment method while you have an active subscription.',
            ], 400);
        }

        $this->paymentMethodService->removePaymentMethod($paymentMethod);

        return response()->json(['success' => true]);
    }

    /**
     * Set default payment method.
     */
    public function setDefaultPaymentMethod(int $paymentMethodId): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        $paymentMethod = \App\Models\PaymentMethod::where('tenant_id', $tenant->id)
            ->findOrFail($paymentMethodId);

        $this->paymentMethodService->setDefaultPaymentMethod($tenant, $paymentMethod);

        return response()->json(['success' => true]);
    }
}
```

### Invoice PDF Template

**File**: `resources/views/invoices/pdf.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { margin-bottom: 30px; }
        .company-name { font-size: 24px; font-weight: bold; }
        .invoice-title { font-size: 18px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        .totals { margin-top: 20px; text-align: right; }
        .total-row { font-weight: bold; font-size: 14px; }
        .notes { margin-top: 30px; font-style: italic; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $company['name'] }}</div>
        <div>{{ $company['address'] }}</div>
        <div>VAT ID: {{ $company['vat_id'] }}</div>
        <div>{{ $company['email'] }}</div>
    </div>

    <div class="invoice-title">Invoice {{ $invoice->invoice_number }}</div>
    <div>Date: {{ $invoice->created_at->format('d.m.Y') }}</div>

    <div style="margin-top: 20px;">
        <strong>Bill To:</strong><br>
        {{ $invoice->customer_name }}<br>
        @if($invoice->customer_address){{ $invoice->customer_address }}<br>@endif
        @if($invoice->customer_vat_id)VAT ID: {{ $invoice->customer_vat_id }}<br>@endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Period</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->line_items as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td>
                    @if(isset($item['period_start']) && isset($item['period_end']))
                        {{ $item['period_start'] }} - {{ $item['period_end'] }}
                    @endif
                </td>
                <td>{{ $item['quantity'] }}</td>
                <td>{{ number_format($item['unit_price'], 2) }} {{ $invoice->currency }}</td>
                <td>{{ number_format($item['amount'], 2) }} {{ $invoice->currency }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div>Subtotal: {{ number_format($invoice->amount_net, 2) }} {{ $invoice->currency }}</div>
        <div>VAT ({{ number_format($invoice->vat_rate, 0) }}%): {{ number_format($invoice->vat_amount, 2) }} {{ $invoice->currency }}</div>
        <div class="total-row">Total: {{ number_format($invoice->amount_gross, 2) }} {{ $invoice->currency }}</div>
    </div>

    @if($invoice->notes)
    <div class="notes">{{ $invoice->notes }}</div>
    @endif

    <div style="margin-top: 40px;">
        <strong>Status:</strong> {{ ucfirst($invoice->status) }}
        @if($invoice->paid_at)
            (Paid on {{ $invoice->paid_at->format('d.m.Y') }})
        @endif
    </div>
</body>
</html>
```

## API Specification

### Billing Endpoints

```yaml
/api/billing/invoices:
  get:
    summary: Get billing history
    security:
      - bearerAuth: []
    responses:
      200:
        description: List of invoices

/api/billing/invoices/{id}/download:
  get:
    summary: Download invoice PDF
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
    responses:
      200:
        description: PDF file
        content:
          application/pdf: {}
      404:
        description: Invoice not found

/api/billing/payment-methods:
  get:
    summary: Get payment methods
    security:
      - bearerAuth: []
    responses:
      200:
        description: List of payment methods

  post:
    summary: Add payment method
    security:
      - bearerAuth: []
    requestBody:
      required: true
      content:
        application/json:
          schema:
            type: object
            required:
              - payment_method_id
            properties:
              payment_method_id:
                type: string
              set_as_default:
                type: boolean
                default: true
    responses:
      201:
        description: Payment method added

/api/billing/payment-methods/{id}:
  delete:
    summary: Remove payment method
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
    responses:
      200:
        description: Payment method removed
      400:
        description: Cannot remove default method

/api/billing/payment-methods/{id}/default:
  post:
    summary: Set default payment method
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
    responses:
      200:
        description: Default payment method set
```

## Testing Strategy

### E2E Test
- `TestBillingInvoicing` covering invoice creation, PDF generation, VAT calculation
- Verify: Invoice number sequential, VAT calculated correctly, PDF downloadable

### Manual Verification
- Create subscription and verify invoice generated
- Download PDF and check formatting
- Test VAT for different countries

## Implementation Steps

1. **Small** - Create Invoice model with PHPDoc annotations
2. **Small** - Create PaymentMethod model with PHPDoc annotations
3. **Medium** - Create InvoiceRepository contract and implementation
4. **Large** - Create VatService with VIES validation
5. **Medium** - Create VatServiceContract interface
6. **Large** - Create BillingService with invoice generation
7. **Medium** - Create PaymentMethodService
8. **Medium** - Create BillingController with all endpoints
9. **Medium** - Create invoice PDF Blade template
10. **Small** - Install DomPDF: `composer require barryvdh/laravel-dompdf`
11. **Medium** - Create form requests for billing endpoints
12. **Medium** - Create API resources for invoices and payment methods
13. **Small** - Register service bindings
14. **Small** - Add billing routes to api.php
15. **Medium** - Write unit tests for VAT calculations
16. **Medium** - Write feature tests for billing endpoints
17. **Small** - Run Pint and verify code style

## Cross-Task Dependencies

- **Depends on**: `database_schema.md`, `backend_stripe_integration.md`, `backend_subscription_service.md`
- **Blocks**: `backend_webhook_handling.md`, `frontend_billing_page.md`
- **Parallel work**: Can work alongside `backend_usage_limit_enforcement.md`
