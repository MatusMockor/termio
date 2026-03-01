<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Billing\BillingPortalSessionCreateAction;
use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Services\BillingService;
use App\Contracts\Services\PaymentMethodServiceContract;
use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\AddPaymentMethodRequest;
use App\Http\Requests\Billing\CreatePortalSessionRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\Tenant\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class BillingController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly BillingService $billingService,
        private readonly PaymentMethodServiceContract $paymentMethodService,
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
     * Get payment methods.
     *
     * @deprecated This endpoint will be removed after frontend fully migrates to Stripe Customer Portal.
     */
    public function paymentMethods(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $methods = $this->paymentMethodService->getPaymentMethods($tenant);

        return response()->json([
            'data' => PaymentMethodResource::collection($methods),
        ]);
    }

    /**
     * Add a new payment method.
     *
     * @deprecated This endpoint will be removed after frontend fully migrates to Stripe Customer Portal.
     */
    public function addPaymentMethod(AddPaymentMethodRequest $request): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        if ($tenant->stripe_id === null) {
            return response()->json(['error' => 'No Stripe customer found. Please contact support.'], 400);
        }

        $paymentMethodId = $request->getPaymentMethodId();
        $paymentMethod = $request->shouldSetAsDefault()
            ? $this->paymentMethodService->addPaymentMethod($tenant, $paymentMethodId)
            : $this->paymentMethodService->addPaymentMethodWithoutDefault($tenant, $paymentMethodId);

        return response()->json([
            'data' => new PaymentMethodResource($paymentMethod),
            'message' => 'Payment method added successfully.',
        ], 201);
    }

    /**
     * Remove a payment method.
     *
     * @deprecated This endpoint will be removed after frontend fully migrates to Stripe Customer Portal.
     */
    public function removePaymentMethod(int $paymentMethodId): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        $paymentMethod = PaymentMethod::where('tenant_id', $tenant->id)
            ->find($paymentMethodId);

        if ($paymentMethod === null) {
            return response()->json(['error' => 'Payment method not found.'], 404);
        }

        // Cannot remove default payment method if there's an active subscription
        if ($paymentMethod->is_default && $tenant->activeSubscription() !== null) {
            return response()->json([
                'error' => 'cannot_remove_default',
                'message' => 'Cannot remove default payment method while you have an active subscription.',
            ], 400);
        }

        $this->paymentMethodService->removePaymentMethod($paymentMethod);

        return response()->json([
            'message' => 'Payment method removed successfully.',
        ]);
    }

    /**
     * Set default payment method.
     *
     * @deprecated This endpoint will be removed after frontend fully migrates to Stripe Customer Portal.
     */
    public function setDefaultPaymentMethod(int $paymentMethodId): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();

        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found.'], 404);
        }

        if ($tenant->stripe_id === null) {
            return response()->json(['error' => 'No Stripe customer found. Please contact support.'], 400);
        }

        $paymentMethod = PaymentMethod::where('tenant_id', $tenant->id)
            ->find($paymentMethodId);

        if ($paymentMethod === null) {
            return response()->json(['error' => 'Payment method not found.'], 404);
        }

        $this->paymentMethodService->setDefaultPaymentMethod($tenant, $paymentMethod);

        return response()->json([
            'message' => 'Default payment method updated successfully.',
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
}
