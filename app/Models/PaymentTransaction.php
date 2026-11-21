<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = ['payment_intent_id', 'provider_transaction_id', 'transaction_type', 'status', 'amount', 'safe_response'];

    protected function casts(): array
    {
        return ['safe_response' => 'array'];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}
