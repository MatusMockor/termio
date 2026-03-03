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
        $existing = $this->invoices->findByStripeId($stripeInvoice->id);
        if ($existing !== null) {
            return $existing;
        }

        $netAmount = $this->convertCentsToAmount($stripeInvoice->subtotal);
        $vatCalculation = $this->vatService->calculateVat($tenant, $netAmount);
        $lineItems = $this->extractLineItems($stripeInvoice);
        $isPaid = $this->isInvoicePaid($stripeInvoice);

        $invoiceData = $this->buildInvoiceData(
            $tenant,
            $subscription,
            $stripeInvoice,
            $vatCalculation,
            $lineItems,
            $isPaid
        );

        $invoice = $this->invoices->create($invoiceData);
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
            $items[] = $this->buildLineItem($line);
        }

        return $items;
    }

    /**
     * @return array{description: string, quantity: int, unit_price: float, amount: float, period_start: string|null, period_end: string|null}
     */
    private function buildLineItem(object $line): array
    {
        $periodStart = $line->period->start ?? 0;
        $periodEnd = $line->period->end ?? 0;

        return [
            'description' => $line->description ?? 'Subscription',
            'quantity' => $line->quantity ?? 1,
            'unit_price' => $this->convertCentsToAmount($line->unit_amount ?? 0),
            'amount' => $this->convertCentsToAmount($line->amount),
            'period_start' => $this->convertTimestampToDate($periodStart),
            'period_end' => $this->convertTimestampToDate($periodEnd),
        ];
    }

    private function convertCentsToAmount(int $cents): float
    {
        return $cents / 100;
    }

    private function convertTimestampToDate(int $timestamp): ?string
    {
        if ($timestamp <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp($timestamp)->toDateString();
    }

    private function isInvoicePaid(StripeInvoice $stripeInvoice): bool
    {
        return $stripeInvoice->paid ?? false;
    }

    /**
     * @param  array<int, array{description: string, quantity: int, unit_price: float, amount: float, period_start: string|null, period_end: string|null}>  $lineItems
     * @return array{tenant_id: int, subscription_id: int, stripe_invoice_id: string, invoice_number: string, amount_net: float, vat_rate: float, vat_amount: float, amount_gross: float, currency: string, customer_name: string, customer_address: string|null, customer_country: string|null, customer_vat_id: string|null, line_items: array<int, array{description: string, quantity: int, unit_price: float, amount: float, period_start: string|null, period_end: string|null}>, status: string, paid_at: \Illuminate\Support\Carbon|null, notes: string|null, billing_period_start: string|null, billing_period_end: string|null}
     */
    private function buildInvoiceData(
        Tenant $tenant,
        Subscription $subscription,
        StripeInvoice $stripeInvoice,
        \App\DTOs\Billing\VatCalculation $vatCalculation,
        array $lineItems,
        bool $isPaid
    ): array {
        return [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => $stripeInvoice->id,
            'invoice_number' => $this->invoices->getNextInvoiceNumber(),
            'amount_net' => $vatCalculation->netAmount,
            'vat_rate' => $vatCalculation->vatRate * 100,
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
            'billing_period_start' => $this->convertTimestampToDate($stripeInvoice->period_start ?? 0),
            'billing_period_end' => $this->convertTimestampToDate($stripeInvoice->period_end ?? 0),
        ];
    }
}
