<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttributeController extends Controller
{
    private const DATA_TYPES = ['text', 'number', 'boolean', 'select', 'multiselect'];

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            Attribute::with('categories:id,name,slug')
                ->orderBy('name')
                ->paginate(50)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $attribute = Attribute::create($this->validatedData($request));

        return ApiResponse::success($attribute, 201);
    }

    public function show(Attribute $attribute): JsonResponse
    {
        return ApiResponse::success($attribute->load('categories'));
    }

    public function update(Request $request, Attribute $attribute): JsonResponse
    {
        $attribute->update($this->validatedData($request, $attribute));

        return ApiResponse::success($attribute->fresh());
    }

    public function destroy(Attribute $attribute): JsonResponse
    {
        $attribute->delete();

        return ApiResponse::success(['message' => 'Attribute deleted successfully.']);
    }

    private function validatedData(Request $request, ?Attribute $attribute = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:180',
                'alpha_dash',
                Rule::unique('attributes', 'slug')->ignore($attribute?->id),
            ],
            'data_type' => ['required', Rule::in(self::DATA_TYPES)],
            'is_filterable' => ['sometimes', 'boolean'],
        ]);
    }
}
