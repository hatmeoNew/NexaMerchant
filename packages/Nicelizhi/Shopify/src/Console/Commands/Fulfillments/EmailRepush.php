<?php

namespace Nicelizhi\Shopify\Console\Commands\Fulfillments;

use Illuminate\Console\Command;
use Webkul\Sales\Models\Order;
use Illuminate\Support\Facades\Artisan;
use NexaMerchant\Feeds\Console\Commands\Klaviyo\SendKlaviyoEvent;

class EmailRepush extends Command
{
    protected $signature = 'email_repush {metric_type?}';

    protected $description = '重推历史订单';

    public function handle()
    {
        Order::where('status', 'processing')->whereBetween('created_at', ['2025-06-01', '2025-06-17'])->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                Artisan::queue((new SendKlaviyoEvent())->getName(), ['--order_id'=> $order->id, '--metric_type' => 100])->onConnection('rabbitmq')->onQueue(config('app.name') . ':klaviyo_event_place_order');
            }
        });
    }
}
