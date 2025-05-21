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
        Schema::table('products', function (Blueprint $table) {
            if  (!Schema::hasColumn('products', 'custom_sku')) {
                $table->string('custom_sku', 191)->nullable()->after('sku');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if  (Schema::hasColumn('products', 'custom_sku')) {
                $table->dropColumn('custom_sku');
            }
        });
    }
};
