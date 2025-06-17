<?php

namespace Webkul\Admin\Listeners;

use Webkul\Admin\Mail\Order\CreatedNotification;
use Webkul\Admin\Mail\Order\CanceledNotification;
use NexaMerchant\Feeds\Console\Commands\Klaviyo\SendKlaviyoEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
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
        try {
            if (! core()->getConfigData('emails.general.notifications.emails.general.notifications.new_order')) {
                return;
            }

            // send email
            if (1 || config('onebuy.is_sync_klaviyo')) {
                Log::info('klaviyo_event_place_order111');
                Artisan::queue((new SendKlaviyoEvent())->getName(), ['--order_id'=> $order->id, '--metric_type' => 100])->onConnection('rabbitmq')->onQueue(config('app.name') . ':klaviyo_event_place_order');
            } else {
                // $this->prepareMail($order, new CreatedNotification($order));
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

            // $this->prepareMail($order, new CanceledNotification($order));
        } catch (\Exception $e) {
            report($e);
        }
    }
}
