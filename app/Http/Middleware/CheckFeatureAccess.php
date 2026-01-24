<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\FeatureGateServiceContract;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Services\Tenant\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckFeatureAccess
{
    public function __construct(
        private readonly FeatureGateServiceContract $featureGate,
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly TenantContextService $tenantContext,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     * @param  string  $feature  The feature key to check
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return $next($request);
        }

        if (! $this->featureGate->canAccess($tenant, $feature)) {
            $currentPlan = $this->subscriptionService->getCurrentPlan($tenant);

            return $this->featureGate->denyWithUpgradeMessage($feature, $currentPlan->slug);
        }

        return $next($request);
    }
}
