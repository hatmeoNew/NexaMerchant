<?php

namespace Nicelizhi\Shopify\Console\Commands\Fulfillments;

use Illuminate\Console\Command;
use Webkul\Sales\Models\Order;
use Illuminate\Support\Facades\Artisan;

class MakeOrderTask extends Command
{
    protected $signature = 'make:order:task';

    protected $description = '获取待发货订单, 发起同步数据脚本';

    public $diffDay = 1;

    public function handle()
    {
        // 获取待发货订单ID
        $maxId = 0;
        $i = 0;
        $limit = 100;
        while (true) {
            $ids = Order::where('status', 'processing')->where('id', '>', $maxId)->orderBy('id', 'ASC')->limit($limit)->pluck('id');
            $i = $i + $limit;

            if (empty($ids)) {
                break;
            }
            foreach ($ids as $id) {
                dump($id);
                Artisan::call((new CreateOdoo())->getName(), [
                    '--order_id' => $id,
                ]);

                $maxId = $id;
            }
        }
    }
}
