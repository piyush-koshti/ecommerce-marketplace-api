<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

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
}
