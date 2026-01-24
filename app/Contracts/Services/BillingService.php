<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Stripe\Invoice as StripeInvoice;

interface BillingService
{
    /**
     * Create an invoice from a Stripe invoice.
     */
    public function createInvoiceFromStripe(
        Tenant $tenant,
        Subscription $subscription,
        StripeInvoice $stripeInvoice
    ): Invoice;

    /**
     * Generate PDF for an invoice.
     *
     * @return string The PDF file path
     */
    public function generateInvoicePdf(Invoice $invoice): string;

    /**
     * Get invoice PDF contents.
     */
    public function getInvoicePdf(Invoice $invoice): ?string;

    /**
     * Get company details for invoice.
     *
     * @return array{name: string, address: string, vat_id: string, email: string}
     */
    public function getCompanyDetails(): array;
}
