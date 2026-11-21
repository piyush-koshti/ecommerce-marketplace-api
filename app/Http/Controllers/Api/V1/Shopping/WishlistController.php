<?php

namespace App\Http\Controllers\Api\V1\Shopping;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $wishlist = Wishlist::firstOrCreate(['user_id' => $request->user()->id, 'name' => 'My Wishlist']);

        return ApiResponse::success($wishlist->load('items.product'));
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);
        $product = Product::where('status', 'approved')->findOrFail($data['product_id']);
        $wishlist = Wishlist::firstOrCreate(['user_id' => $request->user()->id, 'name' => 'My Wishlist']);
        $item = $wishlist->items()->firstOrCreate(['product_id' => $product->id]);

        return ApiResponse::success($item->load('product'), 201);
    }

    public function remove(Request $request, int $item): JsonResponse
    {
        $wishlist = Wishlist::where('user_id', $request->user()->id)->where('name', 'My Wishlist')->firstOrFail();
        $wishlist->items()->whereKey($item)->delete();

        return ApiResponse::success(['message' => 'Wishlist item removed.']);
    }
}
