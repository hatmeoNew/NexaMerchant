<?php

namespace Nicelizhi\Shopify\Console\Commands\Order;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class ProductSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product_sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create product to odoo';


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
        $this->info("start post product");

        $this->postProduct();
    }

    public function postProduct()
    {
        $client =  new Client();

        // 读取csv文件
        $file = fopen(public_path('product_list_v3.csv'), 'r');
        while (($line = fgetcsv($file)) !== false) {
            // 第一行 跳过
            if ($line[0] == 'default_code') {
                continue;
            }

            list($default_code, $name, $product_sku, $img, $price, $product_url, $declared_price, $declared_name_cn, $declared_name_en, $isValida) = $line;
            if ($isValida != 'TRUE') {
                dump('跳过');
                continue;
            }

            try {
                list($product_name, $colorName, $sizeName) = explode('-', $product_sku);
            } catch (\Throwable $th) {
                continue;
            }

            $attribute_name_size = 'Size';
            $option_label_size = $sizeName;

            $attribute_name_color = 'Color';
            $option_label_color = $colorName;

            $attributes = [
                [
                    'attribute_name' => $attribute_name_size,
                    'option_label'   => $option_label_size,
                ],
                [
                    'attribute_name' => $attribute_name_color,
                    'option_label'   => $option_label_color,
                ],
            ];

            $type = 'product';

            $product = [
                'default_code'     => trim($product_name),
                'name'             => trim($product_name),
                'product_sku'      => $product_sku,
                'img'              => $img,
                'price'            => $price,
                'attributes'       => $attributes,
                'type'             => $type,
                'product_url'      => $product_url,
                'declared_price'   => $declared_price,
                'declared_name_cn' => $declared_name_cn,
                'declared_name_en' => $declared_name_en,
            ];

            // dd($product);

            $products[] = $product;

            $odoo_url = config('odoo_api.host') . "/api/nexamerchant/sync_products";
            try {
                $response = $client->post($odoo_url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . config('odoo_api.api_token'),
                    ],
                    'body' => json_encode($product)
                ]);
                echo "Response Body: " . $response->getBody() . PHP_EOL;

            } catch (\Throwable $th) {
                \Nicelizhi\Shopify\Helpers\Utils::sendFeishu($th->getMessage());
            }
        }

        fclose($file);

        // dd($products);

    }
}
