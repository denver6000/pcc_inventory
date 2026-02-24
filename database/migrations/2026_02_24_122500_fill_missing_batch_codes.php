<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('restock_batches', 'batch_code')) {
            return; // earlier migration should have added it
        }

        // For any rows that already have line items but no batch_code, assign a deterministic code
        $groups = DB::table('restock_batches')
            ->whereNull('batch_code')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => optional($row->created_at)->format('Ymd') ?? now()->format('Ymd'));

        foreach ($groups as $ymd => $rows) {
            $counter = 0;
            foreach ($rows as $row) {
                $counter++;
                $code = sprintf('RST-%s-%03d', $ymd, $counter);
                DB::table('restock_batches')->where('id', $row->id)->update(['batch_code' => $code]);
            }
        }
    }

    public function down(): void
    {
        // We won't null them out again; keep assigned codes
    }
};
