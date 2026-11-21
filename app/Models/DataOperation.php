<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataOperation extends Model
{
    protected $hidden = ['source_path', 'result_path'];

    protected $fillable = [
        'user_id',
        'seller_account_id',
        'operation_type',
        'status',
        'source_path',
        'result_path',
        'summary',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }
}
