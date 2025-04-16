<?php

namespace Nicelizhi\Shopify\Console\Commands\Order;

use GuzzleHttp\Client;
use Webkul\Sales\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use GuzzleHttp\Exception\ClientException;
use Nicelizhi\Shopify\Models\OdooOrder;
use Nicelizhi\Shopify\Models\OdooCustomer;
use Nicelizhi\Shopify\Models\OdooProducts;
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

            if (!empty($order_id)) {
                $lists = Order::where(['status'=>'processing'])->where("id", $order_id)->select(['id'])->limit(1)->get();
                // $lists = Order::where("id", ">=", $order_id)->select(['id'])->limit(50)->get();
            } else {
                $lists = [];
                echo "no order", PHP_EOL;
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
        // dd($order->toArray());

        $orderPayment = $order->payment;
        // dd($orderPayment->toArray());

        $postOrder = [];

        $line_items = [];

        $products = $order->items;
        // dd(count($products));

        $q_ty = 0;
        foreach ($products as $k => $product) {
            // if ($k == 0) continue;
            // dump($product->toArray());
            $sku = $product['additional'];

            $attributes = "";

            if (isset($sku['attributes'])) {
                foreach ($sku['attributes'] as $sku_attribute) {
                    $attributes .= $sku_attribute['attribute_name'] . ":" . $sku_attribute['option_label'] . ";";
                }
            }

            $variant_id = $sku['selected_configurable_option'];
            if (isset($sku['product_sku']) && strpos($sku['product_sku'], '-') !== false) {
                $skuInfo = explode('-', $sku['product_sku']);
                if (!isset($skuInfo[1])) {
                    $this->error("have error" . $id);
                    return false;
                }
                $variant_id = $skuInfo[1];
            }

            if (!is_numeric($variant_id)) {
                $variant_id = $sku['selected_configurable_option'];
            }

            $line_item = [];
            $line_item['price'] = $product['price'];
            $price_set = [];
            $price_set['shop_money'] = [
                'amount' => $product['price'],
                'currency_code' => $order->order_currency_code
            ];
            $line_item['price_set'] = $price_set;

            $line_item['title'] = $product['name'] . $product['sku'] . $attributes;
            $line_item['name'] = $product['name'] . $product['sku'] . $attributes;

            if (!empty($sku['attributes'])) {
                $sku['attributes'] = array_values($sku['attributes']);
            } else {
                $sku['attributes'] = [];
            }

            $line_item['sku'] = $sku;

            $product_image = $this->product_image->where('product_id', $variant_id)->value('path');

            if (!empty($product_image)) {
                $line_item['properties'] = [
                    [
                        'name' => 'image',
                        'value' => $product_image
                    ]
                ];
            }

            $q_ty += $product['qty_ordered'];
            $line_item['requires_shipping'] = true;
            $line_item['discount_amount'] = $product['discount_amount'];
            $line_item['qty_ordered'] = $product['qty_ordered'];
            $line_item['default_code'] = $product['sku'];

            dump($product['sku'] . '~~~' . $product['qty_ordered'] . ' ~~~~~'  . $product['price'] . ' ~~~~~' . $product['discount_amount']);

            // dump($line_item['sku']['product_id'] . ' ~ ' . $line_item['default_code']);
            array_push($line_items, $line_item);
        }

        // dd();

        $shipping_address = $order->shipping_address;
        $billing_address = $order->billing_address;
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

        $billing_address = [
            "first_name" => $billing_address->first_name,
            "last_name" => $billing_address->last_name,
            "address1" => $billing_address->address1,
            //$input['phone_full'] = str_replace('undefined+','', $input['phone_full']);
            "phone" => $shipping_address->phone,
            "city" => $billing_address->city,
            "province" => $billing_address->state,
            "country" => $billing_address->country,
            "zip" => $billing_address->postcode
        ];
        $postOrder['billing_address'] = $billing_address;
        $postOrder['payment'] = $orderPayment->toArray();
        echo $postOrder['payment']['method'], PHP_EOL;

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
            "country" => $shipping_address->country,
            "zip" => $shipping_address->postcode
        ];

        $postOrder['shipping_address'] = $shipping_address;

        $transactions = [
            [
                "kind" => "sales",
                "status" => "success",
                "amount" => $order->grand_total,
            ]
        ];

        $financial_status = "paid";

        if ($orderPayment['method'] == 'codpayment') {
            $financial_status = "pending";
            $postOrder['payment_gateway_names'] = [
                "codPay",
                "cash_on_delivery"
            ];

            // when cod payment need format the price to Round integer
            $order->grand_total = round($order->grand_total);
            $order->sub_total = round($order->sub_total);
            $order->discount_amount = round($order->discount_amount);
            $order->shipping_amount = round($order->shipping_amount);

            $transactions = [
                [
                    "kind" => "sale",
                    "status" => "pending",
                    "amount" => $order->grand_total,
                    "gateway" => "Cash on Delivery (COD)"
                ]
            ];
        }

        //$postOrder['financial_status'] = "paid";
        $postOrder['financial_status'] = $financial_status;
        $postOrder['transactions'] = $transactions;
        $postOrder['current_subtotal_price'] = $order->sub_total;
        $postOrder['created_at'] = $order->created_at;
        $postOrder['grand_total'] = $order->grand_total;
        $postOrder['tax_amount'] = $order->tax_amount;
        $postOrder['discount_amount'] = $order->discount_amount;

        $current_subtotal_price_set = [
            'shop_money' => [
                "amount" => $order->sub_total,
                "currency_code" => $order->order_currency_code,
            ],
            'presentment_money' => [
                "amount" => $order->sub_total,
                "currency_code" => $order->order_currency_code,
            ]
        ];
        $postOrder['current_subtotal_price_set'] = $current_subtotal_price_set;


        $total_shipping_price_set = [
            "shop_money" => [
                "amount" => $order->shipping_amount,
                "currency_code" => $order->order_currency_code,
            ],
            "presentment_money" => [
                "amount" => $order->shipping_amount,
                "currency_code" => $order->order_currency_code,
            ]
        ];

        $postOrder['total_shipping_price_set'] = $total_shipping_price_set;
        $postOrder['send_receipt'] = false;
        $postOrder['current_total_discounts'] = $order->discount_amount;
        $current_total_discounts_set = [
            'shop_money' => [
                'amount' => $order->discount_amount,
                'currency_code' => $order->order_currency_code
            ],
            'presentment_money' => [
                'amount' => $order->discount_amount,
                'currency_code' => $order->order_currency_code
            ]
        ];
        $postOrder['current_total_discounts_set'] = $current_total_discounts_set;
        $postOrder['total_discount'] = $order->discount_amount;
        $total_discount_set = [];
        $total_discount_set = [
            'shop_money' => [
                'amount' => $order->discount_amount,
                'currency_code' => $order->order_currency_code
            ],
            'presentment_money' => [
                'amount' => $order->discount_amount,
                'currency_code' => $order->order_currency_code
            ]
        ];
        $postOrder['total_discount_set'] = $total_discount_set;
        $postOrder['total_discounts'] = $order->discount_amount;

        $shipping_lines = [];
        $shipping_lines = [
            'price' => $order->shipping_amount,
            'code' => 'Standard',
            "title" => "Standard Shipping",
            "source" => "us_post",
            "tax_lines" => [],
            "carrier_identifier" => "third_party_carrier_identifier",
            "requested_fulfillment_service_id" => "third_party_fulfillment_service_id",
            "price_set" => [
                'shop_money' => [
                    'amount' => $order->shipping_amount,
                    'currency_code' => $order->order_currency_code
                ],
                'presentment_money' => [
                    'amount' => $order->shipping_amount,
                    'currency_code' => $order->order_currency_code
                ]
            ]
        ];

        $postOrder['shipping_lines'][] = $shipping_lines;
        $postOrder['buyer_accepts_marketing'] = true;
        $postOrder['order_number'] = $id;
        $postOrder['name'] = config('odoo_api.order_pre') . '#' . $id;
        $postOrder['currency'] = $order->order_currency_code;
        $postOrder['presentment_currency'] = $order->order_currency_code;
        $postOrder['website_name'] = self::getRootDomain(config('odoo_api.website_url'));
        $pOrder['order'] = $postOrder;
        // dd($postOrder);
        if (1 || config('odoo_api.enable')) {
            $odoo_url = config('odoo_api.host');
            $odoo_url = $odoo_url . "/api/nexamerchant/order";
            // dd($odoo_url);
            // dd $odoo_url, PHP_EOL;
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
                            \Nicelizhi\Shopify\Helpers\Utils::sendFeishu($response_data['message'] . ' --order_id=' . $id);
                            return false;
                        }
                    } catch (\Throwable $th) {
                        echo $th->getMessage(), PHP_EOL;
                        \Nicelizhi\Shopify\Helpers\Utils::sendFeishu($response->getBody() . ' --order_id=' . $id);
                        return false;
                    }
                }
                // dd($response);
                // dd();
            } catch (ClientException $e) {
                //var_dump($e);
                var_dump($e->getMessage());
                Log::error(json_encode($e->getMessage()));
                \Nicelizhi\Shopify\Helpers\Utils::send($e->getMessage() . '--' . $id . " fix check it ");
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
