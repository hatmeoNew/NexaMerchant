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
        Schema::table('email_send_records', function (Blueprint $table) {
            // 添加 sender 字段，默认为 'system'
            $table->string('sender', 191)->default('system')
                  ->after('metric_name') // 字段位置，可根据需要调整
                  ->comment('邮件发送人，系统发送时默认为 system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_send_records', function (Blueprint $table) {
            $table->dropColumn('sender');
        });
    }
};
