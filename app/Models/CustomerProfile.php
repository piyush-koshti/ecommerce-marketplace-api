<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    protected $fillable = ['user_id', 'first_name', 'last_name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
