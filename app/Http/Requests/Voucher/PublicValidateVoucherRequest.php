<?php

declare(strict_types=1);

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

final class PublicValidateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100'],
            'service_id' => ['required', 'integer'],
        ];
    }

    public function getCode(): string
    {
        return (string) $this->validated('code');
    }

    public function getServiceId(): int
    {
        return (int) $this->validated('service_id');
    }
}
