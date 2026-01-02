<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buy_order_id')
                  ->constrained('orders')
                  ->cascadeOnDelete();

            $table->foreignId('sell_order_id')
                  ->constrained('orders')
                  ->cascadeOnDelete();

            $table->foreignId('market_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->decimal('price', 20, 8);
            $table->decimal('amount', 20, 8);
            $table->decimal('fee_amount', 20, 8)->default(0);
            $table->string('fee_currency', 10)->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['market_id', 'created_at']);
            $table->index(['buy_order_id']);
            $table->index(['sell_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
