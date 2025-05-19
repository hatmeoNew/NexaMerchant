<?php

namespace Nicelizhi\Manage\Listeners;

use Nicelizhi\Manage\Mail\Order\CreatedNotification;
use Nicelizhi\Manage\Mail\Order\CanceledNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Nicelizhi\Shopify\Console\Commands\Order\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Nicelizhi\Shopify\Console\Commands\Order\PostOdoo;

class Order extends Base
{
    /**
     * After order is created
     *
     * @param  \Webkul\Sale\Contracts\Order  $order
     * @return void
     */
    public function afterCreated($order)
    {
        // send order to shopify
        $queue = config('app.name').':orders';
        // Artisan::queue((new Post())->getName(), ['--order_id'=> $order->id])->onConnection('rabbitmq')->onQueue($queue);
        Artisan::queue((new PostOdoo())->getName(), ['--order_id'=> $order->id])->onConnection('rabbitmq')->onQueue(config('app.name') . ':odoo_order');

        try {
            if (! core()->getConfigData('emails.general.notifications.emails.general.notifications.new_order')) {
                return;
            }

            $data = [
                'id' => $order->id,
                'expiry' => Carbon::now()->addHours(24)->toDateTimeString(),
            ];
            $key = Crypt::encrypt(json_encode($data));
            $order->key = $key;

            //Log::info('Order created listener order info: ' . $order);

            $this->prepareMail($order, new CreatedNotification($order));

        } catch (\Exception $e) {
            report($e);
        }


    }

    /**
     * Send cancel order mail.
     *
     * @param  \Webkul\Sales\Contracts\Order  $order
     * @return void
     */
    public function afterCanceled($order)
    {
        try {
            if (! core()->getConfigData('emails.general.notifications.emails.general.notifications.cancel_order')) {
                return;
            }

            $this->prepareMail($order, new CanceledNotification($order));



        } catch (\Exception $e) {
            report($e);
        }
    }
}
