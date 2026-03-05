<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ServiceCategory\ReorderServiceCategoriesRequest;
use App\Http\Requests\ServiceCategory\StoreServiceCategoryRequest;
use App\Http\Requests\ServiceCategory\UpdateServiceCategoryRequest;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

final class ServiceCategoryController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ServiceCategory::ordered()->get();

        return ServiceCategoryResource::collection($categories);
    }

    public function store(StoreServiceCategoryRequest $request): ServiceCategoryResource
    {
        $tenant = $this->resolveTenantOrFail($request);

        $category = ServiceCategory::create([
            'tenant_id' => $tenant->id,
            'name' => $request->getName(),
            'slug' => $this->generateUniqueSlug($tenant->id, $request->getName()),
            'parent_id' => $request->getParentId(),
            'priority' => $request->getPriority(),
            'sort_order' => $request->getSortOrder(),
            'is_active' => $request->isActive(),
        ]);

        return new ServiceCategoryResource($category);
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $category): ServiceCategoryResource
    {
        $this->ensureTenantOwnership($request, $category->tenant_id);

        $data = array_merge(
            $this->buildNameUpdateData($request, $category),
            $this->buildParentUpdateData($request, $category),
            $this->buildPriorityUpdateData($request),
            $this->buildSortOrderUpdateData($request),
            $this->buildActiveFlagUpdateData($request),
        );

        if ($data) {
            $category->update($data);
        }

        return new ServiceCategoryResource($category->refresh());
    }

    public function destroy(Request $request, ServiceCategory $category): JsonResponse
    {
        $this->ensureTenantOwnership($request, $category->tenant_id);

        $category->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderServiceCategoriesRequest $request): JsonResponse
    {
        foreach ($request->getOrder() as $index => $id) {
            ServiceCategory::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Service categories reordered successfully.']);
    }

    private function resolveTenantOrFail(StoreServiceCategoryRequest $request): Tenant
    {
        $tenantId = $request->user()?->tenant_id;

        return Tenant::where('id', $tenantId)->firstOrFail();
    }

    private function generateUniqueSlug(int $tenantId, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'category';
        }

        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($tenantId, $slug, $ignoreId)) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(int $tenantId, string $slug, ?int $ignoreId = null): bool
    {
        $query = ServiceCategory::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function ensureTenantOwnership(Request $request, int $resourceTenantId): void
    {
        $tenantId = $request->user()?->tenant_id;

        if (! is_int($tenantId) || $tenantId !== $resourceTenantId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNameUpdateData(UpdateServiceCategoryRequest $request, ServiceCategory $category): array
    {
        if (! $request->hasName()) {
            return [];
        }

        $name = $request->getName();

        if (! is_string($name) || $name === '') {
            return [];
        }

        return [
            'name' => $name,
            'slug' => $this->generateUniqueSlug((int) $category->tenant_id, $name, $category->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParentUpdateData(UpdateServiceCategoryRequest $request, ServiceCategory $category): array
    {
        if (! $request->hasParentId()) {
            return [];
        }

        $parentId = $request->getParentId();

        return [
            'parent_id' => $parentId === $category->id ? null : $parentId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPriorityUpdateData(UpdateServiceCategoryRequest $request): array
    {
        if (! $request->hasPriority()) {
            return [];
        }

        $priority = $request->getPriority();

        if ($priority === null) {
            return [];
        }

        return ['priority' => $priority];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSortOrderUpdateData(UpdateServiceCategoryRequest $request): array
    {
        if (! $request->hasSortOrder()) {
            return [];
        }

        $sortOrder = $request->getSortOrder();

        if ($sortOrder === null) {
            return [];
        }

        return ['sort_order' => $sortOrder];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildActiveFlagUpdateData(UpdateServiceCategoryRequest $request): array
    {
        if (! $request->hasActiveFlag()) {
            return [];
        }

        $isActive = $request->isActive();

        if ($isActive === null) {
            return [];
        }

        return ['is_active' => $isActive];
    }
}
