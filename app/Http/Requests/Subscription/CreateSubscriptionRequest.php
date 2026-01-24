<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Services\Tenant\TenantContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

final class CreateSubscriptionRequest extends FormRequest
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
            'billing_cycle' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'payment_method_id' => ['nullable', 'string', 'max:255'],
            'start_trial' => ['nullable', 'boolean'],
        ];
    }

    public function getPlanId(): int
    {
        return (int) $this->validated('plan_id');
    }

    public function getBillingCycle(): string
    {
        return $this->validated('billing_cycle');
    }

    public function getPaymentMethodId(): ?string
    {
        return $this->validated('payment_method_id');
    }

    public function shouldStartTrial(): bool
    {
        return (bool) ($this->validated('start_trial') ?? true);
    }

    public function toDTO(): CreateSubscriptionDTO
    {
        /** @var TenantContextService $tenantContext */
        $tenantContext = app(TenantContextService::class);
        $tenant = $tenantContext->getTenant();

        if (! $tenant) {
            throw new RuntimeException('Tenant context not available.');
        }

        return new CreateSubscriptionDTO(
            tenantId: $tenant->id,
            planId: $this->getPlanId(),
            billingCycle: $this->getBillingCycle(),
            paymentMethodId: $this->getPaymentMethodId(),
            startTrial: $this->shouldStartTrial(),
        );
    }
}
