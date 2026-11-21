<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\SellerAccount;
use App\Models\SellerOffer;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function show(Request $request, SellerAccount $sellerAccount, SellerOffer $offer): JsonResponse
    {
        $this->authorizeOffer($request->user(), $sellerAccount, $offer);

        return ApiResponse::success($offer->inventory()->with('movements')->firstOrFail());
    }

    public function adjust(Request $request, SellerAccount $sellerAccount, SellerOffer $offer): JsonResponse
    {
        $this->authorizeOffer($request->user(), $sellerAccount, $offer);
        $data = $request->validate([
            'quantity_change' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'max:500'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'string', 'max:100'],
        ]);

        $inventory = DB::transaction(function () use ($offer, $request, $data) {
            $item = InventoryItem::where('seller_offer_id', $offer->id)->lockForUpdate()->firstOrFail();
            $before = $item->quantity_on_hand;
            $after = $before + $data['quantity_change'];
            if ($after < ($item->quantity_reserved + $item->quantity_damaged)) {
                abort(422, 'Adjustment would reduce stock below reserved and damaged quantities.');
            }

            $item->update([
                'quantity_on_hand' => $after,
                'lock_version' => $item->lock_version + 1,
            ]);

            $item->movements()->create([
                'actor_id' => $request->user()->id,
                'movement_type' => $data['quantity_change'] > 0 ? 'stock_in' : 'stock_out',
                'reason' => $data['reason'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'quantity_change' => abs($data['quantity_change']),
                'quantity_before' => $before,
                'quantity_after' => $after,
            ]);

            return $item->fresh();
        }, 3);

        return ApiResponse::success($inventory);
    }

    public function threshold(Request $request, SellerAccount $sellerAccount, SellerOffer $offer): JsonResponse
    {
        $this->authorizeOffer($request->user(), $sellerAccount, $offer);
        $data = $request->validate(['low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000']]);
        $item = $offer->inventory()->firstOrFail();
        $item->update($data);

        return ApiResponse::success($item->fresh());
    }

    private function authorizeOffer(User $user, SellerAccount $seller, SellerOffer $offer): void
    {
        abort_unless($seller->users()->whereKey($user->id)->wherePivot('status', 'active')->exists(), 404);
        abort_unless((int) $offer->seller_account_id === (int) $seller->id, 404);
        abort_unless($user->hasPermissionTo('inventory.view') || $user->hasPermissionTo('inventory.manage'), 403);
    }
}
