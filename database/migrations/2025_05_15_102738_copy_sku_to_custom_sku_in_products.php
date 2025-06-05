<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 更新 custom_sku = sku
            DB::table('products')->update([
                'custom_sku' => DB::raw('sku')
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 如果需要还原，可以清空 custom_sku
            DB::table('products')->update([
                'custom_sku' => null
            ]);
        });
    }
};
