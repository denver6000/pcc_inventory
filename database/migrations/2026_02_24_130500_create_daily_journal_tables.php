<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_journals', function (Blueprint $table) {
            $table->id();
            $table->date('journal_date')->index();
            $table->string('kind'); // restock | produce | consume | adjust
            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restock_batch_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction'); // in | out
            $table->float('quantity');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->timestamp('captured_at');
            $table->json('items');
            $table->json('products');
            $table->json('restock_batch_items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('restock_batches', function (Blueprint $table) {
            $table->foreignId('journal_id')->nullable()->after('batch_code')->constrained('daily_journals')->nullOnDelete();
        });

        Schema::table('restock_batch_items', function (Blueprint $table) {
            $table->foreignId('journal_line_id')->nullable()->after('restock_batch_id')->constrained('daily_journal_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restock_batch_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_line_id');
        });

        Schema::table('restock_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_id');
        });

        Schema::dropIfExists('daily_snapshots');
        Schema::dropIfExists('daily_journal_lines');
        Schema::dropIfExists('daily_journals');
    }
};
