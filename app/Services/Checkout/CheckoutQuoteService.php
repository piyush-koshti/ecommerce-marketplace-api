<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\SellerOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutQuoteService
{
    public function create(Cart $cart, int $userId, int $addressId): CheckoutSession
    {
        return DB::transaction(function () use ($cart, $userId, $addressId) {
            $cart = Cart::with('items')->lockForUpdate()->findOrFail($cart->id);
            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $rows = [];
            $subtotal = 0.0;
            foreach ($cart->items as $cartItem) {
                $offer = SellerOffer::with(['variant.product', 'sellerAccount', 'inventory'])->lockForUpdate()->findOrFail($cartItem->seller_offer_id);
                $available = $offer->inventory ? $offer->inventory->quantity_on_hand - $offer->inventory->quantity_reserved - $offer->inventory->quantity_damaged : 0;
                if ($offer->status !== 'active' || $offer->sellerAccount?->status !== 'approved' || $offer->variant?->product?->status !== 'approved' || $available < $cartItem->quantity) {
                    throw ValidationException::withMessages(['cart' => 'One or more cart offers are unavailable or have insufficient stock.']);
                }
                $line = round((float) $offer->price * $cartItem->quantity, 2);
                $subtotal += $line;
                $rows[] = ['offer' => $offer, 'quantity' => $cartItem->quantity, 'line' => $line];
            }

            // Tax, shipping and discounts remain zero until approved business rules/providers are configured.
            $snapshot = ['pricing_version' => '1', 'currency' => 'INR', 'subtotal' => round($subtotal, 2), 'discount_total' => 0, 'shipping_total' => 0, 'tax_total' => 0, 'grand_total' => round($subtotal, 2), 'rules_pending' => ['tax', 'shipping', 'promotions']];
            $checkout = CheckoutSession::create([
                'public_id' => (string) Str::uuid(), 'user_id' => $userId, 'cart_id' => $cart->id, 'address_id' => $addressId,
                'status' => 'quoted', 'currency' => 'INR', 'subtotal' => $subtotal, 'discount_total' => 0, 'shipping_total' => 0,
                'tax_total' => 0, 'grand_total' => $subtotal, 'pricing_snapshot' => $snapshot, 'expires_at' => now()->addMinutes(max(1, (int) config('marketplace.checkout_quote_minutes', 15))),
            ]);
            foreach ($rows as $row) {
                $offer = $row['offer'];
                $checkout->items()->create([
                    'seller_offer_id' => $offer->id, 'quantity' => $row['quantity'],
                    'product_name' => $offer->variant->product->name, 'variant_name' => $offer->variant->name,
                    'seller_name' => $offer->sellerAccount->display_name, 'seller_sku' => $offer->seller_sku,
                    'unit_price' => $offer->price, 'line_total' => $row['line'],
                ]);
            }

            return $checkout->load('items');
        }, 3);
    }
}
