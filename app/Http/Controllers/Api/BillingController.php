<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Billing\BillingPortalSessionCreateAction;
use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Services\BillingService;
use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreatePortalSessionRequest;
use App\Http\Resources\InvoiceResource;
use App\Services\Tenant\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\PaymentMethod as CashierPaymentMethod;

final class BillingController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly BillingService $billingService,
        private readonly TenantContextService $tenantContext,
    ) {}

    /**
     * Get billing history (invoices).
     */
    public function invoices(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $invoices = $this->invoices->getByTenant($tenant);

        return response()->json([
            'data' => InvoiceResource::collection($invoices),
        ]);
    }

    /**
     * Get a single invoice.
     */
    public function showInvoice(int $invoiceId): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null || $invoice->tenant_id !== $tenant->id) {
            return response()->json(['error' => 'Invoice not found.'], 404);
        }

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Download invoice PDF.
     */
    public function downloadInvoice(int $invoiceId): Response|JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null || $invoice->tenant_id !== $tenant->id) {
            return response()->json(['error' => 'Invoice not found.'], 404);
        }

        $pdf = $this->billingService->getInvoicePdf($invoice);

        if ($pdf === null) {
            return response()->json(['error' => 'PDF could not be generated.'], 500);
        }

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$invoice->invoice_number.'.pdf"');
    }

    /**
     * Get payment methods from Stripe (default method only).
     */
    public function paymentMethods(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        try {
            $defaultPaymentMethod = $tenant->getDefaultPaymentMethod();
        } catch (\Throwable $exception) {
            Log::warning('Unable to fetch default payment method from Stripe.', [
                'tenant_id' => $tenant->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['data' => []]);
        }

        if ($defaultPaymentMethod === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => [$this->mapDefaultPaymentMethod($defaultPaymentMethod)],
        ]);
    }

    /**
     * Create a Stripe billing portal session.
     */
    public function createPortalSession(
        CreatePortalSessionRequest $request,
        BillingPortalSessionCreateAction $action,
    ): JsonResponse {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        try {
            $portalSession = $action->handle($tenant, $request->getReturnUrl());
        } catch (BillingException $exception) {
            return response()->json(
                ['error' => $exception->getMessage()],
                $exception->getStatusCode(),
            );
        }

        return response()->json([
            'data' => $portalSession->toArray(),
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     stripe_payment_method_id: string,
     *     type: string,
     *     card_brand: string,
     *     card_last4: string,
     *     card_exp_month: int,
     *     card_exp_year: int,
     *     is_default: bool
     * }
     */
    private function mapDefaultPaymentMethod(CashierPaymentMethod $paymentMethod): array
    {
        $stripePaymentMethod = $paymentMethod->asStripePaymentMethod();
        $card = $stripePaymentMethod->card;

        if ($card === null) {
            return [
                'id' => 1,
                'stripe_payment_method_id' => (string) $stripePaymentMethod->id,
                'type' => (string) $stripePaymentMethod->type,
                'card_brand' => '',
                'card_last4' => '',
                'card_exp_month' => 0,
                'card_exp_year' => 0,
                'is_default' => true,
            ];
        }

        return [
            'id' => 1,
            'stripe_payment_method_id' => (string) $stripePaymentMethod->id,
            'type' => (string) $stripePaymentMethod->type,
            'card_brand' => (string) $card->brand,
            'card_last4' => (string) $card->last4,
            'card_exp_month' => (int) $card->exp_month,
            'card_exp_year' => (int) $card->exp_year,
            'is_default' => true,
        ];
    }
}
