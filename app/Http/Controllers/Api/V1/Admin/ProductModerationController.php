<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductModerationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category:id,name', 'brand:id,name', 'variants'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->latest();

        return ApiResponse::success($query->paginate(25));
    }

    public function approve(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->status === 'pending_review', 409, 'Only submitted products can be approved.');
        $product->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'moderation_note' => null,
        ]);

        return ApiResponse::success($product->fresh());
    }

    public function reject(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->status === 'pending_review', 409, 'Only submitted products can be rejected.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $product->update(['status' => 'rejected', 'moderation_note' => $data['reason']]);

        return ApiResponse::success($product->fresh());
    }

    public function setStatus(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['inactive', 'archived'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $product->update([
            'status' => $data['status'],
            'moderation_note' => $data['reason'] ?? null,
        ]);

        return ApiResponse::success($product->fresh());
    }
}
