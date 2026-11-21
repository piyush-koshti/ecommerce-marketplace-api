<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutItem extends Model
{
    protected $fillable = ['checkout_session_id', 'seller_offer_id', 'quantity', 'product_name', 'variant_name', 'seller_name', 'seller_sku', 'unit_price', 'line_total'];

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }
}
