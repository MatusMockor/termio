<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCheckoutSessionRequest extends FormRequest
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
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'string', Rule::in(BillingCycle::values())],
            'success_url' => ['required', 'string', 'url', 'max:2048'],
            'cancel_url' => ['required', 'string', 'url', 'max:2048'],
        ];
    }

    public function getPlanId(): int
    {
        return (int) $this->validated('plan_id');
    }

    public function getBillingCycle(): BillingCycle
    {
        return BillingCycle::from((string) $this->validated('billing_cycle'));
    }

    public function getSuccessUrl(): string
    {
        return (string) $this->validated('success_url');
    }

    public function getCancelUrl(): string
    {
        return (string) $this->validated('cancel_url');
    }
}
