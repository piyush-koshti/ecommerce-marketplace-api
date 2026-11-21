<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attribute extends Model
{
    protected $fillable = ['name', 'slug', 'data_type', 'is_filterable'];

    protected function casts(): array
    {
        return ['is_filterable' => 'boolean'];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attributes')
            ->withPivot(['is_required', 'is_filterable', 'sort_order'])
            ->withTimestamps();
    }
}
