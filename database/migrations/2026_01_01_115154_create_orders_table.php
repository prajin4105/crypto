<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['buy', 'sell']);
            $table->decimal('price', 20, 8);
            $table->decimal('amount', 20, 8);
            $table->decimal('remaining_amount', 20, 8);

            $table->enum('status', [
                'open',
                'partial',
                'filled',
                'cancelled'
            ])->default('open');

            $table->timestamps();

            // Indexes for performance
            $table->index(['market_id', 'status', 'type']);
            $table->index(['market_id', 'type', 'price', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
