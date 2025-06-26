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
        Schema::table('cart_rules', function (Blueprint $table) {
            // 添加is_global字段，默认为0（非全局规则）
            $table->tinyInteger('is_global')->after('sort_order')->default(0)
                ->comment('是否为全场通用规则，1=是，0=否');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_rules', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });
    }
};
