<?php

namespace App\Jobs;

use App\Models\DataOperation;
use App\Models\SellerAccount;
use App\Models\SellerOffer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExportSellerInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $operationId) {}

    public function handle(): void
    {
        $operation = DataOperation::query()->findOrFail($this->operationId);
        if ($operation->status === 'completed') {
            return;
        }

        $actor = User::query()->find($operation->user_id);
        $seller = SellerAccount::query()->find($operation->seller_account_id);
        if (
            ! $actor
            || ! $seller
            || $seller->status !== 'approved'
            || ! $seller->users()->whereKey($actor->id)->wherePivot('status', 'active')->exists()
            || (! $actor->hasPermissionTo('inventory.view') && ! $actor->hasPermissionTo('inventory.manage'))
        ) {
            $operation->update([
                'status' => 'failed',
                'summary' => ['message' => 'The requesting user is no longer authorized to export inventory for this seller.'],
                'completed_at' => now(),
            ]);

            return;
        }

        $operation->update(['status' => 'processing', 'started_at' => now()]);

        $relativePath = 'marketplace/exports/inventory-'.$operation->id.'.csv';
        Storage::disk('local')->makeDirectory('marketplace/exports');
        $absolutePath = Storage::disk('local')->path($relativePath);
        $stream = fopen($absolutePath, 'wb');

        if ($stream === false) {
            throw new RuntimeException('Unable to create inventory export file.');
        }

        $count = 0;

        try {
            fputcsv($stream, [
                'seller_sku',
                'product_name',
                'variant_name',
                'price',
                'quantity_on_hand',
                'quantity_reserved',
                'quantity_damaged',
                'available_quantity',
                'low_stock_threshold',
            ], ',', '"', '');

            SellerOffer::query()
                ->where('seller_account_id', $operation->seller_account_id)
                ->with(['variant.product', 'inventory'])
                ->chunkById(500, function ($offers) use ($stream, &$count): void {
                    foreach ($offers as $offer) {
                        $inventory = $offer->inventory;
                        if (! $inventory) {
                            continue;
                        }

                        fputcsv($stream, [
                            $offer->seller_sku,
                            $offer->variant?->product?->name ?? '',
                            $offer->variant?->name ?? '',
                            $offer->price,
                            $inventory->quantity_on_hand,
                            $inventory->quantity_reserved,
                            $inventory->quantity_damaged,
                            $inventory->available_quantity,
                            $inventory->low_stock_threshold,
                        ], ',', '"', '');
                        $count++;
                    }
                });
        } finally {
            fclose($stream);
        }

        $operation->update([
            'status' => 'completed',
            'result_path' => $relativePath,
            'summary' => ['exported_rows' => $count, 'format' => 'csv'],
            'completed_at' => now(),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        DataOperation::query()->whereKey($this->operationId)->update([
            'status' => 'failed',
            'summary' => ['message' => 'Inventory export failed. Check application logs.'],
            'completed_at' => now(),
        ]);
    }
}
