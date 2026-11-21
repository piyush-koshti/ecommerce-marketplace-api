<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['seller_order_id', 'seller_offer_id', 'product_variant_id', 'product_name', 'variant_name', 'seller_name', 'seller_sku', 'quantity', 'unit_price', 'line_total', 'tax_snapshot'];

    protected function casts(): array
    {
        return ['tax_snapshot' => 'array'];
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }
}
