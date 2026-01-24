<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

final class AddPaymentMethodRequest extends FormRequest
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
            'payment_method_id' => ['required', 'string', 'max:255'],
            'set_as_default' => ['nullable', 'boolean'],
        ];
    }

    public function getPaymentMethodId(): string
    {
        return $this->validated('payment_method_id');
    }

    public function shouldSetAsDefault(): bool
    {
        return (bool) ($this->validated('set_as_default') ?? true);
    }
}
