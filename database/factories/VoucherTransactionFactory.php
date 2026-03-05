<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VoucherTransactionType;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherTransaction>
 */
final class VoucherTransactionFactory extends Factory
{
    protected $model = VoucherTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_id' => Voucher::factory(),
            'appointment_id' => null,
            'type' => VoucherTransactionType::Adjust->value,
            'amount' => fake()->randomFloat(2, 1, 50),
            'metadata' => null,
            'created_by_user_id' => null,
        ];
    }
}
