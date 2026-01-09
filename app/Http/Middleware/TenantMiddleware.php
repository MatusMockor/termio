<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TenantMiddleware
{
    public function __construct(
        private readonly TenantContextService $tenantContext
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            return response()->json(['message' => 'No tenant associated with user.'], 403);
        }

        if ($tenant->status === 'suspended') {
            return response()->json(['message' => 'Tenant account is suspended.'], 403);
        }

        $this->tenantContext->setTenant($tenant);

        return $next($request);
    }
}
