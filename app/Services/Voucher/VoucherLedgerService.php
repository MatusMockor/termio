<?php

declare(strict_types=1);

namespace App\Services\Voucher;

use App\Enums\VoucherTransactionType;
use App\Models\Appointment;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use Closure;
use Illuminate\Validation\ValidationException;

final class VoucherLedgerService
{
    public function issue(Voucher $voucher, ?int $createdByUserId = null): void
    {
        $this->transaction(function () use ($voucher, $createdByUserId): void {
            $lockedVoucher = $this->lockVoucher($voucher->id, (int) $voucher->tenant_id);

            if ($lockedVoucher === null) {
                return;
            }

            $alreadyIssued = VoucherTransaction::where('voucher_id', $lockedVoucher->id)
                ->where('type', VoucherTransactionType::Issue->value)
                ->exists();

            if ($alreadyIssued) {
                return;
            }

            VoucherTransaction::create([
                'voucher_id' => $lockedVoucher->id,
                'type' => VoucherTransactionType::Issue->value,
                'amount' => $lockedVoucher->initial_amount,
                'metadata' => null,
                'created_by_user_id' => $createdByUserId,
            ]);
        });
    }

    public function redeemForAppointment(
        Voucher $voucher,
        Appointment $appointment,
        float $servicePrice,
        ?int $createdByUserId = null,
    ): float {
        return $this->transaction(function () use ($voucher, $appointment, $servicePrice, $createdByUserId): float {
            $lockedVoucher = $this->lockVoucher($voucher->id, (int) $voucher->tenant_id);

            if ($lockedVoucher === null) {
                throw ValidationException::withMessages([
                    'voucher_code' => ['Voucher is unavailable.'],
                ]);
            }

            $existing = $this->findRedeemTransaction($lockedVoucher->id, $appointment->id);

            if ($existing !== null) {
                return (float) $existing->amount;
            }

            $this->ensureVoucherIsRedeemable($lockedVoucher);

            $availableBalanceInCents = $this->toCents($lockedVoucher->balance_amount);
            $servicePriceInCents = $this->toCents($servicePrice);
            $discountInCents = $this->resolveDiscountInCents($availableBalanceInCents, $servicePriceInCents);
            $newBalanceInCents = max(0, $availableBalanceInCents - $discountInCents);
            $discountAmount = $this->fromCents($discountInCents);

            $lockedVoucher->update([
                'balance_amount' => $this->fromCents($newBalanceInCents),
            ]);

            $this->createRedeemTransaction(
                voucherId: $lockedVoucher->id,
                appointmentId: $appointment->id,
                discountAmount: $discountAmount,
                servicePriceInCents: $servicePriceInCents,
                createdByUserId: $createdByUserId,
            );

            return (float) $discountAmount;
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

        $this->transaction(function () use ($appointment, $discountAmount, $createdByUserId): void {
            $lockedVoucher = $this->lockVoucher((int) $appointment->voucher_id, (int) $appointment->tenant_id);

            if ($lockedVoucher === null) {
                return;
            }

            $hasRedeemTransaction = VoucherTransaction::where('voucher_id', $lockedVoucher->id)
                ->where('appointment_id', $appointment->id)
                ->where('type', VoucherTransactionType::Redeem->value)
                ->exists();

            if (! $hasRedeemTransaction) {
                return;
            }

            $hasRestoreTransaction = VoucherTransaction::where('voucher_id', $lockedVoucher->id)
                ->where('appointment_id', $appointment->id)
                ->where('type', VoucherTransactionType::Restore->value)
                ->exists();

            if ($hasRestoreTransaction) {
                return;
            }

            $newBalanceInCents = $this->toCents($lockedVoucher->balance_amount) + $this->toCents($discountAmount);

            $lockedVoucher->update([
                'balance_amount' => $this->fromCents($newBalanceInCents),
            ]);

            VoucherTransaction::create([
                'voucher_id' => $lockedVoucher->id,
                'appointment_id' => $appointment->id,
                'type' => VoucherTransactionType::Restore->value,
                'amount' => $this->fromCents($this->toCents($discountAmount)),
                'metadata' => null,
                'created_by_user_id' => $createdByUserId,
            ]);
        });
    }

    public function adjustBalance(
        Voucher $voucher,
        int $amountInCents,
        ?int $createdByUserId = null,
        ?string $reason = null,
    ): Voucher {
        if ($amountInCents === 0) {
            throw ValidationException::withMessages([
                'amount' => ['Adjustment amount must not be zero.'],
            ]);
        }

        return $this->transaction(function () use ($voucher, $amountInCents, $createdByUserId, $reason): Voucher {
            $lockedVoucher = $this->lockVoucher($voucher->id, (int) $voucher->tenant_id);

            if ($lockedVoucher === null) {
                throw ValidationException::withMessages([
                    'voucher' => ['Voucher not found.'],
                ]);
            }

            $currentBalanceInCents = $this->toCents($lockedVoucher->balance_amount);
            $newBalanceInCents = $currentBalanceInCents + $amountInCents;

            if ($newBalanceInCents < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Adjustment would make voucher balance negative.'],
                ]);
            }

            $lockedVoucher->update([
                'balance_amount' => $this->fromCents($newBalanceInCents),
            ]);

            VoucherTransaction::create([
                'voucher_id' => $lockedVoucher->id,
                'type' => VoucherTransactionType::Adjust->value,
                'amount' => $this->fromCents($amountInCents),
                'metadata' => [
                    'reason' => $reason,
                ],
                'created_by_user_id' => $createdByUserId,
            ]);

            return $lockedVoucher->refresh();
        });
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function transaction(Closure $callback): mixed
    {
        $connection = Voucher::resolveConnection((new Voucher)->getConnectionName());

        return $connection->transaction($callback);
    }

    private function lockVoucher(int $voucherId, int $tenantId): ?Voucher
    {
        return Voucher::withoutTenantScope()
            ->whereKey($voucherId)
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();
    }

    private function findRedeemTransaction(int $voucherId, int $appointmentId): ?VoucherTransaction
    {
        return VoucherTransaction::where('voucher_id', $voucherId)
            ->where('appointment_id', $appointmentId)
            ->where('type', VoucherTransactionType::Redeem->value)
            ->first();
    }

    private function ensureVoucherIsRedeemable(Voucher $voucher): void
    {
        if ($voucher->isRedeemable()) {
            return;
        }

        throw ValidationException::withMessages([
            'voucher_code' => ['Voucher is inactive, expired or has no remaining balance.'],
        ]);
    }

    private function resolveDiscountInCents(int $availableBalanceInCents, int $servicePriceInCents): int
    {
        $discountInCents = min($availableBalanceInCents, $servicePriceInCents);

        if ($discountInCents > 0) {
            return $discountInCents;
        }

        throw ValidationException::withMessages([
            'voucher_code' => ['Voucher has no remaining balance.'],
        ]);
    }

    private function createRedeemTransaction(
        int $voucherId,
        int $appointmentId,
        string $discountAmount,
        int $servicePriceInCents,
        ?int $createdByUserId,
    ): void {
        VoucherTransaction::create([
            'voucher_id' => $voucherId,
            'appointment_id' => $appointmentId,
            'type' => VoucherTransactionType::Redeem->value,
            'amount' => $discountAmount,
            'metadata' => [
                'service_price' => $this->fromCents($servicePriceInCents),
            ],
            'created_by_user_id' => $createdByUserId,
        ]);
    }

    private function toCents(mixed $amount): int
    {
        if (is_int($amount)) {
            return $amount * 100;
        }

        if (is_float($amount)) {
            $amount = number_format($amount, 2, '.', '');
        }

        if (! is_string($amount)) {
            return 0;
        }

        $normalized = str_replace(',', '.', trim($amount));
        $isNegative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $wholeDigits = preg_replace('/\D/', '', $whole) ?? '';
        $fractionDigits = preg_replace('/\D/', '', $fraction) ?? '';

        $wholeValue = (int) ($wholeDigits !== '' ? $wholeDigits : '0');
        $fractionValue = (int) str_pad(substr($fractionDigits, 0, 2), 2, '0');
        $value = ($wholeValue * 100) + $fractionValue;

        return $isNegative ? -$value : $value;
    }

    private function fromCents(int $amountInCents): string
    {
        $isNegative = $amountInCents < 0;
        $absolute = abs($amountInCents);
        $whole = intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return ($isNegative ? '-' : '').$whole.'.'.$fraction;
    }
}
