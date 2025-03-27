<?php
namespace Nicelizhi\OneBuy\Console\Commands\Temp;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ChangeProductRuleSave extends Command
{
    protected $signature = 'onebuy:change-product-rule-save';
    protected $description = 'Change product rule re save';

    public function handle()
    {
        $rules = \Webkul\CartRule\Models\CartRule::all();
        foreach ($rules as $rule) {
            $this->info($rule->id);
            $conditions = $rule->conditions;
            if(empty($conditions)) {
                continue;
            }
            foreach ($conditions as $key => $condition) {
                if($condition['attribute'] == 'product|attribute_family_id') {
                    $product = \Webkul\Product\Models\Product::where("attribute_family_id",$condition['value'])->where('type','configurable')->first();
                    if($product) {
                        // check the rule is already applied to the product or not 

                        $this->error($product->id);

                        $productRule = \Webkul\CartRule\Models\CartRuleProduct::where('cart_rule_id',$rule->id)->where('product_id',$product->id)->first();
                        if(!$productRule) {
                            $productRule = new \Webkul\CartRule\Models\CartRuleProduct();
                            $productRule->cart_rule_id = $rule->id;
                            $productRule->product_id = $product->id;
                            $productRule->save();
                        }
                    }
                    
                }
            }
        }
    }
}