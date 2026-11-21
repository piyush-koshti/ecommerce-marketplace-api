<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Jobs\ExportSellerInventory;
use App\Jobs\ImportSellerInventory;
use App\Models\DataOperation;
use App\Models\SellerAccount;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataOperationController extends Controller
{
    public function importInventory(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $this->authorizeSeller($request, $sellerAccount, true);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $path = $data['file']->store('marketplace/imports', 'local');

        $operation = DataOperation::create([
            'user_id' => $request->user()->id,
            'seller_account_id' => $sellerAccount->id,
            'operation_type' => 'inventory_import',
            'status' => 'queued',
            'source_path' => $path,
        ]);

        ImportSellerInventory::dispatch($operation->id);

        return ApiResponse::success($operation, 202);
    }

    public function exportInventory(Request $request, SellerAccount $sellerAccount): JsonResponse
    {
        $this->authorizeSeller($request, $sellerAccount, false);

        $operation = DataOperation::create([
            'user_id' => $request->user()->id,
            'seller_account_id' => $sellerAccount->id,
            'operation_type' => 'inventory_export',
            'status' => 'queued',
        ]);

        ExportSellerInventory::dispatch($operation->id);

        return ApiResponse::success($operation, 202);
    }

    public function show(Request $request, SellerAccount $sellerAccount, DataOperation $operation): JsonResponse
    {
        $this->authorizeSeller($request, $sellerAccount, false);
        abort_unless(
            (int) $operation->seller_account_id === (int) $sellerAccount->id
            && (int) $operation->user_id === (int) $request->user()->id,
            404
        );

        return ApiResponse::success($operation);
    }

    public function download(Request $request, SellerAccount $sellerAccount, DataOperation $operation): BinaryFileResponse
    {
        $this->authorizeSeller($request, $sellerAccount, false);
        abort_unless(
            (int) $operation->seller_account_id === (int) $sellerAccount->id
            && (int) $operation->user_id === (int) $request->user()->id,
            404
        );
        abort_unless($operation->status === 'completed' && $operation->result_path, 404);
        abort_unless(Storage::disk('local')->exists($operation->result_path), 404);

        return response()->download(
            Storage::disk('local')->path($operation->result_path),
            'seller-inventory-'.$sellerAccount->id.'-'.$operation->id.'.csv'
        );
    }

    private function authorizeSeller(Request $request, SellerAccount $sellerAccount, bool $manage): void
    {
        abort_unless($sellerAccount->status === 'approved', 404);
        abort_unless(
            $sellerAccount->users()
                ->whereKey($request->user()->id)
                ->wherePivot('status', 'active')
                ->exists(),
            404
        );

        $allowed = $manage
            ? $request->user()->hasPermissionTo('inventory.manage')
            : ($request->user()->hasPermissionTo('inventory.view')
                || $request->user()->hasPermissionTo('inventory.manage'));

        abort_unless($allowed, 403);
    }
}
