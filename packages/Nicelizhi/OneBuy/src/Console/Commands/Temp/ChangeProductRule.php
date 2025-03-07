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

        $this->checkRules();
        return;
        
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

        //var_dump($family_ids);
        sort($family_ids);
        var_dump($rules_familys);
    }

    public function validateRules($family_id)
    {

        $rules = $this->getRules();

        exit;

        var_dump($rules);exit;

        $rules = \Webkul\CartRule\Models\CartRule
                    ::where('conditions', 'like', '%"value": '.$family_id.',%')
                    ->get();
        
        $count = count($rules);
        if($count != 4) {
            
            
            $this->error('Family ID: '.$family_id.' - '.$count);
            
            foreach ($rules as $rule) {
                
            }


        }

        return $rules;
    }

    // check the rules have the same faimly id
    public function checkRules()
    {
        $rules = \Webkul\CartRule\Models\CartRule::all();
        $rules = $rules->map(function($rule){
            $conditions = $rule->conditions;
            foreach ($conditions as $condition) {
                if ($condition['attribute'] == 'product|attribute_family_id') {
                    $family_id = $condition['value'];
                    //$this->info('Rule ID: '.$rule->id.' - Family ID: '.$family_id);

                    $products = \Webkul\Product\Models\Product::where('attribute_family_id', $family_id)->get();
                    if(count($products) == 0) {
                        $this->error('Rule ID: '.$rule->id.' - Family ID: '.$family_id);
                    }

                    // check the family id in rules
                    $rules = \Webkul\CartRule\Models\CartRule::where('conditions', 'like', '%"value": '.$family_id.',%')
                        ->get();
                    if(count($rules) != 4) {
                        $this->error('Rule ID: '.$rule->id.' - Family ID: '.$family_id.' - '.count($rules));
                    }
                }
            }
        });
    }

    // get all rules from redis
    public function getRules()
    {
        $rules = Redis::keys('product-quantity-price-*');
        $rules = array_map(function($rule){

            $product_id = explode('-', $rule);
            $product_id = end($product_id);
            $product = \Webkul\Product\Models\Product::where('id', $product_id)->first();
            if(!$product) {
                $this->error('Product ID: '.$product_id);
                exit;
                return;
            }
            $family_id = $product->attribute_family_id;
            $this->info('Family ID: '.$family_id);
            $this->info('Product ID: '.$product_id);
            $this->info('Rule: '.$rule);
            
            $data = Redis::zRange($rule, 0, -1, 'WITHSCORES');
            foreach ($data as $key => $value) {
                $this->error('Rule ID: '.$key);
                $ruleDb = \Webkul\CartRule\Models\CartRule
                    ::where('id', $key)
                    ->first();
                $conditions = $ruleDb->conditions;
                foreach ($conditions as $condition) {

                    if ($condition['attribute'] == 'product|attribute_family_id') {
                        if($condition['value'] != $family_id) {
                            $this->error('Rule ID: '.$key.' - '.$condition['value']);
                            exit;
                        }
                    }
                }

            }

            
            

        }, $rules);

        return $rules;
    }
}