<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $subscription_id
 * @property string|null $stripe_invoice_id
 * @property string $invoice_number
 * @property string $amount_net
 * @property string $vat_rate
 * @property string $vat_amount
 * @property string $amount_gross
 * @property string $currency
 * @property string $customer_name
 * @property string|null $customer_address
 * @property string|null $customer_country
 * @property string|null $customer_vat_id
 * @property array<int, array<string, mixed>> $line_items
 * @property string $status
 * @property Carbon|null $paid_at
 * @property string|null $pdf_path
 * @property string|null $notes
 * @property Carbon|null $billing_period_start
 * @property Carbon|null $billing_period_end
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Subscription|null $subscription
 */
final class Invoice extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'stripe_invoice_id',
        'invoice_number',
        'amount_net',
        'vat_rate',
        'vat_amount',
        'amount_gross',
        'currency',
        'customer_name',
        'customer_address',
        'customer_country',
        'customer_vat_id',
        'line_items',
        'status',
        'paid_at',
        'pdf_path',
        'notes',
        'billing_period_start',
        'billing_period_end',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_net' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'amount_gross' => 'decimal:2',
            'line_items' => 'array',
            'paid_at' => 'datetime',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }
}
