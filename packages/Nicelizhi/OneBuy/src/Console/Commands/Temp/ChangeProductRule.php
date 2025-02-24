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
        
        $products = \Webkul\Product\Models\Product::where('type', 'configurable')->where("id", 1805)->get();
        foreach ($products as $product) {
            
            $rulesId = [];
            // get rules id from redis
            $product_id = $product->id;
            $slug = $product->url_key;
            $rules = Redis::smembers('product-quantity-rules-'.$product_id);

            $this->info('Product Slug: '.$slug);
            $this->info('Attr Family ID: '.$product->attribute_family_id);

            //exit;

            foreach ($rules as $rule) {
                
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

                //var_dump($ruleDb->conditions);
                //exit;

                $ruleDb->save();
                $this->info('Rule ID: '.$rule);
                //exit;

                //var_dump($ruleDb->conditions);
                //exit;

            }

            //exit;

        }

        
    }
}