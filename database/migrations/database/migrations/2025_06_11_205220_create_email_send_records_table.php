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
        Schema::create('email_send_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_id')->nullable()->comment('关联的订单ID');
            $table->string('email', 191)->comment('收件人邮箱');
            $table->string('metric_name', 191)->comment('事件名称');
            $table->enum('send_status', ['success', 'failed'])->default('success')->comment('发送结果状态');
            $table->text('failure_reason')->nullable()->comment('失败原因');
            $table->timestamps();

            // 添加外键约束
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_send_records');
    }
};