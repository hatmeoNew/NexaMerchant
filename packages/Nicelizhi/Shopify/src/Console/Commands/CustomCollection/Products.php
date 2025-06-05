<?php

namespace Nicelizhi\Shopify\Console\Commands\CustomCollection;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderCommentRepository;
use Webkul\Sales\Repositories\ShipmentRepository;
use Webkul\Sales\Repositories\OrderItemRepository;
use Illuminate\Support\Facades\Cache;


use Nicelizhi\Shopify\Models\ShopifyOrder;
use Nicelizhi\Shopify\Models\ShopifyStore;
use GuzzleHttp\Exception\ClientException;

class Products extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:CustomCollection:products:get {--shopify_store_id=} {--force=} {--ids=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get CustomCollection Products List shopify:CustomCollection:products:get';

    private $lang = null;
    private $shopify_store_id = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected ShopifyOrder $ShopifyOrder,
        protected ShopifyStore $ShopifyStore,
        protected ShipmentRepository $shipmentRepository,
        protected OrderItemRepository $orderItemRepository,
        protected OrderCommentRepository $orderCommentRepository
    )
    {
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
        $shopify = $shopifyStore->toArray();

        $client = new Client();

        $force = $this->option('force');

        $items = \Nicelizhi\Shopify\Models\ShopifyCustomCollection::select(['collection_id'])->get();

        foreach($items as $key=>$item) {


            $this->getCollectionProduct($item, $shopify, $client);
            

        }


        
        $i = 1;

       
    }

    protected function getCollectionProduct($collection, $shopify, $client) {
        $this->info("getCollectionProduct");

        // @https://shopify.dev/docs/api/admin-rest/2023-10/resources/customer#get-customers?ids=207119551,1073339482
        $base_url = $shopify['shopify_app_host_name'].'/admin/api/2023-10/collections/'.$collection->collection_id.'/products.json?limit=250';
        //$base_url = $shopify['shopify_app_host_name'].'/admin/api/2023-10/customers.json?limit=2&ids=7927054762214'; // for test
        $this->error("base url ". $base_url);
        $response = $client->get($base_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Shopify-Access-Token' => $shopify['shopify_admin_access_token'],
            ]
        ]);

        $body = $response->getBody();
        $body = json_decode($body, true);

        foreach($body['products'] as $key=>$product) {

            $userCollection = \Nicelizhi\Shopify\Models\ShopifyCollectionProduct::where("collection_id", $collection->collection_id)->where("shopify_store_id", $this->shopify_store_id)->where("product_id", $product['id'])->first();
            if(is_null($userCollection)) $userCollection = new \Nicelizhi\Shopify\Models\ShopifyCollectionProduct;
            $userCollection->shopify_store_id = $this->shopify_store_id;
            $userCollection->collection_id = $collection->collection_id;
            $userCollection->product_id = $product['id'];
            $userCollection->save();

        }

    }
}