<?php

namespace App\Wa\Hub\Models;

use App\Wa\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Point Purchase Transaction
 *
 * DB columns include:
 * - invoice_number, invoice_pdf_path, invoice_sent_at, gateway_meta
 */
class PointPurchase extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'point_package_id',
        'points_purchased',
        'amount_paid',
        'currency',
        'payment_gateway',
        'transaction_id',
        'status',

        'invoice_number',
        'invoice_pdf_path',
        'invoice_sent_at',
        'gateway_meta',
    ];

    protected $casts = [
        'points_purchased' => 'integer',
        'amount_paid' => 'float',
        'invoice_sent_at' => 'datetime',
        'gateway_meta' => 'array',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Vendors::class, 'restaurant_id');
    }

    public function pointPackage(): BelongsTo
    {
        return $this->belongsTo(PointPackage::class, 'point_package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* ───────────────────────── Helpers ───────────────────────── */

    public function isPaid(): bool
    {
        return in_array(strtolower((string) $this->status), ['paid', 'completed'], true);
    }

    public function isPending(): bool
    {
        return strtolower((string) $this->status) === 'pending';
    }

    public function isFailed(): bool
    {
        return in_array(strtolower((string) $this->status), ['failed', 'cancelled'], true);
    }

    public function gatewayLabel(): string
    {
        return $this->payment_gateway ?: '—';
    }

    public function gatewayField(string $key, mixed $default = null): mixed
    {
        // convenient accessor for gateway_meta
        return data_get($this->gateway_meta ?? [], $key, $default);
    }
}
