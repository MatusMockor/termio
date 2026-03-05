<?php

declare(strict_types=1);

namespace App\Http\Requests\Voucher;

use App\Enums\VoucherStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreVoucherRequest extends FormRequest
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
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('vouchers', 'code')->where('tenant_id', $this->tenantId()),
            ],
            'initial_amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expires_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(VoucherStatus::values())],
            'issued_to_name' => ['nullable', 'string', 'max:255'],
            'issued_to_email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getVoucherData(): array
    {
        return [
            'code' => $this->validated('code'),
            'initial_amount' => (float) $this->validated('initial_amount'),
            'currency' => strtoupper((string) ($this->validated('currency') ?? config('vouchers.default_currency', 'EUR'))),
            'expires_at' => $this->validated('expires_at'),
            'status' => $this->validated('status') ?? VoucherStatus::Active->value,
            'issued_to_name' => $this->validated('issued_to_name'),
            'issued_to_email' => $this->validated('issued_to_email'),
            'note' => $this->validated('note'),
        ];
    }

    private function tenantId(): int
    {
        $tenantId = $this->user()?->tenant_id;

        return is_int($tenantId) ? $tenantId : 0;
    }
}
