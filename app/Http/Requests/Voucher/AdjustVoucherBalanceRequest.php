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
            'amount' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function getAmount(): float
    {
        return (float) $this->validated('amount');
    }

    public function getReason(): ?string
    {
        $value = $this->validated('reason');

        return is_string($value) ? $value : null;
    }
}
