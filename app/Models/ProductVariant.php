<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'name', 'attribute_values', 'barcode', 'weight_grams',
        'length_mm', 'width_mm', 'height_mm', 'is_active',
    ];

    protected function casts(): array
    {
        return ['attribute_values' => 'array', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(SellerOffer::class);
    }
}
