<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerAccount;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SellerProductController extends Controller
{
    public function index(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);

        return ApiResponse::success(
            Product::where('created_by_seller_account_id', $sellerAccount->id)
                ->with(['category:id,name,slug', 'brand:id,name,slug', 'variants'])
                ->latest()->paginate(25)
        );
    }

    public function store(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);
        abort_unless($sellerAccount->status === 'approved', 403, 'Seller account must be approved.');

        $data = $this->validateProduct($request);
        $product = DB::transaction(function () use ($data, $sellerAccount, $request) {
            $variants = $data['variants'];
            unset($data['variants']);
            $product = Product::create([
                ...$data,
                'created_by_seller_account_id' => $sellerAccount->id,
                'created_by' => $request->user()->id,
                'status' => 'draft',
            ]);
            foreach ($variants as $variant) {
                $product->variants()->create($variant);
            }

            return $product;
        });

        return ApiResponse::success($product->load('variants'), 201);
    }

    public function show(Request $request, SellerAccount $sellerAccount, Product $product): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);
        abort_unless((int) $product->created_by_seller_account_id === (int) $sellerAccount->id, 404);

        return ApiResponse::success($product->load(['variants', 'media', 'category', 'brand']));
    }

    public function update(Request $request, SellerAccount $sellerAccount, Product $product): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);
        abort_unless((int) $product->created_by_seller_account_id === (int) $sellerAccount->id, 404);
        abort_if(in_array($product->status, ['approved', 'pending_review'], true), 409, 'Approved or submitted catalogue records cannot be edited directly.');

        $data = $this->validateProduct($request, $product);
        DB::transaction(function () use ($data, $product) {
            $variants = $data['variants'];
            unset($data['variants']);
            $product->update($data);
            foreach ($variants as $variant) {
                if (! empty($variant['id'])) {
                    $existing = $product->variants()->findOrFail($variant['id']);
                    unset($variant['id']);
                    $existing->update($variant);
                } else {
                    $product->variants()->create($variant);
                }
            }
        });

        return ApiResponse::success($product->fresh()->load('variants'));
    }

    public function submit(Request $request, SellerAccount $sellerAccount, Product $product): JsonResponse
    {
        $this->authorizeSeller($request->user(), $sellerAccount);
        abort_unless((int) $product->created_by_seller_account_id === (int) $sellerAccount->id, 404);
        abort_unless($product->status === 'draft' || $product->status === 'rejected', 409);

        $product->load('category.attributes', 'variants');
        abort_if($product->variants->where('is_active', true)->isEmpty(), 422, 'At least one active variant is required.');

        $requiredSlugs = $product->category->attributes
            ->filter(fn ($attribute) => (bool) $attribute->pivot->is_required)
            ->pluck('slug')->all();

        foreach ($product->variants->where('is_active', true) as $variant) {
            $values = $variant->attribute_values ?? [];
            foreach ($requiredSlugs as $slug) {
                abort_if(! array_key_exists($slug, $values) || $values[$slug] === null || $values[$slug] === '',
                    422, "Required category attribute [{$slug}] is missing from a variant.");
            }
        }

        $product->update(['status' => 'pending_review', 'submitted_at' => now(), 'moderation_note' => null]);

        return ApiResponse::success($product->fresh());
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:220'],
            'slug' => ['required', 'string', 'max:240', 'alpha_dash', Rule::unique('products', 'slug')->ignore($product?->id)],
            'description' => ['nullable', 'string', 'max:50000'],
            'tax_classification_code' => ['nullable', 'string', 'max:80'],
            'variants' => ['required', 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['sometimes', 'integer'],
            'variants.*.name' => ['nullable', 'string', 'max:180'],
            'variants.*.attribute_values' => ['nullable', 'array'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'variants.*.length_mm' => ['nullable', 'integer', 'min:0'],
            'variants.*.width_mm' => ['nullable', 'integer', 'min:0'],
            'variants.*.height_mm' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeSeller(User $user, SellerAccount $seller): void
    {
        abort_unless($seller->users()->whereKey($user->id)->wherePivot('status', 'active')->exists(), 404);
        abort_unless($user->hasPermissionTo('products.create') || $user->hasPermissionTo('products.update'), 403);
    }
}
