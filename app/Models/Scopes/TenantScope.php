<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\Tenant\TenantContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class TenantScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantContext = app(TenantContextService::class);

        if ($tenantContext->hasTenant()) {
            $builder->where($model->getTable().'.tenant_id', $tenantContext->getTenantId());
        }
    }
}
