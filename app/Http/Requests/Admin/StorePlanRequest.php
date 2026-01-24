<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\Admin\CreatePlanDTO;
use Illuminate\Foundation\Http\FormRequest;

final class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', 'max:50', 'unique:plans,slug'],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'features' => ['required', 'array'],
            'limits' => ['required', 'array'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
            'stripe_monthly_price_id' => ['nullable', 'string', 'max:255'],
            'stripe_yearly_price_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function getName(): string
    {
        return $this->validated('name');
    }

    public function getSlug(): string
    {
        return $this->validated('slug');
    }

    public function getDescription(): ?string
    {
        return $this->validated('description');
    }

    public function getMonthlyPrice(): float
    {
        return (float) $this->validated('monthly_price');
    }

    public function getYearlyPrice(): float
    {
        return (float) $this->validated('yearly_price');
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeatures(): array
    {
        return $this->validated('features');
    }

    /**
     * @return array<string, mixed>
     */
    public function getLimits(): array
    {
        return $this->validated('limits');
    }

    public function getSortOrder(): int
    {
        return (int) $this->validated('sort_order');
    }

    public function isActive(): bool
    {
        return (bool) ($this->validated('is_active') ?? true);
    }

    public function isPublic(): bool
    {
        return (bool) ($this->validated('is_public') ?? true);
    }

    public function getStripeMonthlyPriceId(): ?string
    {
        return $this->validated('stripe_monthly_price_id');
    }

    public function getStripeYearlyPriceId(): ?string
    {
        return $this->validated('stripe_yearly_price_id');
    }

    public function toDTO(): CreatePlanDTO
    {
        return new CreatePlanDTO(
            name: $this->getName(),
            slug: $this->getSlug(),
            description: $this->getDescription(),
            pricing: [
                'monthly' => $this->getMonthlyPrice(),
                'yearly' => $this->getYearlyPrice(),
            ],
            features: $this->getFeatures(),
            limits: $this->getLimits(),
            visibility: [
                'is_active' => $this->isActive(),
                'is_public' => $this->isPublic(),
                'sort_order' => $this->getSortOrder(),
            ],
            stripeConfig: [
                'monthly_price_id' => $this->getStripeMonthlyPriceId(),
                'yearly_price_id' => $this->getStripeYearlyPriceId(),
            ],
        );
    }
}
