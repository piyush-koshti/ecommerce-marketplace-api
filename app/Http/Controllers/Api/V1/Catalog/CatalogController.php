<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        return ApiResponse::success(
            Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'parent_id',
                    'name',
                    'slug',
                    'description',
                    'sort_order',
                ])
        );
    }

    public function brands(): JsonResponse
    {
        return ApiResponse::success(
            Brand::where('is_active', true)->orderBy('name')->get()
        );
    }

    public function categoryAttributes(Category $category): JsonResponse
    {
        abort_unless($category->is_active, 404);

        return ApiResponse::success(
            $category->attributes()
                ->where('attributes.is_filterable', true)
                ->orderByPivot('sort_order')
                ->get()
        );
    }

    public function products(Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('status', 'approved')
            ->whereHas('variants', fn ($q) => $q->where('is_active', true)
                ->whereHas('offers', fn ($offer) => $offer->where('status', 'active')
                    ->whereHas('sellerAccount', fn ($seller) => $seller->where('status', 'approved'))
                    ->whereHas('inventory', fn ($inventory) => $inventory->whereRaw(
                        'quantity_on_hand > quantity_reserved + quantity_damaged'
                    ))))
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'variants.offers' => function ($q) {
                $q->where('status', 'active')
                    ->whereHas('sellerAccount', fn ($seller) => $seller->where('status', 'approved'))
                    ->with('inventory');
            }]);

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%');
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        return ApiResponse::success($query->latest()->paginate(min(50, max(1, $request->integer('per_page', 20)))));
    }
}
