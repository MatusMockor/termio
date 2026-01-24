<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\Admin\UpdatePlanDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePlanRequest extends FormRequest
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
        $planId = $this->route('plan')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:50'],
            'slug' => ['sometimes', 'string', 'max:50', Rule::unique('plans', 'slug')->ignore($planId)],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'features' => ['sometimes', 'array'],
            'limits' => ['sometimes', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'stripe_monthly_price_id' => ['nullable', 'string', 'max:255'],
            'stripe_yearly_price_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function getName(): ?string
    {
        return $this->validated('name');
    }

    public function getSlug(): ?string
    {
        return $this->validated('slug');
    }

    public function getDescription(): ?string
    {
        return $this->validated('description');
    }

    public function getMonthlyPrice(): ?float
    {
        $value = $this->validated('monthly_price');

        return $value !== null ? (float) $value : null;
    }

    public function getYearlyPrice(): ?float
    {
        $value = $this->validated('yearly_price');

        return $value !== null ? (float) $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFeatures(): ?array
    {
        return $this->validated('features');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLimits(): ?array
    {
        return $this->validated('limits');
    }

    public function getSortOrder(): ?int
    {
        $value = $this->validated('sort_order');

        return $value !== null ? (int) $value : null;
    }

    public function isActive(): ?bool
    {
        $value = $this->validated('is_active');

        return $value !== null ? (bool) $value : null;
    }

    public function isPublic(): ?bool
    {
        $value = $this->validated('is_public');

        return $value !== null ? (bool) $value : null;
    }

    public function getStripeMonthlyPriceId(): ?string
    {
        return $this->validated('stripe_monthly_price_id');
    }

    public function getStripeYearlyPriceId(): ?string
    {
        return $this->validated('stripe_yearly_price_id');
    }

    public function toDTO(): UpdatePlanDTO
    {
        return new UpdatePlanDTO(
            name: $this->getName(),
            slug: $this->getSlug(),
            description: $this->getDescription(),
            pricing: $this->buildPricingArray(),
            features: $this->getFeatures(),
            limits: $this->getLimits(),
            visibility: $this->buildVisibilityArray(),
            stripeConfig: $this->buildStripeConfigArray(),
        );
    }

    /**
     * @return array{monthly: float|null, yearly: float|null}|null
     */
    private function buildPricingArray(): ?array
    {
        $monthly = $this->getMonthlyPrice();
        $yearly = $this->getYearlyPrice();

        if ($monthly === null && $yearly === null) {
            return null;
        }

        return [
            'monthly' => $monthly,
            'yearly' => $yearly,
        ];
    }

    /**
     * @return array{is_active: bool|null, is_public: bool|null, sort_order: int|null}|null
     */
    private function buildVisibilityArray(): ?array
    {
        $isActive = $this->isActive();
        $isPublic = $this->isPublic();
        $sortOrder = $this->getSortOrder();

        if ($isActive === null && $isPublic === null && $sortOrder === null) {
            return null;
        }

        return [
            'is_active' => $isActive,
            'is_public' => $isPublic,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array{monthly_price_id: string|null, yearly_price_id: string|null}|null
     */
    private function buildStripeConfigArray(): ?array
    {
        $monthlyPriceId = $this->getStripeMonthlyPriceId();
        $yearlyPriceId = $this->getStripeYearlyPriceId();

        if ($monthlyPriceId === null && $yearlyPriceId === null) {
            return null;
        }

        return [
            'monthly_price_id' => $monthlyPriceId,
            'yearly_price_id' => $yearlyPriceId,
        ];
    }
}
