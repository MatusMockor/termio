<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
final readonly class UpdatePlanDTO
{
    /**
     * @param  array{monthly: float|null, yearly: float|null}|null  $pricing
     * @param  array<string, mixed>|null  $features
     * @param  array<string, mixed>|null  $limits
     * @param  array{is_active: bool|null, is_public: bool|null, sort_order: int|null}|null  $visibility
     * @param  array{monthly_price_id: string|null, yearly_price_id: string|null}|null  $stripeConfig
     */
    public function __construct(
        public ?string $name,
        public ?string $slug,
        public ?string $description,
        public ?array $pricing,
        public ?array $features,
        public ?array $limits,
        public ?array $visibility,
        public ?array $stripeConfig,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->buildCoreData();
        $pricingData = $this->buildPricingData();
        $visibilityData = $this->buildVisibilityData();
        $stripeData = $this->buildStripeData();

        return array_merge($data, $pricingData, $visibilityData, $stripeData);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCoreData(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->slug !== null) {
            $data['slug'] = $this->slug;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->features !== null) {
            $data['features'] = $this->features;
        }

        if ($this->limits !== null) {
            $data['limits'] = $this->limits;
        }

        return $data;
    }

    /**
     * @return array<string, float>
     */
    private function buildPricingData(): array
    {
        $data = [];

        if ($this->pricing === null) {
            return $data;
        }

        $monthly = $this->pricing['monthly'] ?? null;
        $yearly = $this->pricing['yearly'] ?? null;

        if ($monthly !== null) {
            $data['monthly_price'] = $monthly;
        }

        if ($yearly !== null) {
            $data['yearly_price'] = $yearly;
        }

        return $data;
    }

    /**
     * @return array<string, bool|int>
     */
    private function buildVisibilityData(): array
    {
        $data = [];

        if ($this->visibility === null) {
            return $data;
        }

        $isActive = $this->visibility['is_active'] ?? null;
        $isPublic = $this->visibility['is_public'] ?? null;
        $sortOrder = $this->visibility['sort_order'] ?? null;

        if ($isActive !== null) {
            $data['is_active'] = $isActive;
        }

        if ($isPublic !== null) {
            $data['is_public'] = $isPublic;
        }

        if ($sortOrder !== null) {
            $data['sort_order'] = $sortOrder;
        }

        return $data;
    }

    /**
     * @return array<string, string|null>
     */
    private function buildStripeData(): array
    {
        if ($this->stripeConfig === null) {
            return [];
        }

        $data = [];

        if (array_key_exists('monthly_price_id', $this->stripeConfig)) {
            $data['stripe_monthly_price_id'] = $this->stripeConfig['monthly_price_id'];
        }

        if (array_key_exists('yearly_price_id', $this->stripeConfig)) {
            $data['stripe_yearly_price_id'] = $this->stripeConfig['yearly_price_id'];
        }

        return $data;
    }
}
