<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerOffer extends Model
{
    protected $fillable = [
        'seller_account_id', 'product_variant_id', 'seller_sku', 'condition',
        'price', 'dispatch_sla_days', 'status',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }
}
