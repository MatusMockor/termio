<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\UsageLimitServiceContract;
use App\Services\Tenant\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckServiceLimit
{
    public function __construct(
        private readonly UsageLimitServiceContract $usageLimitService,
        private readonly TenantContextService $tenantContext,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return $next($request);
        }

        if (! $this->usageLimitService->canAddService($tenant)) {
            return response()->json([
                'error' => 'service_limit_exceeded',
                'message' => 'You have reached your service limit. Please upgrade your plan to add more services.',
            ], 402);
        }

        /** @var Response $response */
        $response = $next($request);

        // Add warning header if at 80% usage
        if ($this->usageLimitService->isNearLimit($tenant, 'services')) {
            $percentage = $this->usageLimitService->getUsagePercentage($tenant, 'services');
            $response->headers->set('X-Usage-Warning', sprintf('Service usage at %.0f%%', $percentage));
        }

        return $response;
    }
}
