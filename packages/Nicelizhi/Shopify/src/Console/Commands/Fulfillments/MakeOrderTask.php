<?php

namespace Nicelizhi\Shopify\Console\Commands\Fulfillments;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Webkul\Sales\Models\Order;
use Illuminate\Support\Facades\Artisan;

class MakeOrderTask extends Command
{
    protected $signature = 'make:order:task';

    protected $description = '获取待发货订单, 写入队列';

    public $diffDay = 1;

    public function handle()
    {
        // 获取待发货订单ID
        $ids = Order::where('status', '=', 'processing')->where('created_at', '<', Carbon::now()->subDays($this->diffDay))->pluck('id');
        foreach ($ids as $id) {
            Artisan::call((new CreateOdoo())->getName(), [
                '--order_id' => $id,
            ]);
        }
    }
}
