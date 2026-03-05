<?php

declare(strict_types=1);

namespace App\Services\Voucher;

use App\Enums\VoucherTransactionType;
use App\Models\Appointment;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VoucherLedgerService
{
    public function issue(Voucher $voucher, ?int $createdByUserId = null): void
    {
        $alreadyIssued = VoucherTransaction::where('voucher_id', $voucher->id)
            ->where('type', VoucherTransactionType::Issue->value)
            ->exists();

        if ($alreadyIssued) {
            return;
        }

        VoucherTransaction::create([
            'voucher_id' => $voucher->id,
            'type' => VoucherTransactionType::Issue->value,
            'amount' => $voucher->initial_amount,
            'metadata' => null,
            'created_by_user_id' => $createdByUserId,
        ]);
    }

    public function redeemForAppointment(
        Voucher $voucher,
        Appointment $appointment,
        float $servicePrice,
        ?int $createdByUserId = null,
    ): float {
        return DB::transaction(function () use ($voucher, $appointment, $servicePrice, $createdByUserId): float {
            $existing = VoucherTransaction::where('voucher_id', $voucher->id)
                ->where('appointment_id', $appointment->id)
                ->where('type', VoucherTransactionType::Redeem->value)
                ->first();

            if ($existing !== null) {
                return (float) $existing->amount;
            }

            if (! $voucher->isRedeemable()) {
                throw ValidationException::withMessages([
                    'voucher_code' => ['Voucher is inactive, expired or has no remaining balance.'],
                ]);
            }

            $discount = round(min((float) $voucher->balance_amount, $servicePrice), 2);

            if ($discount <= 0) {
                throw ValidationException::withMessages([
                    'voucher_code' => ['Voucher has no remaining balance.'],
                ]);
            }

            $newBalance = round(max(0, (float) $voucher->balance_amount - $discount), 2);
            $voucher->update(['balance_amount' => $newBalance]);

            VoucherTransaction::create([
                'voucher_id' => $voucher->id,
                'appointment_id' => $appointment->id,
                'type' => VoucherTransactionType::Redeem->value,
                'amount' => $discount,
                'metadata' => [
                    'service_price' => $servicePrice,
                ],
                'created_by_user_id' => $createdByUserId,
            ]);

            return $discount;
        });
    }

    public function restoreForCancelledAppointment(Appointment $appointment, ?int $createdByUserId = null): void
    {
        if ($appointment->voucher_id === null) {
            return;
        }

        $discountAmount = (float) $appointment->voucher_discount_amount;

        if ($discountAmount <= 0) {
            return;
        }

        DB::transaction(function () use ($appointment, $discountAmount, $createdByUserId): void {
            $voucher = Voucher::find($appointment->voucher_id);

            if ($voucher === null) {
                return;
            }

            $hasRedeemTransaction = VoucherTransaction::where('voucher_id', $voucher->id)
                ->where('appointment_id', $appointment->id)
                ->where('type', VoucherTransactionType::Redeem->value)
                ->exists();

            if (! $hasRedeemTransaction) {
                return;
            }

            $hasRestoreTransaction = VoucherTransaction::where('voucher_id', $voucher->id)
                ->where('appointment_id', $appointment->id)
                ->where('type', VoucherTransactionType::Restore->value)
                ->exists();

            if ($hasRestoreTransaction) {
                return;
            }

            $voucher->update([
                'balance_amount' => round((float) $voucher->balance_amount + $discountAmount, 2),
            ]);

            VoucherTransaction::create([
                'voucher_id' => $voucher->id,
                'appointment_id' => $appointment->id,
                'type' => VoucherTransactionType::Restore->value,
                'amount' => $discountAmount,
                'metadata' => null,
                'created_by_user_id' => $createdByUserId,
            ]);
        });
    }

    public function adjustBalance(
        Voucher $voucher,
        float $amount,
        ?int $createdByUserId = null,
        ?string $reason = null,
    ): Voucher {
        if ($amount === 0.0) {
            throw ValidationException::withMessages([
                'amount' => ['Adjustment amount must not be zero.'],
            ]);
        }

        return DB::transaction(function () use ($voucher, $amount, $createdByUserId, $reason): Voucher {
            $newBalance = round((float) $voucher->balance_amount + $amount, 2);

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Adjustment would make voucher balance negative.'],
                ]);
            }

            $voucher->update(['balance_amount' => $newBalance]);

            VoucherTransaction::create([
                'voucher_id' => $voucher->id,
                'type' => VoucherTransactionType::Adjust->value,
                'amount' => $amount,
                'metadata' => [
                    'reason' => $reason,
                ],
                'created_by_user_id' => $createdByUserId,
            ]);

            return $voucher->refresh();
        });
    }
}
