<?php

namespace Nicelizhi\Shopify\Console\Commands\Order;

use Webkul\Sales\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Customer\Repositories\CustomerRepository;

class RepushCRM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repush_crm {order_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'repush crm';

    private $product_image = null;


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("start post order");

        try {
            $this->customerRepository = app(CustomerRepository::class);
            $this->Order              = new Order();
            $this->product            = new \Webkul\Product\Models\Product();
            $this->product_image      = new \Webkul\Product\Models\ProductImage();

            $order_id = $this->argument("order_id");

            if (!empty($order_id)) {
                $lists = Order::where(['status' => 'processing'])->where("id", $order_id)->select(['id'])->limit(1)->get();
                if (count($lists) == 0) {
                    $this->info("no order");
                    return false;
                }
            } else {
                $lists = [];
                $this->info("no order");
            }

            foreach ($lists as $list) {
                $this->postOrder($list->id);
            }
        } catch (\Throwable $th) {
            $this->info($th->getMessage() . ' | ' . $th->getLine());
        }
    }

    public function postOrder($id)
    {
        $this->info("sync to order to odoo " . $id);

        $order = $this->Order->findOrFail($id);

        $orderPayment = $order->payment;

        $line_items = [];

        $orderItems = $order->items;
        $q_ty = 0;
        foreach ($orderItems as $orderItem) {

            $line_item = [];

            $line_item['name'] = $orderItem['name'];
            $line_item['price'] = $orderItem['price'];
            $line_item['requires_shipping'] = true;
            $line_item['discount_amount'] = $orderItem['discount_amount'];
            $line_item['qty_ordered'] = $orderItem['qty_ordered'];

            $additional = $orderItem['additional'];
            $variant_id = $additional['selected_configurable_option'] ?: $orderItem['product_id'];
            if (empty($additional['product_sku'])) {
                $additional['img'] = $this->product_image->where('product_id', $variant_id)->value('path');
            }
            $additional['product_sku'] = $this->product->where('id', $variant_id)->value('custom_sku');

            $line_item['is_shipping'] = $variant_id == env('ONEBUY_RETURN_SHIPPING_INSURANCE_PRODUCT_ID');

            if (!empty($additional['attributes'])) {
                $additional['attributes'] = array_values($additional['attributes']);
            } else {
                $additional['attributes'] = [];
            }

            $url_key = ProductAttributeValue::query()->where('product_id', $variant_id)->where('attribute_id', 3)->value('text_value');
            if (!$line_item['is_shipping'] && !empty($url_key)) {
                $additional['product_url'] = rtrim(env('SHOP_URL'), '/') . '/products/' . $url_key;
            } else {
                $additional['product_url'] = '';
            }

            $line_item['sku'] = $additional;

            // 是否是运费险订单
            $line_item['is_shipping'] = $additional['product_id'] == env('ONEBUY_RETURN_SHIPPING_INSURANCE_PRODUCT_ID') ? true : false;

            $q_ty += $orderItem['qty_ordered'];

            array_push($line_items, $line_item);
        }

        $shipping_address = $order->shipping_address;

        // 同步给CRM ---------------start----------------------
        $cnv_id = explode('-', $orderPayment['method_title']);
        $crm_channel = config('onebuy.crm_channel');
        $crm_url = config('onebuy.crm_url');
        $url = $crm_url . "/api/offers/callBack?refer=" . $cnv_id[1] . "&revenue=" . $order->grand_total . "&currency_code=" . $order->order_currency_code . "&channel_id=" . $crm_channel . "&q_ty=" . $q_ty . "&email=" . $shipping_address->email . "&order_id=" . $id;
        $res = $this->get_content($url);
        Log::info("post to bm 2 url " . $url . " res " . json_encode($res));
        // 同步给CRM ---------------end------------------------

        if ($orderPayment['method'] == 'codpayment') {
            Artisan::queue("GooglePlaces:check-order", ['--order_id' => $id])->onConnection('redis')->onQueue('order-checker'); // push to queue for check order
        }

        echo $id . " end post \r\n";
    }


    private function get_content($URL)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $URL);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}
