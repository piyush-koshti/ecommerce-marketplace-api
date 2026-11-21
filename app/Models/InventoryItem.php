<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'seller_offer_id', 'quantity_on_hand', 'quantity_reserved', 'quantity_damaged',
        'quantity_returned', 'quantity_in_transit', 'low_stock_threshold', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer', 'quantity_reserved' => 'integer',
            'quantity_damaged' => 'integer', 'quantity_returned' => 'integer',
            'quantity_in_transit' => 'integer', 'low_stock_threshold' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(SellerOffer::class, 'seller_offer_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved - $this->quantity_damaged);
    }
}
