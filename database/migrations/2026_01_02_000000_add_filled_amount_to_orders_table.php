<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add filled_amount column for better tracking
            // filled_amount = amount - remaining_amount
            // This is redundant but useful for quick queries
            $table->decimal('filled_amount', 20, 8)->default(0)->after('remaining_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('filled_amount');
        });
    }
};

