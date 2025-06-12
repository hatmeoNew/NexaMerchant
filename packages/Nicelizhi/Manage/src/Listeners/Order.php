<?php

namespace Nicelizhi\Manage\Listeners;

use Nicelizhi\Manage\Mail\Order\CreatedNotification;
use Nicelizhi\Manage\Mail\Order\CanceledNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Nicelizhi\Shopify\Console\Commands\Order\Post;
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
        if (config('onebuy.is_sync_erp')) {
            Artisan::queue((new PostOdoo())->getName(), ['--order_id'=> $order->id])->onConnection('rabbitmq')->onQueue(config('app.name') . ':odoo_order');
        } else {
            Artisan::queue((new Post())->getName(), ['--order_id'=> $order->id])->onConnection('redis')->onQueue('commands');
        }

        try {
            if (! core()->getConfigData('emails.general.notifications.emails.general.notifications.new_order')) {
                return;
            }

            if (config('onebuy.is_sync_klaviyo')) {
                Log::info('klaviyo_event_place_order');
            } else {
                $this->prepareMail($order, new CreatedNotification($order));
            }

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
