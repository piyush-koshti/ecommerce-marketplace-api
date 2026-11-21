<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerAccount;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerReviewController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            SellerAccount::with('users:id,name,email')
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    public function approve(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        abort_unless($sellerAccount->status === 'pending_review', 409, 'Only pending sellers can be approved.');

        $sellerAccount->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reviewer_note' => null,
        ]);

        return ApiResponse::success($sellerAccount->fresh());
    }

    public function reject(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $validated = $request->validate([
            'reviewer_note' => ['required', 'string', 'max:2000'],
        ]);

        abort_unless($sellerAccount->status === 'pending_review', 409, 'Only pending sellers can be rejected.');

        $sellerAccount->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reviewer_note' => $validated['reviewer_note'],
        ]);

        return ApiResponse::success($sellerAccount->fresh());
    }

    public function suspend(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $validated = $request->validate([
            'reviewer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless($sellerAccount->status === 'approved', 409, 'Only approved sellers can be suspended.');

        $sellerAccount->update([
            'status' => 'suspended',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reviewer_note' => $validated['reviewer_note'] ?? null,
        ]);

        return ApiResponse::success($sellerAccount->fresh());
    }
}
