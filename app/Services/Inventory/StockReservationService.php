<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReservationService
{
    public function reserve(InventoryItem $item, string $referenceType, string $referenceId, int $quantity, int $ttlMinutes = 15): StockReservation
    {
        return DB::transaction(function () use ($item, $referenceType, $referenceId, $quantity, $ttlMinutes) {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $available = $locked->quantity_on_hand - $locked->quantity_reserved - $locked->quantity_damaged;

            if ($quantity < 1 || $available < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient available stock.']);
            }

            $reservation = StockReservation::create([
                'inventory_item_id' => $locked->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quantity' => $quantity,
                'status' => 'reserved',
                'expires_at' => now()->addMinutes(max(1, $ttlMinutes)),
            ]);

            $locked->increment('quantity_reserved', $quantity);
            $locked->increment('lock_version');

            return $reservation;
        }, 3);
    }

    public function release(StockReservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $lockedReservation = StockReservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($lockedReservation->status !== 'reserved') {
                return;
            }

            $item = InventoryItem::query()->lockForUpdate()->findOrFail($lockedReservation->inventory_item_id);
            $item->decrement('quantity_reserved', $lockedReservation->quantity);
            $item->increment('lock_version');

            $lockedReservation->update(['status' => 'released', 'released_at' => now()]);
        }, 3);
    }

    public function commit(StockReservation $reservation, ?int $actorId = null): void
    {
        DB::transaction(function () use ($reservation, $actorId) {
            $lockedReservation = StockReservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($lockedReservation->status !== 'reserved' || $lockedReservation->expires_at->isPast()) {
                throw ValidationException::withMessages(['reservation' => 'Reservation is not active.']);
            }

            $item = InventoryItem::query()->lockForUpdate()->findOrFail($lockedReservation->inventory_item_id);
            $before = $item->quantity_on_hand;
            $item->decrement('quantity_reserved', $lockedReservation->quantity);
            $item->decrement('quantity_on_hand', $lockedReservation->quantity);
            $item->increment('lock_version');
            $item->movements()->create([
                'actor_id' => $actorId,
                'movement_type' => 'order_commit',
                'reason' => 'Stock committed for accepted order.',
                'reference_type' => $lockedReservation->reference_type,
                'reference_id' => $lockedReservation->reference_id,
                'quantity_change' => $lockedReservation->quantity,
                'quantity_before' => $before,
                'quantity_after' => $before - $lockedReservation->quantity,
            ]);

            $lockedReservation->update(['status' => 'committed', 'committed_at' => now()]);
        }, 3);
    }

    public function reverseCommitted(StockReservation $reservation, ?int $actorId = null): void
    {
        DB::transaction(function () use ($reservation, $actorId) {
            $lockedReservation = StockReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($lockedReservation->status !== 'committed') {
                return;
            }
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($lockedReservation->inventory_item_id);
            $before = $item->quantity_on_hand;
            $item->increment('quantity_on_hand', $lockedReservation->quantity);
            $item->increment('lock_version');
            $item->movements()->create([
                'actor_id' => $actorId,
                'movement_type' => 'order_cancel_restore',
                'reason' => 'Committed stock restored after seller order cancellation.',
                'reference_type' => $lockedReservation->reference_type,
                'reference_id' => $lockedReservation->reference_id,
                'quantity_change' => $lockedReservation->quantity,
                'quantity_before' => $before,
                'quantity_after' => $before + $lockedReservation->quantity,
            ]);
            $lockedReservation->update(['status' => 'reversed', 'released_at' => now()]);
        }, 3);
    }
}
