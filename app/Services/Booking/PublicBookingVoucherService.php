<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Services\Voucher\VoucherLedgerService;
use App\Services\Voucher\VoucherValidationService;

final class PublicBookingVoucherService
{
    public function __construct(
        private readonly VoucherValidationService $voucherValidationService,
        private readonly VoucherLedgerService $voucherLedgerService,
    ) {}

    public function findRedeemableVoucher(Tenant $tenant, string $voucherCode): Voucher
    {
        return $this->voucherValidationService->findRedeemableVoucher($tenant, $voucherCode);
    }

    public function redeemForAppointment(Voucher $voucher, Appointment $appointment, float $servicePrice): float
    {
        return $this->voucherLedgerService->redeemForAppointment($voucher, $appointment, $servicePrice);
    }
}
