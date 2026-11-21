<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\SellerAccount;
use App\Models\SellerOffer;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SellerOfferController extends Controller
{
    public function index(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);

        return ApiResponse::success(
            SellerOffer::where('seller_account_id', $sellerAccount->id)
                ->with(['variant.product:id,name,slug,status', 'inventory'])
                ->latest()->paginate(25)
        );
    }

    public function store(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);
        abort_unless($sellerAccount->status === 'approved', 403, 'Seller account must be approved.');

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'seller_sku' => [
                'required', 'string', 'max:100',
                Rule::unique('seller_offers', 'seller_sku')->where('seller_account_id', $sellerAccount->id),
            ],
            'condition' => ['required', 'string', 'max:40'],
            'price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'dispatch_sla_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);
        abort_unless($variant->is_active && $variant->product->status === 'approved', 422, 'Only active variants of approved products can be offered.');

        $offer = DB::transaction(function () use ($data, $sellerAccount) {
            $offer = SellerOffer::create([
                ...$data,
                'seller_account_id' => $sellerAccount->id,
                'status' => 'draft',
            ]);
            $offer->inventory()->create([
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_damaged' => 0,
                'quantity_returned' => 0,
                'quantity_in_transit' => 0,
            ]);

            return $offer;
        });

        return ApiResponse::success($offer->load(['variant.product', 'inventory']), 201);
    }

    public function update(Request $request, SellerAccount $sellerAccount, SellerOffer $offer): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);
        abort_unless((int) $offer->seller_account_id === (int) $sellerAccount->id, 404);
        abort_if($offer->status === 'active', 409, 'Deactivate the offer before editing it.');

        $data = $request->validate([
            'seller_sku' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('seller_offers', 'seller_sku')->where('seller_account_id', $sellerAccount->id)->ignore($offer->id),
            ],
            'condition' => ['sometimes', 'required', 'string', 'max:40'],
            'price' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,2'],
            'dispatch_sla_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
        ]);
        $offer->update($data);

        return ApiResponse::success($offer->fresh());
    }

    public function activate(Request $request, SellerAccount $sellerAccount, SellerOffer $offer): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);
        abort_unless((int) $offer->seller_account_id === (int) $sellerAccount->id, 404);
        abort_unless($sellerAccount->status === 'approved', 403);
        abort_unless($offer->variant()->whereHas('product', fn ($q) => $q->where('status', 'approved'))->exists(), 422);
        $offer->update(['status' => 'active']);

        return ApiResponse::success($offer->fresh());
    }

    public function deactivate(Request $request, SellerAccount $sellerAccount, SellerOffer $offer): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);
        abort_unless((int) $offer->seller_account_id === (int) $sellerAccount->id, 404);
        $offer->update(['status' => 'inactive']);

        return ApiResponse::success($offer->fresh());
    }

    private function authorizeSeller(User $user, SellerAccount $seller): void
    {
        abort_unless($seller->users()->whereKey($user->id)->wherePivot('status', 'active')->exists(), 404);
        abort_unless($user->hasPermissionTo('products.create') || $user->hasPermissionTo('products.update'), 403);
    }
}
