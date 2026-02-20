<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

final readonly class UpgradeMessageDTO
{
    public function __construct(
        public string $error,
        public string $message,
        public string $feature,
        public ?string $currentPlan,
        public RequiredPlanDTO $requiredPlan,
        public string $upgradeUrl,
    ) {}

    /**
     * @return array{
     *     error: string,
     *     message: string,
     *     feature: string,
     *     current_plan: string|null,
     *     required_plan: array{name: string|null, slug: string, monthly_price: string|null},
     *     upgrade_url: string
     * }
     */
    public function toArray(): array
    {
        return [
            'error' => $this->error,
            'message' => $this->message,
            'feature' => $this->feature,
            'current_plan' => $this->currentPlan,
            'required_plan' => $this->requiredPlan->toArray(),
            'upgrade_url' => $this->upgradeUrl,
        ];
    }
}
