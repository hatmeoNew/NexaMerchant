<?php

namespace Nicelizhi\Shopify\Console\Commands\Fulfillments;

use Illuminate\Console\Command;
use Webkul\Sales\Models\Order;
use Illuminate\Support\Facades\Artisan;

class MakeOrderTask extends Command
{
    protected $signature = 'make:order:task {minId}';

    protected $description = '获取待发货订单, 发起同步数据脚本';

    public function handle()
    {
        Order::where('status', 'processing')->where('id', '>=', $this->argument('minId'))->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                dump($order->id);
                Artisan::call((new CreateOdoo())->getName(), [
                    '--order_id' => $order->id,
                ]);
            }
        });
    }
}
