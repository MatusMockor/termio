<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\DTOs\Subscription\DowngradeSubscriptionDTO;
use App\Services\Tenant\TenantContextService;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

final class DowngradeSubscriptionRequest extends FormRequest
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
        ];
    }

    public function getPlanId(): int
    {
        return (int) $this->validated('plan_id');
    }

    public function toDTO(): DowngradeSubscriptionDTO
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

        return new DowngradeSubscriptionDTO(
            subscriptionId: $subscription !== null ? $subscription->id : 0,
            newPlanId: $this->getPlanId(),
        );
    }
}
