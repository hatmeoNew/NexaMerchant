<?php
namespace Nicelizhi\OneBuy\Console\Commands\Temp;

use Illuminate\Console\Command;

class ChangeProductSku extends Command
{
    protected $signature= 'onebuy:change-product-sku-id';
    protected $description = 'Change product sku id';

    public function handle()
    {
        $this->info('Change product sku id');
        
        
        $products = \Webkul\Product\Models\Product::where('type', 'configurable')->get();

        // product variant
        foreach ($products as $product) {
            $this->info('product->id: '.$product->id);
            $product_id = $product->id;
            $product_sku = $product->sku;
            $product_variants = \Webkul\Product\Models\Product::where('parent_id', $product_id)->get();

            $super_attributes = $product->super_attributes;

            $super_attribute_ids = $super_attributes->pluck('id')->toArray();
            var_dump($super_attribute_ids);
            //var_dump($super_attribute_ids->toArray());exit;

            foreach ($product_variants as $product_variant) {

                //var_dump($product_variant->id);
                $this->error('product_variant->id: '.$product_variant->id);
                //var_dump($product_variant->sku);
                //var_dump($product_variant->name);

                $labels = explode('/', $product_variant->name);

                //var_dump($labels);

                //product_attribute_values
                foreach($super_attribute_ids as $super_attribute_id) {
                    $this->info('super_attribute_id: '.$super_attribute_id);
                    $product_attribute_values = \Webkul\Product\Models\ProductAttributeValue::where('product_id', $product_variant->id)->where('attribute_id', $super_attribute_id)->first();
                    if(is_null($product_attribute_values)) {
                        $this->error('product_attribute_values is null '. $product_variant->id);

                        // insert the integer_value to product_attribute_values
                        // $product_attribute_values = new \Webkul\Product\Models\ProductAttributeValue();
                        // $product_attribute_values->product_id = $product_variant->id;
                        // $product_attribute_values->attribute_id = $super_attribute_id;
                        // $product_attribute_values->integer_value = 0;
                        // $product_attribute_values->save();

                        continue;
                    }
                    var_dump($product_attribute_values->integer_value);

                    // attribute_options
                    $this->info('attribute_options');
                    $this->info('attribute_id: '.$super_attribute_id);
                    $this->info('id : '.$product_attribute_values->integer_value);
                    $attribute_options = \Webkul\Attribute\Models\AttributeOption::where('attribute_id', $super_attribute_id)->where('id', $product_attribute_values->integer_value)->first();
                    if(is_null($attribute_options)) {
                        var_dump($labels);

                        //attribute_options
                        foreach($labels as $label) {
                            $this->warn('label: '.$label);
                            $this->warn('super_attribute_id : '.$super_attribute_id);
                            $attribute_options = \Webkul\Attribute\Models\AttributeOption::where('attribute_id', $super_attribute_id)->where('admin_name', $label)->first();
                            if(is_null($attribute_options)) {
                                $this->error('attribute_options is null '. $product_attribute_values->integer_value);
                                continue;
                                //exit;
                                //continue;
                            }

                            //var_dump($attribute_options->toArray());
                            $this->info('attribute_options is not equal '. $product_attribute_values->integer_value. ' - '.$attribute_options->id);
                            if($product_attribute_values->integer_value != $attribute_options->id) {
                                $this->error('attribute_options is not equal '. $product_attribute_values->integer_value. ' - '.$attribute_options->id);
                                // update the integer_value to attribute_options
                                $attribute_options->id = $product_attribute_values->integer_value;
                                $attribute_options->save();
                                var_dump($attribute_options->toArray());
                                //exit;
                            }


                            //$this->error('attribute_options is null '. $product_attribute_values->integer_value);
                            //exit;



                        }
                        
                        //continue;
                    }
                    //var_dump($attribute_options->admin_name);

                }
                //exit;
                //$product_attribute_values = \Webkul\Product\Models\ProductAttributeValue::where('product_id', $product_variant->id)->select(['attribute_id','integer_value'])->get();


            }
            //exit;

            $this->error("------------------------------------------------------------------");
            

        }


    }


}
