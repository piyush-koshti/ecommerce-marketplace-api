<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    protected $fillable = [
        'inventory_item_id', 'reference_type', 'reference_id', 'quantity',
        'status', 'expires_at', 'committed_at', 'released_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'committed_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
