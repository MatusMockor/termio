<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\UsageLimitServiceContract;
use App\Services\Tenant\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckReservationLimit
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

        if (! $this->usageLimitService->canCreateReservation($tenant)) {
            return response()->json([
                'error' => 'reservation_limit_exceeded',
                'message' => 'You have reached your monthly reservation limit. Please upgrade your plan to create more reservations.',
            ], 402);
        }

        /** @var Response $response */
        $response = $next($request);

        // Add warning header if at 80% usage
        if ($this->usageLimitService->isNearLimit($tenant, 'reservations')) {
            $percentage = $this->usageLimitService->getUsagePercentage($tenant, 'reservations');
            $response->headers->set('X-Usage-Warning', sprintf('Reservation usage at %.0f%%', $percentage));
        }

        return $response;
    }
}
