<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SellerAccount;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductMediaController extends Controller
{
    public function store(Request $request, SellerAccount $sellerAccount, Product $product): JsonResponse
    {
        $this->authorizeProduct($request->user(), $sellerAccount, $product);
        abort_if(in_array($product->status, ['approved', 'pending_review'], true), 409);
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:250'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $file = $data['file'];
        $path = $file->store('products/'.$product->id, 'public');
        $media = $product->media()->create([
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'alt_text' => $data['alt_text'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return ApiResponse::success($media, 201);
    }

    public function destroy(Request $request, SellerAccount $sellerAccount, Product $product, ProductMedia $media): JsonResponse
    {
        $this->authorizeProduct($request->user(), $sellerAccount, $product);
        abort_unless((int) $media->product_id === (int) $product->id, 404);
        abort_if(in_array($product->status, ['approved', 'pending_review'], true), 409);
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return ApiResponse::success(['message' => 'Product media deleted.']);
    }

    private function authorizeProduct(User $user, SellerAccount $seller, Product $product): void
    {
        abort_unless($seller->users()->whereKey($user->id)->wherePivot('status', 'active')->exists(), 404);
        abort_unless((int) $product->created_by_seller_account_id === (int) $seller->id, 404);
    }
}
