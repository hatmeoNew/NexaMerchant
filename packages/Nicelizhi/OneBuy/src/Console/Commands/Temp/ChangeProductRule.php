<?php
namespace Nicelizhi\OneBuy\Console\Commands\Temp;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ChangeProductRule extends Command
{
    protected $signature = 'onebuy:change-product-rule';
    protected $description = 'Change product rule';

    public function handle()
    {
        $this->info('Change product rule');

        $this->editRulesV2();
        return false;

        $products = \Webkul\Product\Models\Product::where('type', 'configurable')->get();

        $family_ids = [];
        $rules_familys = [];

        foreach ($products as $product) {
            $this->info('Attr Family ID: '.$product->attribute_family_id);
            $this->validateRules($product->attribute_family_id);
            continue;
            
            $rulesId = [];
            // get rules id from redis
            $product_id = $product->id;
            $slug = $product->url_key;
            $rules = Redis::smembers('product-quantity-rules-'.$product_id);

            if(!isset($family_ids[$product->attribute_family_id])) {
                $family_ids[$product->attribute_family_id] = [];
            }else{
                //var_dump(isset($family_ids[$product->attribute_family_id]));
                $this->error('Family ID: '.$product->attribute_family_id);
                return false;
            }

            if(count($rules)!=4) {
                $this->error($slug.' - '.$product_id.' - '.count($rules));
                return;
            }

            $this->info('Product Slug: '.$slug);
            $this->info('Product ID: '.$product_id);
            

            //exit;

            // a family only have 4 rules and the rules are unique

            foreach ($rules as $rule) {

                $family_ids[$product->attribute_family_id][] = $rule;
                $rules_familys[$rule] = $product->attribute_family_id;

                $this->info('Rule ID: '.$rule);
                $ruleDb = \Webkul\CartRule\Models\CartRule
                    ::where('id', $rule)
                    ->first();
                $conditions = $ruleDb->conditions;
                //var_dump($conditions);
                $conditionsNew = [];
                foreach ($conditions as $condition) {
                    //var_dump($condition);
                    if ($condition['attribute'] == 'cart_item|quantity') {
                        $condition['attribute'] = "cart|items_qty";
                    }

                    if ($condition['value'] != $product->attribute_family_id && $condition['attribute'] == 'product|attribute_family_id') {
                        var_dump($condition['value']);
                        $condition['value'] = $product->attribute_family_id;
                    }


                    $conditionsNew[] = $condition;
                }
                $ruleDb->conditions = $conditionsNew;

                var_dump($ruleDb->conditions);
                //exit;
                var_dump($family_ids[$product->attribute_family_id]);

                $ruleDb->save();
                $this->info('Rule ID: '.$rule);
                //exit;

                //var_dump($ruleDb->conditions);
                //exit;

            }

            //exit;

        }

    }

    /**
     * 
     * 
     * Edit the product and create new rules for the product
     * 
     * 
     */
    public function editRulesV2() {
        // get all products rules price
        $keys = Redis::keys('product-quantity-price-*');

        $redis_temp_price_cache_key = 'temp_price_cache_key';
        
        foreach ($keys as $key) {
            $product_id = str_replace("product-quantity-price-", "", $key);
            
            $prices = Redis::zRange('product-quantity-price-'.$product_id, 0, -1, 'WITHSCORES');
            
            // add the price to the temp cache key
            Redis::zadd($redis_temp_price_cache_key, $product_id, json_encode($prices));

        }

        // 
        $rules = Redis::zRange($redis_temp_price_cache_key, 0, -1, 'WITHSCORES');
        //exit;
        $client = new \GuzzleHttp\Client();

        $token = "";

        foreach($rules as $key=> $rule) {

            // if the product has been processed
            if(Redis::get('product-quantity-prices-'.$rule.'-done')) {
                continue;
            }

            // delete the redis key
            Redis::del('product-quantity-price-'.$rule);
            Redis::del('product-quantity-rules-'.$rule);
            
            $url =config('app.url')."/api/v1/admin/promotions/cart-rules/".$rule."/product-quantity-rules";
            $this->info($url);
            $ruleInfo = json_decode($key, true);
            //exit;

            $product = \Webkul\Product\Models\Product::where('id', $rule)->first();
            $this->info('Product ID: '.$rule);
            
            /**
             * 
             {"product_id":1207,
             "rules":[
             {"id":109,"price":1299,"action_type":"by_fixed","attributes":[{"value":"1","operator":"==","attribute":"cart|items_qty","attribute_type":"integer"},{"value":875,"operator":"==","attribute":"product|attribute_family_id","attribute_type":"integer"}]},{"id":110,"price":2078.4,"action_type":"by_fixed","attributes":[{"value":"2","operator":"==","attribute":"cart|items_qty","attribute_type":"integer"},{"value":875,"operator":"==","attribute":"product|attribute_family_id","attribute_type":"integer"}]},{"id":111,"price":2727.9,"action_type":"by_fixed","attributes":[{"value":"3","operator":"==","attribute":"cart|items_qty","attribute_type":"integer"},{"value":875,"operator":"==","attribute":"product|attribute_family_id","attribute_type":"integer"}]},{"id":112,"price":3117.6,"action_type":"by_fixed","attributes":[{"value":"4","operator":"==","attribute":"cart|items_qty","attribute_type":"integer"},{"value":875,"operator":"==","attribute":"product|attribute_family_id","attribute_type":"integer"}]}]}
             * 
             */
            $postRules = [];
            $i = 1;
            foreach($ruleInfo as $price) {
                
                $attributes = [
                    [
                        "value"=> $i,
                        "operator"=> "==",
                        "attribute"=> "cart|items_qty",
                        "attribute_type"=> "integer"
                    ],
                    [
                        "value"=> $product->attribute_family_id,
                        "operator"=> "==",
                        "attribute"=> "product|attribute_family_id",
                        "attribute_type"=> "integer"
                    ]
                ];
                $postRules[] = [
                    "id"=> 0,
                    "price"=> round($price, 2),
                    "action_type"=> 'by_fixed',
                    "attributes"=> $attributes
                ];
                $i++;
            }
            $postData = [
                "product_id"=> $rule,
                "rules"=> $postRules
            ];

            // var_dump($postData);
            // exit;

            $response = $client->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$token
                ],
                'json' => $postData
            ]);

            $this->info($response->getBody());

            // add a mark for the product
            Redis::set('product-quantity-prices-'.$rule.'-done', 1);



            sleep(2);

            

        }

    }

    
}