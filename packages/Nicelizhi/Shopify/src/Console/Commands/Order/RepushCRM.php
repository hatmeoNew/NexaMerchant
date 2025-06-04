<?php

namespace Nicelizhi\Shopify\Console\Commands\Order;

use Webkul\Sales\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Nicelizhi\Shopify\Helpers\Utils;
use Webkul\Product\Models\Product;
use Nicelizhi\Shopify\Models\ShopifyProduct;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Models\ProductAttributeValue;

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
    private $customerRepository = null;


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

        $webSiteName = self::getRootDomain(config('odoo_api.website_url'));

        $order = Order::query()->findOrFail($id);

        $orderPayment = $order->payment;

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

            if (!empty($additional['selected_configurable_option'])) {
                $variant_id = $additional['selected_configurable_option'];
            } else {
                $variant_id = $additional['product_id']; //表示运费险订单
            }

            $line_item['is_shipping'] = $variant_id == env('ONEBUY_RETURN_SHIPPING_INSURANCE_PRODUCT_ID');

            $shopifyInfo = Product::query()->where('id', $variant_id)->value('sku');
            list($shopify_product_id, $shopify_variant_id) = explode('-', $shopifyInfo);
            $shopifyProduct = ShopifyProduct::query()->where('product_id', $shopify_product_id)->select('variants', 'images', 'options')->first();
            if (empty($shopifyProduct)) {
                dump('shopifyProduct is empty');
                Utils::sendFeishu('shopifyProduct is empty --order_id=' . $id . ' website:' . $webSiteName);
                continue;
            }

            $options = [];
            foreach ($shopifyProduct['variants'] as $variants) {
                if ($variants['id'] == $shopify_variant_id) {
                    $additional['product_sku'] = $variants['sku'];

                    if (!empty($variants['option1'])) {
                        $options['option1'] = $variants['option1'];
                    }
                    if (!empty($variants['option2'])) {
                        $options['option2'] = $variants['option2'];
                    }
                    if (!empty($variants['option3'])) {
                        $options['option3'] = $variants['option3'];
                    }
                    foreach ($shopifyProduct['images'] as $images) {
                        if ($variants['image_id'] == $images['id']) {
                            $additional['img'] = $images['src'];
                            break;
                        }
                    }
                    // 如果没有图片，则取第一个图片
                    if (empty($additional['img'])) {
                        $additional['img'] = $shopifyProduct['images'][0]['src'];
                    }
                    break;
                }
            }

            if (!empty($additional['attributes'])) {
                $additional['attributes'] = array_values($additional['attributes']);
            } else {
                // dump($options);
                $additional['attributes'] = [];

                // 非运费险订单才需要属性
                if ($variant_id != env('ONEBUY_RETURN_SHIPPING_INSURANCE_PRODUCT_ID')) {

                    $i = 0;
                    foreach ($options as $option) {
                        if (empty($shopifyProduct['options'][$i])) {
                            $i++;
                            continue;
                        }
                        $attrName = $shopifyProduct['options'][$i]['name'];
                        $attrValue = $option;

                        $additional['attributes'][] = [
                            'attribute_name' => $attrName,
                            'option_label' => $attrValue,
                        ];

                        $i++;
                    }
                }
            }

            $url_key = ProductAttributeValue::query()->where('product_id', $variant_id)->where('attribute_id', 3)->value('text_value');
            if (!$line_item['is_shipping'] && !empty($url_key)) {
                $additional['product_url'] = rtrim(env('SHOP_URL'), '/') . '/products/' . $url_key;
            } else {
                $additional['product_url'] = '';
            }

            $line_item['sku'] = $additional;

            $q_ty += $orderItem['qty_ordered'];

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

        // order check
        // for cod order need check the order
        if ($orderPayment['method'] == 'codpayment') {
            Artisan::queue("GooglePlaces:check-order", ['--order_id' => $id])->onConnection('redis')->onQueue('order-checker'); // push to queue for check order
        }
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

    function getRootDomain($url)
    {
        // 解析主机名
        $host = parse_url($url, PHP_URL_HOST);

        // 如果没有 host，尝试从路径中取
        if (!$host) {
            $host = $url;
        }

        // 转换为小写
        $host = strtolower($host);

        // 去掉前缀的 www.
        $host = preg_replace('/^www\./', '', $host);

        // 用点号分隔
        $parts = explode('.', $host);

        // 如果是IP或localhost直接返回
        if (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost') {
            return $host;
        }

        $count = count($parts);

        // 多级域名判断，默认返回最后两个
        if ($count >= 2) {
            // 判断特殊的二级后缀情况，比如 .com.cn, .co.uk 等
            $commonTlds = [
                'com.cn',
                'net.cn',
                'org.cn',
                'gov.cn',
                'com.hk',
                'co.uk',
                'org.uk',
                'gov.uk',
            ];
            $lastTwo = $parts[$count - 2] . '.' . $parts[$count - 1];

            if (in_array($lastTwo, $commonTlds) && $count >= 3) {
                return $parts[$count - 3] . '.' . $lastTwo;
            }

            return $lastTwo;
        }

        return $host;
    }
}