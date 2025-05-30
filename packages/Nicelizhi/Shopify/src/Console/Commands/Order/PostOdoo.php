<?php

namespace Nicelizhi\Shopify\Console\Commands\Order;

use GuzzleHttp\Client;
use Webkul\Sales\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Nicelizhi\Shopify\Helpers\Utils;
use Nicelizhi\Shopify\Models\OdooOrder;
use Nicelizhi\Shopify\Models\OdooCustomer;
use Nicelizhi\Shopify\Models\OdooProducts;
use Webkul\Core\Models\CountryState;
use Illuminate\Support\Facades\Artisan;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Models\ProductAttributeValue;

class PostOdoo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'odoo:order:post {--order_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create Order odoo:order:post erp';

    private $customerRepository = null;
    private $product_image = null;

    //protected ShopifyOrder $ShopifyOrder,
    //protected ShopifyStore $ShopifyStore,

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

            $order_id = $this->option("order_id");

            Artisan::queue((new \NexaMerchant\Feeds\Console\Commands\Klaviyo\Push())->getName(), ['--order_id'=> $order_id])->onConnection('rabbitmq')->onQueue('klaviyo:profiles');

            if (!empty($order_id)) {
                $lists = Order::where(['status' => 'processing'])->where("id", $order_id)->select(['id'])->limit(1)->get();
                // $lists = Order::where("id", ">=", $order_id)->select(['id'])->limit(50)->get();
                if (count($lists) == 0) {
                    $this->info("no order");
                    return false;
                }
            } else {
                $lists = [];
                $this->info("no order");
            }

            foreach ($lists as $list) {
                $this->info("start post order " . $list->id);
                $this->postOrder($list->id);
                $this->syncOrderPrice($list); // sync price to system
            }
        } catch (\Throwable $th) {
            $this->info($th->getMessage() . ' | ' . $th->getLine());
        }
    }

    /**
     *
     *
     * @param object orderitem
     *
     */
    public function syncOrderPrice($orderItem)
    {
        if ($orderItem->grand_total_invoiced == '0.0000') {
            $base_grand_total_invoiced = $orderItem->base_grand_total;
            $grand_total_invoiced = $orderItem->grand_total;
            Order::where(['id' => $orderItem->id])->update(['grand_total_invoiced' => $grand_total_invoiced, 'base_grand_total_invoiced' => $base_grand_total_invoiced]);
        }
    }

    public function postOrder($id)
    {
        $this->info("sync to order to odoo " . $id);

        $client = new Client();

        $order = $this->Order->findOrFail($id);

        $orderPayment = $order->payment;

        $postOrder = [];

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

        // dd($line_items);

        $shipping_address = $order->shipping_address;
        $postOrder['line_items'] = $line_items;

        $customer = [];
        $customer = [
            "first_name" => $shipping_address->first_name,
            "last_name"  => $shipping_address->last_name,
            "email"     => $shipping_address->email,
        ];
        $postOrder['customer'] = $customer;

        $shipping_address->phone = str_replace('undefined', '', $shipping_address->phone);
        $shipping_address->city = empty($shipping_address->city) ? $shipping_address->state : $shipping_address->city;

        // create user
        $customer = $this->customerRepository->findOneByField('email', $shipping_address->email);
        if (is_null($customer)) {
            $customer = $this->customerRepository->findOneByField('phone', $shipping_address->phone);
            if (is_null($customer)) {
                $data = [];
                $data['email'] = $shipping_address->email;
                $data['customer_group_id'] = 2;
                $data['first_name'] = $shipping_address->first_name;
                $data['last_name'] = $shipping_address->last_name;
                $data['gender'] = $shipping_address->gender;
                $data['phone'] = $shipping_address->phone;

                $this->createCuster($data);
            }
        }

        $state = $shipping_address->state;
        if (in_array($shipping_address->country, ['CZ', 'PL', 'DE', 'AT', 'CH'])) {
            $state = '';
        }

        $post_shipping_address = [
            "first_name" => $shipping_address->first_name,
            "last_name" => $shipping_address->last_name,
            "address1" => $shipping_address->address1,
            "phone" => $shipping_address->phone,
            "city" => $shipping_address->city,
            "province" => $state,
            'state_name' => CountryState::where('code', $shipping_address->state)->where('country_code', $shipping_address->country)->value('default_name'),
            "country" => $shipping_address->country,
            "zip" => $shipping_address->postcode
        ];

        $postOrder['shipping_address'] = $post_shipping_address;

        if ($orderPayment['method'] == 'codpayment') {
            $postOrder['payment_gateway_names'] = [
                "codPay",
                "cash_on_delivery"
            ];
            $order->grand_total = round($order->grand_total);
            $order->sub_total = round($order->sub_total);
            $order->discount_amount = round($order->discount_amount);
            $order->shipping_amount = round($order->shipping_amount);
        }

        $postOrder['payment'] = $orderPayment->toArray();
        $postOrder['created_at'] = $order->created_at;
        $postOrder['grand_total'] = $order->grand_total;
        $postOrder['tax_amount'] = $order->tax_amount;
        $postOrder['discount_amount'] = $order->discount_amount;
        $postOrder['send_receipt'] = false;
        $postOrder['current_total_discounts'] = $order->discount_amount;
        $postOrder['total_discount'] = $order->discount_amount;
        $postOrder['total_discounts'] = $order->discount_amount;
        $postOrder['order_number'] = $id;
        $postOrder['name'] = config('odoo_api.order_pre') . '#' . $id;
        $postOrder['currency'] = $order->order_currency_code;
        $postOrder['presentment_currency'] = $order->order_currency_code;
        $postOrder['website_name'] = self::getRootDomain(config('odoo_api.website_url'));

        $pOrder['order'] = $postOrder;
        // dd($pOrder);

        $odoo_url = config('odoo_api.host') . "/api/nexamerchant/external_order";
        try {
            $response = $client->post($odoo_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . config('odoo_api.api_token'),
                ],
                'body' => json_encode($pOrder)
            ]);
            // echo "Response Body: " . $response->getBody() . PHP_EOL;
            // dd();
            if ($response->getStatusCode() == 200) {
                $response_body = json_decode($response->getBody(), true);
                $response_data = $response_body['result'];
                if (!empty($response_data['success']) && $response_data['success'] == true) {

                    $this->syncOdooLog($response_data['data']);

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

                    // 同步飞书提醒
                    $notice = "Order " . $postOrder['name'] . "\r\n" . core()->currency($postOrder['grand_total']) . '，' . count($postOrder['line_items']) . ' items from ' . $postOrder['website_name'];
                    Utils::sendFeishuErp($notice);

                    echo $id . " post success \r\n";
                    return true;
                } else {
                    echo $id . " post failed \r\n";
                    Utils::sendFeishu($response_data['message'] . ' --order_id=' . $id . ' website:' . $postOrder['website_name']);
                    return false;
                }
            }
        } catch (ServerException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $message = "接口{$odoo_url}异常，请及时检查. status code:{$statusCode}" . PHP_EOL;
            $message .= '--order_id=' . $id . ' website:' . $postOrder['website_name'];
            Utils::sendFeishu($message);
            echo $message;
            return false;
        } catch (ClientException $e) {
            Log::error(json_encode($e->getMessage()));
            Utils::sendFeishu($e->getMessage() . '--' . $id . " fix check it ");
            echo $e->getMessage() . " post failed";
            return false;
        }

        echo $id . " end post \r\n";
    }

    public function syncOdooLog($data)
    {
        $orderData = $data['order_data'];
        $customerData = $data['customer_data'];
        $productsData = $data['product_data'];

        OdooOrder::create([
            'name'                => $orderData['name'],
            'partner_id'          => $orderData['partner_id'],
            'partner_invoice_id'  => $orderData['partner_invoice_id'],
            'partner_shipping_id' => $orderData['partner_shipping_id'],
            'pricelist_id'        => $orderData['pricelist_id'],
            'currency_id'         => $orderData['currency_id'],
            'payment_term_id'     => $orderData['payment_term_id'],
            'team_id'             => $orderData['team_id'],
            'user_id'             => $orderData['user_id'],
            'company_id'          => $orderData['company_id'],
            'warehouse_id'        => $orderData['warehouse_id'],
            'client_order_ref'    => $orderData['client_order_ref'],
            'origin'              => $orderData['origin'],
            'date_order'          => $orderData['date_order'],
            'state'               => $orderData['state'],
            'invoice_status'      => $orderData['invoice_status'],
            'picking_policy'      => $orderData['picking_policy'],
            'amount_tax'          => $orderData['amount_tax'],
            'amount_total'        => $orderData['amount_total'],
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        OdooCustomer::create([
            'name'                  => $customerData['name'],
            'email'                 => $customerData['email'],
            'phone'                 => $customerData['phone'],
            'mobile'                => $customerData['mobile'],
            'street'                => $customerData['street'],
            'street2'               => $customerData['street2'],
            'zip'                   => $customerData['zip'],
            'city'                  => $customerData['city'],
            'state_id'              => $customerData['state_id'],
            'country_id'            => $customerData['country_id'],
            'vat'                   => $customerData['vat'],
            'function'              => $customerData['function'],
            'title'                 => $customerData['title'],
            'company_id'            => $customerData['company_id'],
            'category_id'           => $customerData['category_id'],
            'user_id'               => $customerData['user_id'],
            'team_id'               => $customerData['team_id'],
            'lang'                  => $customerData['lang'],
            'tz'                    => $customerData['tz'],
            'active'                => $customerData['active'],
            'company_type'          => $customerData['company_type'],
            'is_company'            => $customerData['is_company'],
            'color'                 => $customerData['color'],
            'partner_share'         => $customerData['partner_share'],
            'commercial_partner_id' => $customerData['commercial_partner_id'],
            'type'                  => $customerData['type'],
            'signup_token'          => $customerData['signup_token'],
            'signup_type'           => $customerData['signup_type'],
            'signup_expiration'     => $customerData['signup_expiration'],
            'signup_url'            => $customerData['signup_url'],
            'partner_gid'           => $customerData['partner_gid'],
            'created_at'            => date('Y-m-d H:i:s'),
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);

        foreach ($productsData as $productData) {
            OdooProducts::create([
                'name'         => $productData['name'],
                'product_id'   => $productData['product_id'],
                'default_code' => $productData['default_code'],
                'type'         => $productData['type'],
                'list_price'   => $productData['list_price'],
                'currency_id'  => $productData['currency_id'],
                'uom_id'       => $productData['uom_id'],
                'categ_id'     => $productData['categ_id'],
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function createCuster($data)
    {
        $password = rand(100000, 10000000);
        Event::dispatch('customer.registration.before');

        $data = array_merge($data, [
            'password'    => bcrypt($password),
            'is_verified' => 1,
            'subscribed_to_news_letter' => 1,
        ]);

        $this->customerRepository->create($data);
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
