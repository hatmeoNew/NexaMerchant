<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('products')
            ->where('type', 'configurable')
            ->whereNull('custom_sku')
            ->update(['custom_sku' => DB::raw('sku')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('products')
            ->where('type', 'configurable')
            ->whereNotNull('custom_sku')
            ->whereRaw('custom_sku = sku')
            ->update(['custom_sku' => null]);
    }
};
