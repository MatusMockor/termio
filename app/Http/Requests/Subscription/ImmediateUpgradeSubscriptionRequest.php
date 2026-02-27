<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\DTOs\Subscription\ImmediateUpgradeSubscriptionDTO;
use App\Services\Tenant\TenantContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

final class ImmediateUpgradeSubscriptionRequest extends FormRequest
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
            'billing_cycle' => ['nullable', 'string', Rule::in(['monthly', 'yearly'])],
        ];
    }

    public function getPlanId(): int
    {
        return (int) $this->validated('plan_id');
    }

    public function getBillingCycle(): ?string
    {
        return $this->validated('billing_cycle');
    }

    public function toDTO(): ImmediateUpgradeSubscriptionDTO
    {
        /** @var TenantContextService $tenantContext */
        $tenantContext = app(TenantContextService::class);
        $tenant = $tenantContext->getTenant();

        if (! $tenant) {
            throw new RuntimeException('Tenant context not available.');
        }

        /** @var SubscriptionRepository $subscriptionRepository */
        $subscriptionRepository = app(SubscriptionRepository::class);
        $subscription = $subscriptionRepository->findActiveByTenant($tenant);

        return new ImmediateUpgradeSubscriptionDTO(
            subscriptionId: $subscription !== null ? $subscription->id : 0,
            newPlanId: $this->getPlanId(),
            billingCycle: $this->getBillingCycle(),
        );
    }
}
