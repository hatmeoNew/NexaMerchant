<?php

namespace Nicelizhi\Shopify\Console\Commands\Fulfillments;

use Illuminate\Console\Command;
use Webkul\Sales\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Nicelizhi\Manage\Helpers\Queue\RabbitMQ;

class MakeOrderTask extends Command
{
    protected $signature = 'erp:shipping_order:sync {minId?}';

    protected $description = '获取待发货订单, 发起同步数据脚本';

    public function handle1()
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

    public function handle()
    {
        set_time_limit(0);

        $prefix = env('SHOPIFY_ORDER_PRE');

        try {
            $rabbitMQ = new RabbitMQ();
            $channel = str_replace(' ', '_', strtolower($prefix)) . '_order_shipping';
            // dd($channel);
            $rabbitMQ->consume($channel, function ($message) {

                $taskInfo = json_decode($message, true);
                $orderId = explode('#', $taskInfo['name'])[1];
                dump($orderId);
                Artisan::call((new CreateOdoo())->getName(), [
                    '--order_id' => $orderId
                ]);

                return true;
            });
        } catch (\Throwable $th) {
            dump($th->getMessage());
        }
    }
}
