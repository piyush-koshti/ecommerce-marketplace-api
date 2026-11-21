<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'event_type', 'processing_status', 'safe_payload', 'processed_at'];

    protected function casts(): array
    {
        return ['safe_payload' => 'array', 'processed_at' => 'datetime'];
    }
}
