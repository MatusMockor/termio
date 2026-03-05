<?php

declare(strict_types=1);

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

final class AdjustVoucherBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function getAmountInCents(): int
    {
        return $this->toCents($this->validated('amount'));
    }

    public function getReason(): ?string
    {
        $value = $this->validated('reason');

        return is_string($value) ? $value : null;
    }

    private function toCents(mixed $amount): int
    {
        $normalized = str_replace(',', '.', trim((string) $amount));
        $isNegative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $wholeDigits = preg_replace('/\D/', '', $whole);
        $fractionDigits = preg_replace('/\D/', '', $fraction);

        $wholeValue = (int) ($wholeDigits !== '' ? $wholeDigits : '0');
        $fractionValue = (int) str_pad(substr($fractionDigits, 0, 2), 2, '0');
        $value = ($wholeValue * 100) + $fractionValue;

        return $isNegative ? -$value : $value;
    }
}
