<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MarketplaceOperationsStatus extends Command
{
    protected $signature = 'marketplace:ops-status';

    protected $description = 'Report marketplace database, migration, queue and low-stock operational status.';

    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
            $this->info('Database: connected');
        } catch (Throwable) {
            $this->error('Database: unavailable');

            return self::FAILURE;
        }

        $this->line('Database driver: '.DB::connection()->getDriverName());
        if (Schema::hasTable('migrations')) {
            $this->line('Applied migrations: '.DB::table('migrations')->count());
            Artisan::call('migrate:status', ['--no-ansi' => true]);
            $this->line(Artisan::output());
        } else {
            $this->warn('Migration table missing');
        }
        $this->line('Queue connection: '.config('queue.default'));

        if (Schema::hasTable('failed_jobs')) {
            $this->line('Failed jobs: '.DB::table('failed_jobs')->count());
        } else {
            $this->warn('Failed jobs: table missing');
        }

        if (Schema::hasTable('inventory_items')) {
            $lowStock = InventoryItem::query()
                ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_damaged) <= low_stock_threshold')
                ->count();
            $this->line('Low-stock inventory records: '.$lowStock);
        }

        if (Schema::hasTable('data_operations')) {
            $this->line('Queued data operations: '.DB::table('data_operations')->where('status', 'queued')->count());
            $this->line('Failed data operations: '.DB::table('data_operations')->where('status', 'failed')->count());
        }

        return self::SUCCESS;
    }
}
