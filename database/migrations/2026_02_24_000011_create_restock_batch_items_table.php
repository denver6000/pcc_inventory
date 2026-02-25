<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restock_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->float('quantity_added');
            $table->float('cost_per_unit');  // snapshot at restock time
            $table->float('subtotal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_batch_items');
    }
};
