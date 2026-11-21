<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SellerAccount extends Model
{
    protected $fillable = [
        'legal_business_name',
        'display_name',
        'slug',
        'business_type',
        'contact_email',
        'contact_mobile',
        'status',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'reviewer_note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seller_users')
            ->withPivot(['membership_role', 'status', 'assigned_by'])
            ->withTimestamps();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
