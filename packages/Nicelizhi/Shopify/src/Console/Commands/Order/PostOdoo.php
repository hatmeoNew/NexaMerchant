<?php

namespace Nicelizhi\Shopify\Console\Commands\Order;

use GuzzleHttp\Client;
use Webkul\Sales\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Nicelizhi\Shopify\Helpers\Utils;
use Illuminate\Support\Facades\Event;
use GuzzleHttp\Exception\ClientException;
use Nicelizhi\Shopify\Models\OdooOrder;
use Nicelizhi\Shopify\Models\OdooCustomer;
use Nicelizhi\Shopify\Models\OdooProducts;
use Webkul\Core\Models\CountryState;
use Webkul\Product\Models\Product;
use Nicelizhi\Shopify\Models\ShopifyProduct;
use Webkul\Customer\Repositories\CustomerRepository;

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
    protected $description = 'create Order odoo:order:post';
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
            $order_id = $this->option("order_id");

            if (!empty($order_id)) {
                $lists = Order::where(['status'=>'processing'])->where("id", $order_id)->select(['id'])->limit(1)->get();
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

        $order = Order::query()->findOrFail($id);

        $orderPayment = $order->payment;

        $postOrder = [];

        $line_items = [];

        $orderItems = $order->items;

        foreach ($orderItems as $orderItem) {

            $line_item = [];

            $line_item['name'] = $orderItem['name'];
            $line_item['price'] = $orderItem['price'];
            $line_item['requires_shipping'] = true;
            $line_item['discount_amount'] = $orderItem['discount_amount'];
            $line_item['qty_ordered'] = $orderItem['qty_ordered'];
            $line_item['default_code'] = $orderItem['sku'];

            $additional = $orderItem['additional'];
            // dd($additional['product_sku']);

            if (!empty($additional['selected_configurable_option'])) {
                $variant_id = $additional['selected_configurable_option'];
            } else {
                $variant_id = $additional['product_id'];//表示运费险订单
            }
            $shopifyInfo = Product::query()->where('id', $variant_id)->value('sku');
            list($shopify_product_id, $shopify_variant_id) = explode('-', $shopifyInfo);
            // dump($shopify_product_id . ' ~~~');
            $shopifyProduct = ShopifyProduct::query()->where('product_id', $shopify_product_id)->select('variants', 'images', 'options')->first();
            // dd($shopifyProduct);
            if (empty($shopifyProduct)) {
                dump('shopifyProduct is empty');
                Utils::sendFeishu('shopifyProduct is empty --order_id=' . $id) . ' website:' . $postOrder['website_name'];
                continue;
            }

            $options = [];
            foreach ($shopifyProduct['variants'] as $variants) {
                if ($variants['id'] == $shopify_variant_id) {
                    $additional['product_sku'] = $variants['sku'];
                    $options = [
                        'option1' => $variants['option1'],
                        'option2' => $variants['option2'],
                    ];
                    if (!empty($variants['option3'])) {
                        $options['option3'] = $variants['option2'];
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

                if (empty($options)) {
                    dump('options is empty');
                    Utils::sendFeishu('attributes & options is empty --order_id=' . $id) . ' website:' . $postOrder['website_name'];
                    continue;
                }

                $i = 0;
                foreach ($options as $option) {
                    if (empty($shopifyProduct['options'][$i])) {
                        $i++;
                        continue;
                    }
                    $attrName = $shopifyProduct['options'][$i];
                    $attrValue = $option;
                    $additional['attributes']['attribute_name'] = $attrName;
                    $additional['attributes']['option_label'] = $attrValue;

                    $additional['attributes'][] = [
                        'attribute_name' => $attrName,
                        'option_label' => $attrValue,
                    ];

                    $i++;
                }
            }

            $line_item['sku'] = $additional;

            dump($orderItem['sku'] . '~~~' . $orderItem['qty_ordered'] . ' ~~~~~'  . $orderItem['price'] . ' ~~~~~' . $orderItem['discount_amount']);

            // dump($line_item['sku']['product_id'] . ' ~ ' . $line_item['default_code']);
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

        $shipping_address = [
            "first_name" => $shipping_address->first_name,
            "last_name" => $shipping_address->last_name,
            "address1" => $shipping_address->address1,
            "phone" => $shipping_address->phone,
            "city" => $shipping_address->city,
            "province" => $shipping_address->state,
            'state_name' => CountryState::where('code', $shipping_address->state)->where('country_code', $shipping_address->country)->value('default_name'),
            "country" => $shipping_address->country,
            "zip" => $shipping_address->postcode
        ];

        $postOrder['shipping_address'] = $shipping_address;

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

        if (1 || config('odoo_api.enable')) {
            $odoo_url = config('odoo_api.host') . "/api/nexamerchant/order";
            try {
                $response = $client->post($odoo_url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . config('odoo_api.api_token'),
                    ],
                    'body' => json_encode($pOrder)
                ]);
                echo "Response Body: " . $response->getBody() . PHP_EOL;
                // dd();
                if ($response->getStatusCode() == 200) {
                    $response_body = json_decode($response->getBody(), true);
                    try {
                        $response_data = $response_body['result'];
                        // dd($response_data);
                        if (!empty($response_data['success']) && $response_data['success'] == true) {
                            // dd($response_data['data']);
                            try {
                                $this->syncOdooLog($response_data['data']);
                            } catch (\Throwable $th) {
                                echo $th->getMessage(), PHP_EOL;
                            }
                            echo $id . " post success \r\n";
                            return true;
                        } else {
                            echo $id . " post failed \r\n";
                            Utils::sendFeishu($response_data['message'] . ' --order_id=' . $id . ' website:' . $postOrder['website_name']);
                            return false;
                        }
                    } catch (\Throwable $th) {
                        echo $th->getMessage(), PHP_EOL;
                        Utils::sendFeishu($response->getBody() . ' --order_id=' . $id) . ' website:' . $postOrder['website_name'];
                        return false;
                    }
                }
                // dd($response);
                // dd();
            } catch (ClientException $e) {
                //var_dump($e);
                var_dump($e->getMessage());
                Log::error(json_encode($e->getMessage()));
                Utils::send($e->getMessage() . '--' . $id . " fix check it ");
                echo $e->getMessage() . " post failed";
                //continue;
                return false;
            }
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
                'com.cn', 'net.cn', 'org.cn', 'gov.cn',
                'com.hk', 'co.uk', 'org.uk', 'gov.uk',
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
