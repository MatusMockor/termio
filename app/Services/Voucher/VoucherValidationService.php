<?php

declare(strict_types=1);

namespace App\Services\Voucher;

use App\Models\Service;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Validation\ValidationException;

final class VoucherValidationService
{
    public function findRedeemableVoucher(Tenant $tenant, string $code): Voucher
    {
        $voucher = Voucher::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->first();

        if ($voucher === null) {
            throw ValidationException::withMessages([
                'code' => ['Voucher code is invalid.'],
            ]);
        }

        if (! $voucher->isRedeemable()) {
            throw ValidationException::withMessages([
                'code' => ['Voucher is inactive, expired or has no remaining balance.'],
            ]);
        }

        return $voucher;
    }

    /**
     * @return array{valid: bool, discount_amount: float, remaining_balance: float, expires_at: string|null}
     */
    public function validateForService(Tenant $tenant, string $code, int $serviceId): array
    {
        $voucher = $this->findRedeemableVoucher($tenant, $code);

        $service = Service::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($serviceId);

        $servicePrice = (float) $service->price;
        $remainingBalance = (float) $voucher->balance_amount;
        $discountAmount = min($remainingBalance, $servicePrice);

        return [
            'valid' => true,
            'discount_amount' => round($discountAmount, 2),
            'remaining_balance' => round($remainingBalance - $discountAmount, 2),
            'expires_at' => $voucher->expires_at?->toIso8601String(),
        ];
    }
}
