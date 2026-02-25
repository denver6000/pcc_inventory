<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restock_batches', function (Blueprint $table) {
            $table->string('batch_code')->nullable()->after('id');
        });

        // Backfill any existing rows without a batch_code to keep UI stable
        DB::table('restock_batches')
            ->whereNull('batch_code')
            ->orderBy('id')
            ->get()
            ->each(function ($row, $index) {
                $code = sprintf('RST-LEGACY-%03d', $index + 1);
                DB::table('restock_batches')
                    ->where('id', $row->id)
                    ->update(['batch_code' => $code]);
            });
    }

    public function down(): void
    {
        Schema::table('restock_batches', function (Blueprint $table) {
            $table->dropColumn('batch_code');
        });
    }
};
