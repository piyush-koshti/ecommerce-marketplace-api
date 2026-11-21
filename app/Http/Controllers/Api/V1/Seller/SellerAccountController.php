<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SellerAccount;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->sellerAccounts()
            ->withPivot(['membership_role', 'status'])
            ->orderBy('seller_accounts.created_at', 'desc')
            ->get();

        return ApiResponse::success($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'legal_business_name' => ['required', 'string', 'max:200'],
            'display_name' => ['nullable', 'string', 'max:200'],
            'business_type' => ['nullable', 'string', 'max:80'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_mobile' => ['nullable', 'string', 'max:20'],
        ]);

        $account = DB::transaction(function () use ($request, $validated): SellerAccount {
            $slug = Str::slug($validated['display_name'] ?: $validated['legal_business_name']);
            $slug = $slug.'-'.Str::lower(Str::random(8));

            $account = SellerAccount::create([
                ...$validated,
                'slug' => $slug,
                'status' => 'pending_review',
                'submitted_at' => now(),
            ]);

            $account->users()->attach($request->user()->id, [
                'membership_role' => 'owner',
                'status' => 'active',
                'assigned_by' => $request->user()->id,
            ]);

            $role = Role::where('name', 'seller_owner')->firstOrFail();
            $request->user()->roles()->syncWithoutDetaching([$role->id]);

            return $account;
        });

        return ApiResponse::success($account->load('users:id,name,email'), 201);
    }

    public function show(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $this->ensureMembership($request->user(), $sellerAccount);

        return ApiResponse::success($sellerAccount->load([
            'users' => fn ($query) => $query->select('users.id', 'users.name', 'users.email')
                ->withPivot(['membership_role', 'status']),
        ]));
    }

    public function addStaff(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $this->ensureOwner($request->user(), $sellerAccount);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $staff = User::findOrFail($validated['user_id']);

        if ($staff->is($request->user())) {
            return ApiResponse::error('INVALID_STAFF_MEMBER', 'The seller owner cannot be added as staff.', 422);
        }

        DB::transaction(function () use ($request, $sellerAccount, $staff): void {
            $existingMembership = $sellerAccount->users()
                ->whereKey($staff->id)
                ->first();

            if ($existingMembership?->pivot->membership_role === 'owner') {
                abort(422, 'A seller owner cannot be converted into staff.');
            }

            if ($existingMembership) {
                $sellerAccount->users()->updateExistingPivot($staff->id, [
                    'membership_role' => 'staff',
                    'status' => 'active',
                    'assigned_by' => $request->user()->id,
                ]);
            } else {
                $sellerAccount->users()->attach($staff->id, [
                    'membership_role' => 'staff',
                    'status' => 'active',
                    'assigned_by' => $request->user()->id,
                ]);
            }

            $staffRole = Role::where('name', 'seller_staff')->firstOrFail();
            $staff->roles()->syncWithoutDetaching([$staffRole->id]);
        });

        return ApiResponse::success([
            'seller_account_id' => $sellerAccount->id,
            'user_id' => $staff->id,
            'membership_role' => 'staff',
            'status' => 'active',
        ], 201);
    }

    public function removeStaff(Request $request, SellerAccount $sellerAccount, User $user): JsonResponse
    {
        $this->ensureOwner($request->user(), $sellerAccount);

        $membership = $sellerAccount->users()
            ->whereKey($user->id)
            ->wherePivot('membership_role', 'staff')
            ->first();

        if (! $membership) {
            abort(404);
        }

        $sellerAccount->users()->detach($user->id);

        // Do not remove the global seller_staff role: the user may belong to another seller.
        return ApiResponse::success(['message' => 'Seller staff membership removed.']);
    }

    private function ensureMembership(User $user, SellerAccount $sellerAccount): void
    {
        abort_unless(
            $sellerAccount->users()
                ->whereKey($user->id)
                ->wherePivot('status', 'active')
                ->exists(),
            404
        );
    }

    private function ensureOwner(User $user, SellerAccount $sellerAccount): void
    {
        abort_unless(
            $sellerAccount->users()
                ->whereKey($user->id)
                ->wherePivot('membership_role', 'owner')
                ->wherePivot('status', 'active')
                ->exists(),
            403
        );
    }
}
