<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Brand::orderBy('name')->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $brand = Brand::create($this->validatedData($request));

        return ApiResponse::success($brand, 201);
    }

    public function show(Brand $brand): JsonResponse
    {
        return ApiResponse::success($brand);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $brand->update($this->validatedData($request, $brand));

        return ApiResponse::success($brand->fresh());
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return ApiResponse::success(['message' => 'Brand deleted successfully.']);
    }

    private function validatedData(Request $request, ?Brand $brand = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:180',
                'alpha_dash',
                Rule::unique('brands', 'slug')->ignore($brand?->id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
