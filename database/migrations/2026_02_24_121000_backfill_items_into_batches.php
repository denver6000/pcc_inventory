<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure the batch_code column exists before touching data
        if (!Schema::hasColumn('restock_batches', 'batch_code')) {
            Schema::table('restock_batches', function (Blueprint $table) {
                $table->string('batch_code')->nullable()->after('id');
            });
        }

        $today = now()->format('Ymd');
        $sequence = DB::table('restock_batches')->count();

        DB::table('items')
            ->select('id', 'name', 'current_stock', 'cost_per_unit')
            ->where('current_stock', '>', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($item) use (&$sequence, $today) {
                $hasLines = DB::table('restock_batch_items')->where('item_id', $item->id)->exists();
                if ($hasLines) {
                    return; // already backed by batches
                }

                $sequence++;
                $batchCode = sprintf('RST-LEGACY-%s-%03d', $today, $sequence);

                $batchId = DB::table('restock_batches')->insertGetId([
                    'batch_code' => $batchCode,
                    'notes'      => 'Backfilled from existing item stock',
                    'total_cost' => round($item->current_stock * ($item->cost_per_unit ?? 0), 4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('restock_batch_items')->insert([
                    'restock_batch_id' => $batchId,
                    'item_id'          => $item->id,
                    'quantity_added'   => $item->current_stock,
                    'cost_per_unit'    => $item->cost_per_unit ?? 0,
                    'subtotal'         => round($item->current_stock * ($item->cost_per_unit ?? 0), 4),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Removing only the legacy batches/items we created by marker prefix
        DB::table('restock_batches')
            ->where('batch_code', 'like', 'RST-LEGACY-%')
            ->get()
            ->each(function ($batch) {
                DB::table('restock_batch_items')->where('restock_batch_id', $batch->id)->delete();
                DB::table('restock_batches')->where('id', $batch->id)->delete();
            });
    }
};
