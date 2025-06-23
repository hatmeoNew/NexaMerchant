<?php

namespace Nicelizhi\Shopify\Console\Commands\Fulfillments;

use Illuminate\Console\Command;
use Webkul\Sales\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Nicelizhi\Manage\Helpers\Queue\RabbitMQ;
use Nicelizhi\Shopify\Models\OdooOrder;

class MakeOrderTask extends Command
{
    protected $signature = 'make:order:task {minId?}';

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

        $rabbitMQ = new RabbitMQ();
        $channel = str_replace(' ', '_', strtolower($prefix)) . '_order_shipping';
        // dd($channel);
        $rabbitMQ->consume($channel, function ($message) {

            $taskInfo = json_decode($message, true);
            $erp_orderId = $taskInfo['order_id'];
            $odooOrder = OdooOrder::query()->where('user_id', '=', $erp_orderId)->first();
            if (!$odooOrder) {
                dump('not found');
                return true;
            }

            Artisan::call((new CreateOdoo())->getName(), [
                '--order_id' => $odooOrder->origin
            ]);

            return true;
        });
    }
}
