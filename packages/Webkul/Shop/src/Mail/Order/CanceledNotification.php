<?php


namespace Webkul\Shop\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;

class CanceledNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  \Webkul\Sales\Contracts\Order  $order
     * @return void
     */
    public function __construct(public $order)
    {
        $data = [
            'id' => $this->order->id,
            'expiry' => Carbon::now()->addHours(24)->toDateTimeString(),
        ];
        $key = Crypt::encrypt(json_encode($data));
        $this->order->key =  $key;
    }

    public function build()
    {
        return $this->from(core()->getSenderEmailDetails()['email'], core()->getSenderEmailDetails()['name'])
            ->to($this->order->customer_email, $this->order->customer_full_name)
            ->subject(trans('admin::app.emails.orders.canceled.subject'))
            ->view('shop::shop.orders.canceled');
    }
}