<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            Category::with('parent:id,name,slug')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(50)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedData($request);
        $category = Category::create($validated);

        return ApiResponse::success($category, 201);
    }

    public function show(Category $category): JsonResponse
    {
        return ApiResponse::success($category->load([
            'parent:id,name,slug',
            'children:id,parent_id,name,slug,is_active,sort_order',
            'attributes',
        ]));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $this->validatedData($request, $category);

        if ($this->wouldCreateCycle($category, $validated['parent_id'] ?? null)) {
            return ApiResponse::error(
                'INVALID_CATEGORY_PARENT',
                'A category cannot be its own ancestor.',
                422
            );
        }

        $category->update($validated);

        return ApiResponse::success($category->fresh());
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->children()->exists()) {
            return ApiResponse::error(
                'CATEGORY_HAS_CHILDREN',
                'Move or delete child categories before deleting this category.',
                409
            );
        }

        $category->delete();

        return ApiResponse::success(['message' => 'Category deleted successfully.']);
    }

    public function syncAttributes(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'attributes' => ['required', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'attributes.*.is_required' => ['sometimes', 'boolean'],
            'attributes.*.is_filterable' => ['sometimes', 'boolean'],
            'attributes.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $sync = [];

        foreach ($validated['attributes'] as $item) {
            $sync[$item['attribute_id']] = [
                'is_required' => $item['is_required'] ?? false,
                'is_filterable' => $item['is_filterable'] ?? false,
                'sort_order' => $item['sort_order'] ?? 0,
            ];
        }

        $category->attributes()->sync($sync);

        return ApiResponse::success($category->load('attributes'));
    }

    private function validatedData(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::notIn($category ? [$category->id] : []),
            ],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:180',
                'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    private function wouldCreateCycle(Category $category, ?int $parentId): bool
    {
        while ($parentId !== null) {
            $parentId = (int) $parentId;

            if ($parentId === $category->id) {
                return true;
            }

            $parentId = Category::query()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }
}
