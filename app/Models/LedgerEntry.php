<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $fillable = ['transaction_group', 'ledger_account_id', 'order_id', 'payment_transaction_id', 'entry_type', 'debit', 'credit', 'currency', 'description', 'posted_at'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    public function delete(): never
    {
        throw new \LogicException('Ledger entries are immutable.');
    }

    public function update(array $attributes = [], array $options = []): never
    {
        throw new \LogicException('Ledger entries are immutable.');
    }
}
