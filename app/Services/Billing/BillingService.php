<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Services\BillingService as BillingServiceContract;
use App\Contracts\Services\VatService;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Stripe\Invoice as StripeInvoice;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class BillingService implements BillingServiceContract
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly VatService $vatService,
    ) {}

    public function createInvoiceFromStripe(
        Tenant $tenant,
        Subscription $subscription,
        StripeInvoice $stripeInvoice
    ): Invoice {
        // Check if invoice already exists
        $existing = $this->invoices->findByStripeId($stripeInvoice->id);
        if ($existing !== null) {
            return $existing;
        }

        $netAmount = $stripeInvoice->subtotal / 100; // Convert from cents
        $vatCalculation = $this->vatService->calculateVat($tenant, (float) $netAmount);

        $lineItems = $this->extractLineItems($stripeInvoice);

        /** @var bool $isPaid */
        $isPaid = $stripeInvoice->paid ?? false;

        $invoice = $this->invoices->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => $stripeInvoice->id,
            'invoice_number' => $this->invoices->getNextInvoiceNumber(),
            'amount_net' => $vatCalculation->netAmount,
            'vat_rate' => $vatCalculation->vatRate * 100, // Store as percentage
            'vat_amount' => $vatCalculation->vatAmount,
            'amount_gross' => $vatCalculation->grossAmount,
            'currency' => strtoupper($stripeInvoice->currency ?? 'EUR'),
            'customer_name' => $tenant->name,
            'customer_address' => $tenant->address,
            'customer_country' => $tenant->country,
            'customer_vat_id' => $tenant->vat_id,
            'line_items' => $lineItems,
            'status' => $isPaid ? 'paid' : 'open',
            'paid_at' => $isPaid ? now() : null,
            'notes' => $vatCalculation->note,
            'billing_period_start' => $stripeInvoice->period_start > 0
                ? Carbon::createFromTimestamp($stripeInvoice->period_start)->toDateString()
                : null,
            'billing_period_end' => $stripeInvoice->period_end > 0
                ? Carbon::createFromTimestamp($stripeInvoice->period_end)->toDateString()
                : null,
        ]);

        // Generate PDF
        $this->generateInvoicePdf($invoice);

        return $invoice;
    }

    public function generateInvoicePdf(Invoice $invoice): string
    {
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'company' => $this->getCompanyDetails(),
        ]);

        $filename = 'invoices/'.$invoice->invoice_number.'.pdf';
        Storage::disk('local')->put($filename, $pdf->output());

        $this->invoices->update($invoice, ['pdf_path' => $filename]);

        return $filename;
    }

    public function getInvoicePdf(Invoice $invoice): ?string
    {
        if ($invoice->pdf_path === null) {
            $this->generateInvoicePdf($invoice);
            $invoice->refresh();
        }

        if ($invoice->pdf_path === null) {
            return null;
        }

        if (! Storage::disk('local')->exists($invoice->pdf_path)) {
            $this->generateInvoicePdf($invoice);
        }

        return Storage::disk('local')->get($invoice->pdf_path);
    }

    public function getCompanyDetails(): array
    {
        return [
            'name' => (string) config('app.company_name', 'Termio s.r.o.'),
            'address' => (string) config('app.company_address', 'Address, Bratislava, Slovakia'),
            'vat_id' => (string) config('app.company_vat_id', 'SK1234567890'),
            'email' => (string) config('app.company_email', 'billing@termio.sk'),
        ];
    }

    /**
     * Extract line items from Stripe invoice.
     *
     * @return array<int, array{description: string, quantity: int, unit_price: float, amount: float, period_start: string|null, period_end: string|null}>
     */
    private function extractLineItems(StripeInvoice $stripeInvoice): array
    {
        $items = [];

        foreach ($stripeInvoice->lines->data as $line) {
            $periodStart = $line->period->start ?? 0;
            $periodEnd = $line->period->end ?? 0;

            $items[] = [
                'description' => $line->description ?? 'Subscription',
                'quantity' => $line->quantity ?? 1,
                'unit_price' => ($line->unit_amount ?? 0) / 100,
                'amount' => $line->amount / 100,
                'period_start' => $periodStart > 0
                    ? Carbon::createFromTimestamp($periodStart)->toDateString()
                    : null,
                'period_end' => $periodEnd > 0
                    ? Carbon::createFromTimestamp($periodEnd)->toDateString()
                    : null,
            ];
        }

        return $items;
    }
}
