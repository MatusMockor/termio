<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\VoucherStatus;
use App\Http\Requests\Voucher\AdjustVoucherBalanceRequest;
use App\Http\Requests\Voucher\StoreVoucherRequest;
use App\Http\Resources\VoucherResource;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Services\Voucher\VoucherLedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class VoucherController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        $vouchers = Voucher::orderByDesc('id')->paginate((int) request()->integer('per_page', 20));

        return VoucherResource::collection($vouchers);
    }

    public function store(StoreVoucherRequest $request, VoucherLedgerService $ledgerService): VoucherResource
    {
        $tenant = $this->resolveTenantOrFail($request);
        $payload = $request->getVoucherData();
        $initialAmount = (float) $payload['initial_amount'];
        $issuerId = $request->user()?->id;

        $voucher = DB::transaction(function () use ($tenant, $payload, $initialAmount, $issuerId, $ledgerService): Voucher {
            $voucher = Voucher::create([
                'tenant_id' => $tenant->id,
                'code' => $this->resolveCode($tenant->id, $payload['code']),
                'initial_amount' => $initialAmount,
                'balance_amount' => $initialAmount,
                'currency' => $payload['currency'],
                'expires_at' => $payload['expires_at'],
                'status' => $payload['status'],
                'issued_to_name' => $payload['issued_to_name'],
                'issued_to_email' => $payload['issued_to_email'],
                'note' => $payload['note'],
            ]);

            $ledgerService->issue($voucher, $issuerId);

            return $voucher;
        });

        return new VoucherResource($voucher);
    }

    public function show(Request $request, Voucher $voucher): VoucherResource
    {
        $this->ensureTenantOwnership($request, $voucher->tenant_id);

        return new VoucherResource($voucher);
    }

    public function deactivate(Request $request, Voucher $voucher): VoucherResource
    {
        $this->ensureTenantOwnership($request, $voucher->tenant_id);

        $voucher->update(['status' => VoucherStatus::Inactive->value]);

        return new VoucherResource($voucher->refresh());
    }

    public function adjustBalance(
        AdjustVoucherBalanceRequest $request,
        Voucher $voucher,
        VoucherLedgerService $ledgerService,
    ): VoucherResource {
        $this->ensureTenantOwnership($request, $voucher->tenant_id);

        $updated = $ledgerService->adjustBalance(
            $voucher,
            $request->getAmountInCents(),
            $request->user()?->id,
            $request->getReason(),
        );

        return new VoucherResource($updated);
    }

    private function resolveTenantOrFail(StoreVoucherRequest $request): Tenant
    {
        $tenantId = $request->user()?->tenant_id;

        return Tenant::where('id', $tenantId)->firstOrFail();
    }

    private function resolveCode(int $tenantId, mixed $inputCode): string
    {
        if (is_string($inputCode) && $inputCode !== '') {
            return $inputCode;
        }

        $prefix = (string) config('vouchers.code_prefix', 'GIFT');
        $randomLength = (int) config('vouchers.code_random_length', 8);

        do {
            $random = strtoupper(Str::random($randomLength));
            $code = $prefix.'-'.$random;
        } while (Voucher::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->exists());

        return $code;
    }

    private function ensureTenantOwnership(Request $request, int $resourceTenantId): void
    {
        $tenantId = $request->user()?->tenant_id;

        if (! is_int($tenantId) || $tenantId !== $resourceTenantId) {
            abort(404);
        }
    }
}
