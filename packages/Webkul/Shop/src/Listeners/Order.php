<?php

namespace Webkul\Shop\Listeners;

use Webkul\Shop\Mail\Order\CreatedNotification;
use Webkul\Shop\Mail\Order\CanceledNotification;
use Webkul\Shop\Mail\Order\CommentedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use NexaMerchant\Feeds\Console\Commands\Klaviyo\SendKlaviyoEvent;

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
                Log::info('new_order config is not enabled');
                return;
            }

            if (config('onebuy.is_sync_klaviyo')) {
                Log::info('klaviyo_event_place_order222');
                Artisan::queue((new SendKlaviyoEvent())->getName(), ['--order_id'=> $order->id, '--metric_type' => 100])->onConnection('rabbitmq')->onQueue(config('app.name') . ':klaviyo_event_place_order');
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

    /**
     * Send order comment mail.
     *
     * @param  \Webkul\Sales\Contracts\OrderComment  $comment
     * @return void
     */
    public function afterCommented($comment)
    {
        if (! $comment->customer_notified) {
            return;
        }

        try {
            /**
             * Email to customer.
             */
            $this->prepareMail($comment, new CommentedNotification($comment));
        } catch (\Exception $e) {
            report($e);
        }
    }
}