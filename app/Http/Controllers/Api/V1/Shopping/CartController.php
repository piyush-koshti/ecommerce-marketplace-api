<?php

namespace App\Http\Controllers\Api\V1\Shopping;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\SellerOffer;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request, false);
        $response = ApiResponse::success($cart?->load(['items.offer.variant.product', 'items.offer.sellerAccount', 'items.offer.inventory']));

        return $this->withGuestToken($request, $response);
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'seller_offer_id' => ['required', 'integer', 'exists:seller_offers,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);
        $offer = SellerOffer::with(['variant.product', 'sellerAccount', 'inventory'])->findOrFail($data['seller_offer_id']);
        abort_unless($this->offerIsPurchasable($offer), 422, 'This offer is not currently available.');
        $cart = $this->resolveCart($request, true);
        $existingQuantity = $cart->items()->where('seller_offer_id', $offer->id)->value('quantity') ?? 0;
        abort_if(! $offer->inventory || ($offer->inventory->quantity_on_hand - $offer->inventory->quantity_reserved - $offer->inventory->quantity_damaged) < ($existingQuantity + $data['quantity']), 422, 'Insufficient available stock.');
        $item = DB::transaction(function () use ($cart, $offer, $data) {
            $item = CartItem::firstOrNew(['cart_id' => $cart->id, 'seller_offer_id' => $offer->id]);
            $newQuantity = (int) $item->quantity + $data['quantity'];
            abort_if($newQuantity > 1000, 422, 'Cart quantity limit exceeded.');
            $item->quantity = $newQuantity;
            $item->save();

            return $item->load('offer.variant.product');
        });

        return $this->withGuestToken($request, ApiResponse::success($item, 201));
    }

    public function update(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->resolveCart($request, false);
        abort_unless($cart && (int) $item->cart_id === (int) $cart->id, 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000']]);
        $offer = $item->offer()->with(['variant.product', 'sellerAccount', 'inventory'])->firstOrFail();
        abort_unless($this->offerIsPurchasable($offer), 422);
        abort_if(! $offer->inventory || ($offer->inventory->quantity_on_hand - $offer->inventory->quantity_reserved - $offer->inventory->quantity_damaged) < $data['quantity'], 422, 'Insufficient available stock.');
        $item->update($data);

        return ApiResponse::success($item->fresh('offer.variant.product'));
    }

    public function remove(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->resolveCart($request, false);
        abort_unless($cart && (int) $item->cart_id === (int) $cart->id, 404);
        $item->delete();

        return ApiResponse::success(['message' => 'Cart item removed.']);
    }

    public function mergeGuest(Request $request): JsonResponse
    {
        $data = $request->validate(['guest_token' => ['required', 'string', 'min:32', 'max:128']]);
        $guest = Cart::where('guest_token_hash', hash('sha256', $data['guest_token']))->whereNull('user_id')->where('status', 'active')->first();
        if (! $guest) {
            return ApiResponse::success(['merged' => false]);
        }
        $userCart = Cart::firstOrCreate(['user_id' => $request->user()->id, 'status' => 'active']);
        DB::transaction(function () use ($guest, $userCart) {
            foreach ($guest->items as $guestItem) {
                $existing = $userCart->items()->where('seller_offer_id', $guestItem->seller_offer_id)->first();
                if ($existing) {
                    $existing->update(['quantity' => min(1000, $existing->quantity + $guestItem->quantity)]);
                } else {
                    $userCart->items()->create(['seller_offer_id' => $guestItem->seller_offer_id, 'quantity' => $guestItem->quantity]);
                }
            }
            $guest->update(['status' => 'converted']);
        });

        return ApiResponse::success(['merged' => true, 'cart' => $userCart->fresh('items')]);
    }

    private function resolveCart(Request $request, bool $create): ?Cart
    {
        if ($request->user()) {
            $cart = Cart::firstOrCreate(['user_id' => $request->user()->id, 'status' => 'active']);

            return $cart;
        }
        $token = $request->header('X-Guest-Cart-Token');
        if (! $token && ! $create) {
            return null;
        }
        if (! $token) {
            $token = Str::random(48);
            $request->attributes->set('new_guest_cart_token', $token);
        }
        $hash = hash('sha256', $token);
        $cart = Cart::firstOrCreate(['guest_token_hash' => $hash], ['status' => 'active', 'expires_at' => now()->addDays(30)]);

        return $cart;
    }

    private function withGuestToken(Request $request, JsonResponse $response): JsonResponse
    {
        $token = $request->attributes->get('new_guest_cart_token');
        if ($token) {
            $response->headers->set('X-Guest-Cart-Token', $token);
        }

        return $response;
    }

    private function offerIsPurchasable(SellerOffer $offer): bool
    {
        return $offer->status === 'active'
            && $offer->sellerAccount?->status === 'approved'
            && $offer->variant?->is_active
            && $offer->variant?->product?->status === 'approved';
    }
}
