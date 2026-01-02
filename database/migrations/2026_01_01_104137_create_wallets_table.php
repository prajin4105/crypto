<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    $table->string('currency'); // BTC, ETH, USDT
    $table->decimal('balance', 20, 8)->default(0);
    $table->decimal('locked_balance', 20, 8)->default(0);

    $table->string('wallet_address')->nullable();
    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->unique(['user_id', 'currency']);
    
    // Indexes for performance
    $table->index(['user_id', 'currency']);
    $table->index('currency');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
