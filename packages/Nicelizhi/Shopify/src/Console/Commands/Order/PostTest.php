<?php

namespace Nicelizhi\Shopify\Console\Commands\Order;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Webkul\Sales\Repositories\OrderRepository;
use Illuminate\Support\Facades\Cache;
use Nicelizhi\Shopify\Models\ShopifyOrder;
use Nicelizhi\Shopify\Models\ShopifyStore;
use Webkul\Sales\Models\Order;
use Illuminate\Http\Client\RequestException;
use GuzzleHttp\Exception\ClientException;
use Webkul\Customer\Repositories\CustomerRepository;
use Illuminate\Support\Facades\Event;

class PostTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:order:test:post {--order_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create Order shopify:order:post';

    private $shopify_store_id = null;
    private $lang = null;

    private $customerRepository = null;

    private $product = null;
    private $product_image = null;

    //protected ShopifyOrder $ShopifyOrder,
    //protected ShopifyStore $ShopifyStore,

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        
    )
    {
        $this->ShopifyOrder = new ShopifyOrder();
        $this->ShopifyStore = new ShopifyStore();
        $this->product = new \Webkul\Product\Models\Product();
        $this->customerRepository = app(CustomerRepository::class);
        $this->Order = new Order();
        $this->product_image = new \Webkul\Product\Models\ProductImage();


        $this->shopify_store_id = config('shopify.shopify_store_id');
        $this->lang = config('shopify.store_lang');
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $shopifyStore = Cache::get("shopify_store_".$this->shopify_store_id);

        if(empty($shopifyStore)){
            $shopifyStore = $this->ShopifyStore->where('shopify_store_id', $this->shopify_store_id)->first();
            Cache::put("shopify_store_".$this->shopify_store_id, $shopifyStore, 3600);
        }


        if(is_null($shopifyStore)) {
            $this->error("no store");
            return false;
        }

        $order_id = $this->option("order_id");

        if(!empty($order_id)) {
            //$lists = Order::where(['status'=>'processing'])->where("id", $order_id)->select(['id'])->limit(1)->get();
            $lists = Order::where("id", $order_id)->select(['id'])->limit(1)->get();
        }else{
            $lists = [];
            //$lists = Order::where(['status'=>'processing'])->orderBy("updated_at", "desc")->select(['id'])->limit(100)->get();
        }
        

        //$this->checkLog();

        foreach($lists as $key=>$list) {
            $this->info("start post order " . $list->id);
            $this->postOrder($list->id, $shopifyStore);
            $this->syncOrderPrice($list); // sync price to system
            //exit;
        }


        
    }

    /**
     * 
     * 
     * @param object orderitem
     * 
     */
    public function syncOrderPrice($orderItem) {
        if($orderItem->grand_total_invoiced=='0.0000') {
            
            $base_grand_total_invoiced = $orderItem->base_grand_total;
            $grand_total_invoiced = $orderItem->grand_total;
            Order::where(['id'=>$orderItem->id])->update(['grand_total_invoiced'=>$grand_total_invoiced, 'base_grand_total_invoiced'=>$base_grand_total_invoiced]);

        }
        
    }

    public function postOrder($id, $shopifyStore) {
        //return false;
        // check the shopify have sync

        // $shopifyOrder = $this->ShopifyOrder->where([
        //     'order_id' => $id
        // ])->first();
        // if(!is_null($shopifyOrder)) {
        //     return false;
        // }

        $this->info("sync to order to shopify ".$id);
        echo $id." start post \r\n";

        $client = new Client();

        $shopify = $shopifyStore->toArray();

        /**
         * 
         * @link https://shopify.dev/docs/api/admin-rest/2023-10/resources/order#post-orders
         * 
         */
        $id = 2133;
        $order = $this->Order->findOrFail($id);

        $orderPayment = $order->payment;  

        

        

        //var_dump($order);exit;

        $postOrder = [];

        $line_items = [];

        $products = $order->items;
        //var_dump($products);exit;
        $q_ty = 0;
        foreach($products as $key=>$product) {
            $sku = $product['additional'];

            var_dump($sku);
            
            $attributes = "";

            if(isset($sku['attributes'])) {
                foreach($sku['attributes'] as $sku_attribute) {
                    $attributes .= $sku_attribute['attribute_name'].":".$sku_attribute['option_label'].";";
                }
            }
            
            $variant_id = $sku['selected_configurable_option'];
            if(isset($sku['product_sku'])) {
                $skuInfo = explode('-', $sku['product_sku']);
                if(!isset($skuInfo[1])) {
                    $this->error("have error" . $id);
                    return false;
                }
                $variant_id = $skuInfo[1];
            }

            if(!is_numeric($variant_id)) {
                $variant_id = $sku['selected_configurable_option'];
            }
            

            $line_item = [];
            $line_item['variant_id'] = $variant_id;
            $line_item ['quantity'] = $product['qty_ordered'];
            $line_item ['price'] = $product['price'];
            $line_item ['product_id'] = $variant_id;
            $price_set = [];
            $price_set['shop_money'] = [
                'amount' => $product['price'],
                'currency_code' => $order->order_currency_code
            ];
            $line_item['price_set'] = $price_set;

            $line_item['title'] = $product['name'].$product['sku'].$attributes;
            $line_item['name'] = $product['name'].$product['sku'].$attributes;
            $variant_sku = $this->product->where('id', $variant_id)->value('sku');
            $line_item['sku'] = $variant_sku;
            if(empty($variant_sku)) {
                $line_item['sku'] = $sku['product_sku'];
            }

            $product_image = $this->product_image->where('product_id', $variant_id)->value('path');

            // add image to line item
            if(!empty($product_image)) {
                $line_item['properties'] = [
                    [
                        'name' => 'image',
                        'value' => $product_image
                    ]
                ];
            }



            $q_ty += $product['qty_ordered'];
            $line_item ['requires_shipping'] = true;

            array_push($line_items, $line_item);
        }

        $shipping_address = $order->shipping_address;
        $billing_address = $order->billing_address;
        $postOrder['line_items'] = $line_items;

        $shipping_address->email = "liulizhi@heomai.com";
        $shipping_address->first_name = "测试";
        $shipping_address->last_name = "测试";


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

        // create user
        $customer = $this->customerRepository->findOneByField('email', $shipping_address->email);
        if(is_null($customer)) {
            $customer = $this->customerRepository->findOneByField('phone', $shipping_address->phone);
            if(is_null($customer)) {

                $data = [];
                $data['email'] = $shipping_address->email;
                $data['customer_group_id'] = 2;
                $data['first_name'] = $shipping_address->first_name;
                $data['last_name'] = $shipping_address->last_name;
                $data['gender'] = $shipping_address->gender;
                $data['phone'] = $shipping_address->phone;

                //var_dump($data);
    
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

        //$postOrder['email'] = "";
        
        $transactions = [];

        $transactions = [
            [
                "kind" => "sales",
                "status" => "success",
                "amount" => $order->grand_total,
            ]
        ];

        // if($shipping_address->email=='test@example.com') {
        //     $postOrder['test'] = true;
        //     return false;
        // }
        

        
        $financial_status = "paid";

        if($orderPayment['method']=='codpayment') {
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



        // $total_shipping_price_set = [];
        // $shop_money = [];
        // $shop_money['amount'] = $order->shipping_amount;
        // $shop_money['currency_code'] = $order->order_currency_code;
        // $total_shipping_price_set['shop_money'] = $shop_money;
        // $total_shipping_price_set['presentment_money'] = $shop_money;

        if($order->shipping_amount=='14.9850') {
            $str = "aud order";
            //\Nicelizhi\Shopify\Helpers\Utils::send($str.'--' .$id. " 需要留意查看 ");
            //continue;
            //return false;
            $postOrder['send_receipt'] = true; 
        }else{
            $postOrder['send_receipt'] = true; 
        }

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

        $postOrder['buyer_accepts_marketing'] = true; // 

        $postOrder['name'] = config('shopify.order_pre').'#'.$id;
        $postOrder['order_number'] = $id;
        $postOrder['currency'] = $order->order_currency_code;
        $postOrder['presentment_currency'] = $order->order_currency_code;
        $pOrder['order'] = $postOrder;

        var_dump(json_encode($pOrder));
        //var_dump($pOrder); exit;

        //exit;

        $crm_url = config('onebuy.crm_url');

        // post the order to odoo erp
        if(config('OdooApi.enable')) {
            $odoo_url = config('OdooApi.host');

            $odoo_url = $odoo_url."/api/nexamerchant/order?api_key=".config('OdooApi.api_key');

            try{
                $response = $client->post($odoo_url, [
                    'http_errors' => true,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => json_encode($pOrder)
                ]);
            }catch(ClientException $e) {
                //var_dump($e);
                var_dump($e->getMessage());
                Log::error(json_encode($e->getMessage()));
                \Nicelizhi\Shopify\Helpers\Utils::send($e->getMessage().'--' .$id. " fix check it ");
                echo $e->getMessage()." post failed";
                //continue;
                return false;
            }
        }

        exit;



        try {
            $response = $client->post($shopify['shopify_app_host_name'].'/admin/api/2023-10/orders.json', [
                'http_errors' => true,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Shopify-Access-Token' => $shopify['shopify_admin_access_token'],
                ],
                'body' => json_encode($pOrder)
            ]);
        }catch(ClientException $e) {
            //var_dump($e);
            var_dump($e->getMessage());
            Log::error(json_encode($e->getMessage()));
            \Nicelizhi\Shopify\Helpers\Utils::send($e->getMessage().'--' .$id. " fix check it ");
            echo $e->getMessage()." post failed";
            //continue;
            return false;
        }

        

        $body = json_decode($response->getBody(), true);
        var_dump($body);

        echo $id." end post \r\n";
    }

    private function get_content($URL){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $URL);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    public function createCuster($data) {
        $password = rand(100000, 10000000);
        Event::dispatch('customer.registration.before');

        $data = array_merge($data, [
            'password'    => bcrypt($password),
            'is_verified' => 1,
            'subscribed_to_news_letter' => 1,
        ]);

        $this->customerRepository->create($data);
    }
}
