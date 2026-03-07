<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Enums\ClientRiskLevel;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

final class ClientRiskLevelResolver
{
    public function resolve(Client $client): ClientRiskLevel
    {
        if ($client->is_blacklisted || $client->no_show_count >= 2 || $client->late_cancellation_count >= 3) {
            return ClientRiskLevel::High;
        }

        if ($client->no_show_count >= 1 || $client->late_cancellation_count >= 2) {
            return ClientRiskLevel::Medium;
        }

        return ClientRiskLevel::Low;
    }

    /**
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function applyFilter(Builder $query, ClientRiskLevel $riskLevel): Builder
    {
        return match ($riskLevel) {
            ClientRiskLevel::High => $query->where(static function (Builder $builder): void {
                $builder->where('is_blacklisted', true)
                    ->orWhere('no_show_count', '>=', 2)
                    ->orWhere('late_cancellation_count', '>=', 3);
            }),
            ClientRiskLevel::Medium => $query
                ->where('is_blacklisted', false)
                ->where(static function (Builder $builder): void {
                    $builder->where('no_show_count', '>=', 1)
                        ->orWhere('late_cancellation_count', '>=', 2);
                })
                ->where(static function (Builder $builder): void {
                    $builder->where('no_show_count', '<', 2)
                        ->where('late_cancellation_count', '<', 3);
                }),
            ClientRiskLevel::Low => $query
                ->where('is_blacklisted', false)
                ->where('no_show_count', '<', 1)
                ->where('late_cancellation_count', '<', 2),
        };
    }
}
