<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 定义需要插入的管理员数据
        $admins = [
            [
                'name' => '宋红慧',
                'email' => 'lily@heomai.com',
                'password' => 'vip@888888'
            ],
        ];

        // 遍历管理员数据并插入
        foreach ($admins as $admin) {
            // 检查邮箱是否已存在
            $existingAdmin = DB::table('admins')->where('email', $admin['email'])->first();

            if ($existingAdmin) {
                // 邮箱已存在，跳过
                echo "跳过已存在的管理员: {$admin['email']}\n";
                DB::table('admins')->where('email', $admin['email'])->update([
                    'password'   => bcrypt($admin['password']),
                    'name'       => $admin['name'],
                ]);
                continue;
            }

            // 插入新管理员记录
            DB::table('admins')->insert([
                'name'       => $admin['name'],
                'email'      => $admin['email'],
                'password'   => bcrypt($admin['password']),
                'api_token'  => Str::random(80),
                'status'     => 1,
                'role_id'    => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            echo "创建管理员成功: {$admin['email']}\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 定义需要删除的管理员邮箱
        $adminEmails = [
            'lily@heomai.com',
        ];

        // 删除这些管理员账户
        DB::table('admins')
            ->whereIn('email', $adminEmails)
            ->delete();
    }
};
