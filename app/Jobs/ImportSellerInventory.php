<?php

namespace App\Jobs;

use App\Models\DataOperation;
use App\Models\InventoryItem;
use App\Models\SellerAccount;
use App\Models\SellerOffer;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportSellerInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_ROWS = 5000;

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
            || ! $actor->hasPermissionTo('inventory.manage')
        ) {
            $this->failOperation($operation, ['The requesting user is no longer authorized to import inventory for this seller.']);

            return;
        }

        $operation->update(['status' => 'processing', 'started_at' => now()]);

        $path = Storage::disk('local')->path($operation->source_path);
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read inventory import file.');
        }

        try {
            $header = fgetcsv($stream, null, ',', '"', '');
            if (! is_array($header)) {
                $this->failOperation($operation, ['CSV file is empty.']);

                return;
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $required = ['seller_sku', 'quantity_on_hand', 'reason'];

            if ($header !== $required) {
                $this->failOperation($operation, [
                    'CSV header must be exactly: seller_sku,quantity_on_hand,reason.',
                ]);

                return;
            }

            $rows = [];
            $errors = [];
            $line = 1;
            $processedRows = 0;
            $seenSkus = [];

            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null] || $values === []) {
                    continue;
                }

                $processedRows++;
                if ($processedRows > self::MAX_ROWS) {
                    $errors[] = 'CSV exceeds the maximum of '.self::MAX_ROWS.' data rows.';
                    break;
                }

                if (count($values) !== 3) {
                    $errors[] = "Line {$line}: exactly three CSV columns are required.";

                    continue;
                }

                $row = array_combine($required, $values);
                $sku = trim((string) ($row['seller_sku'] ?? ''));
                $quantityRaw = trim((string) ($row['quantity_on_hand'] ?? ''));
                $reason = trim((string) ($row['reason'] ?? ''));

                $quantityIsValid = preg_match('/^(0|[1-9][0-9]{0,6})$/D', $quantityRaw) === 1;
                $quantity = $quantityIsValid ? (int) $quantityRaw : -1;
                if ($sku === '' || ! $quantityIsValid || $quantity > 1000000 || $reason === '' || mb_strlen($reason) > 500) {
                    $errors[] = "Line {$line}: SKU, quantity between 0 and 1,000,000 and reason (max 500 characters) are required.";

                    continue;
                }

                if (isset($seenSkus[$sku])) {
                    $errors[] = "Line {$line}: duplicate SKU {$sku}.";

                    continue;
                }

                $seenSkus[$sku] = true;
                $rows[] = ['line' => $line, 'sku' => $sku, 'quantity' => $quantity, 'reason' => $reason];
            }
        } finally {
            fclose($stream);
        }

        if ($rows === [] && $errors === []) {
            $errors[] = 'CSV contains no inventory rows.';
        }

        if ($errors !== []) {
            $this->failOperation($operation, array_slice($errors, 0, 100));

            return;
        }

        $updatedRows = 0;
        $lowStockEvents = [];

        try {
            DB::transaction(function () use ($operation, $rows, &$updatedRows, &$lowStockEvents): void {
                foreach ($rows as $row) {
                    $offer = SellerOffer::query()
                        ->where('seller_account_id', $operation->seller_account_id)
                        ->where('seller_sku', $row['sku'])
                        ->lockForUpdate()
                        ->first();

                    if (! $offer) {
                        throw new RuntimeException("Line {$row['line']}: SKU {$row['sku']} does not belong to this seller.");
                    }

                    $inventory = InventoryItem::query()
                        ->where('seller_offer_id', $offer->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory) {
                        throw new RuntimeException("Line {$row['line']}: inventory record is missing for SKU {$row['sku']}.");
                    }

                    $before = (int) $inventory->quantity_on_hand;
                    $beforeAvailable = $inventory->available_quantity;
                    $after = (int) $row['quantity'];

                    if ($after < ((int) $inventory->quantity_reserved + (int) $inventory->quantity_damaged)) {
                        throw new RuntimeException("Line {$row['line']}: quantity for SKU {$row['sku']} is below reserved plus damaged stock.");
                    }

                    if ($before === $after) {
                        continue;
                    }

                    $inventory->update([
                        'quantity_on_hand' => $after,
                        'lock_version' => $inventory->lock_version + 1,
                    ]);

                    $updatedRows++;

                    $afterAvailable = max(0, $after - (int) $inventory->quantity_reserved - (int) $inventory->quantity_damaged);
                    if ($beforeAvailable > $inventory->low_stock_threshold && $afterAvailable <= $inventory->low_stock_threshold) {
                        $lowStockEvents[] = [
                            'inventory_item_id' => $inventory->id,
                            'seller_sku' => $offer->seller_sku,
                            'available_quantity' => $afterAvailable,
                            'threshold' => (int) $inventory->low_stock_threshold,
                        ];
                    }

                    $inventory->movements()->create([
                        'actor_id' => $operation->user_id,
                        'movement_type' => 'stock_import_adjustment',
                        'reason' => $row['reason'],
                        'reference_type' => 'data_operation',
                        'reference_id' => (string) $operation->id,
                        'quantity_change' => abs($after - $before),
                        'quantity_before' => $before,
                        'quantity_after' => $after,
                    ]);
                }

                if ($lowStockEvents !== []) {
                    $seller = SellerAccount::query()->find($operation->seller_account_id);
                    $members = $seller?->users()->wherePivot('status', 'active')->get() ?? collect();

                    foreach ($lowStockEvents as $event) {
                        foreach ($members as $member) {
                            $member->notify(new LowStockNotification(
                                $event['inventory_item_id'],
                                $event['seller_sku'],
                                $event['available_quantity'],
                                $event['threshold'],
                            ));
                        }
                    }
                }

                $operation->update([
                    'status' => 'completed',
                    'summary' => ['processed_rows' => count($rows), 'updated_rows' => $updatedRows, 'atomic' => true],
                    'completed_at' => now(),
                ]);
            }, 3);
        } catch (RuntimeException $exception) {
            $this->failOperation($operation, [$exception->getMessage()]);

            return;
        } catch (\Throwable $exception) {
            report($exception);
            $this->failOperation($operation, ['Inventory import failed unexpectedly. Check application logs.']);

            return;
        }

    }

    private function failOperation(DataOperation $operation, array $errors): void
    {
        $operation->update([
            'status' => 'failed',
            'summary' => ['errors' => $errors, 'atomic' => true, 'applied_rows' => 0],
            'completed_at' => now(),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        DataOperation::query()->whereKey($this->operationId)->update([
            'status' => 'failed',
            'summary' => ['message' => 'Inventory import failed. Check application logs.'],
            'completed_at' => now(),
        ]);
    }
}
