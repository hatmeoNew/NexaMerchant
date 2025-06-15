<?php

namespace Nicelizhi\Shopify\Console\Commands\Fulfillments;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Webkul\Sales\Models\Order;
use Webkul\Product\Models\Product;
use Illuminate\Support\Facades\Log;
use Nicelizhi\Shopify\Helpers\Utils;
use Illuminate\Support\Facades\Artisan;
use Webkul\Sales\Repositories\ShipmentRepository;
use NexaMerchant\Feeds\Console\Commands\Klaviyo\SendKlaviyoEvent;

class CreateOdoo extends Command
{
    protected $signature = 'odoo:fulfillments:create {--order_id=}';

    protected $description = '获取订单面单数据，写入表中，并更新订单状态为已完成，发起邮件通知任务';

    const ODOO_URL = 'https://erp.heomai.com/jsonrpc';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $orderId = $this->option('order_id');
        $order = Order::findOrFail($orderId);

        $line_items = [];
        foreach ($order->items as $orderItem) {

            $line_item = [];

            $line_item['additional'] = $orderItem['additional'];
            $variant_id = $orderItem['additional']['selected_configurable_option'] ?: $orderItem['product_id'];
            $line_item['product_sku'] = Product::where('id', $variant_id)->value('custom_sku');
            $line_item['name'] = $orderItem['name'];
            $line_item['order_item_id'] = $orderItem['id'];
            $line_item['price'] = $orderItem['price'];

            array_push($line_items, $line_item);
        }

        // 根据接口获取面单数据
        $shipments = $this->getShipments($order);
        // dd($shipments);
        $createData = [];
        foreach ($shipments as $shipment) {
            $createData['order_id'] = $order->id;
            $createData['carrier_title'] = $shipment['delivery_type'];
            $createData['track_number'] = $shipment['track_number'];
            $createData['order_address_id'] = $order->shipping_address->id;
            $createData['inventory_source_id'] = 1;
            $createData['inventory_source_name'] = 'Default';
            $createData['source'] = 1;

            $shipment_items = [];
            foreach ($line_items as $line_item) {
                foreach ($shipment['product_list'] as $shipment_product) {
                    if ($shipment_product['external_sku'] == $line_item['product_sku']) {
                        $shipment_items[$line_item['order_item_id']] = [
                            'name' => $line_item['name'],
                            'sku' => $line_item['product_sku'],
                            'order_item_id' => $line_item['order_item_id'],
                            'qty' => $line_item['additional']['quantity'],
                            'price' => $line_item['price'],
                            'product_id' => $line_item['additional']['selected_configurable_option'],
                            'additional' => json_encode($line_item['additional']),
                            '1' => 1
                        ];
                        break;
                    }
                }
            }
            // dd($shipment_items);

            if (empty($shipment_items)) {
                Utils::sendFeishu('shipment_items is empty! order_id=' . $order->id . 'website:' . config('odoo_api.website_url'));
                return false;
            }

            $createData['items'] = $shipment_items;

            // dd($createData);
            $data['shipment'] = $createData;

            $shipment = app(ShipmentRepository::class);
            $ok = $shipment->create(array_merge($data, [
                'order_id' => $order->id,
            ]));

            Log::info('shipment->create:' . $ok);

            if ($ok) {
                // 发起邮件通知
                Log::info('SendKlaviyoEvent-200:' . $orderId);
                Artisan::queue((new SendKlaviyoEvent())->getName(), ['--order_id'=> $order->id, '--metric_type' => 200])->onConnection('rabbitmq')->onQueue(config('app.name') . ':klaviyo_event_place_order');
                return;
            }
        }
    }

    public function getShipments($order)
    {
        $name = config('odoo_api.order_pre') . '#' . $order->id;
        // $name = "KundiesDe#118";

        // 获取发货单数据
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $where = [
            ["name", "=", $name],
            ["delivery_status", "in", ["full", "partial"]]
        ];
        $fields = ["picking_ids", "delivery_status", "carrier_id"];
        $response = $client->get(self::ODOO_URL, [
            'headers' => $headers,
            'body' => $this->formatBody("sale.order", $where, $fields)
        ]);
        $data = json_decode($response->getBody(), true);
        // dd($data);
        $shipments = [];
        if ($data['result']) {
            foreach ($data['result'] as $k1 => $saleOrder) {

                // 获取快递商
                $where4 = [["id", "=", $saleOrder['carrier_id'][0]]];
                $fields4 = ['delivery_type'];
                $response4 = $client->get(self::ODOO_URL, [
                    'headers' => $headers,
                    'body' => $this->formatBody("delivery.carrier", $where4, $fields4)
                ]);
                $carrier_data = json_decode($response4->getBody(), true);
                $delivery_type = $carrier_data['result'][0]['delivery_type'];

                // 获取发货单
                $picking_ids = $saleOrder['picking_ids'];
                $where1 = [["id", "in", $picking_ids]];
                $fields1 = ['carrier_tracking_ref', 'delivery_type', 'move_ids'];
                $response1 = $client->get(self::ODOO_URL, [
                    'headers' => $headers,
                    'body' => $this->formatBody("stock.picking", $where1, $fields1)
                ]);
                $picking_data = json_decode($response1->getBody(), true);
                if ($picking_data) {

                    // 处理每个发货单
                    foreach ($picking_data['result'] as $picking) {
                        $delivery = [
                            "delivery_type" => $delivery_type,
                            "track_number" => $picking['carrier_tracking_ref'],
                        ];

                        $where2 = [["id", "in", $picking['move_ids']]];
                        $fields2 = ['product_id'];
                        $response2 = $client->get(self::ODOO_URL, [
                            'headers' => $headers,
                            'body' => $this->formatBody("stock.move", $where2, $fields2)
                        ]);
                        $move_data = json_decode($response2->getBody(), true);
                        // dd($move_data['result']);
                        $product_ids = array_map(function ($item) {
                            return $item['product_id'][0];
                        }, $move_data['result']);
                        // dd($product_ids);
                        $where3 = [["product_id", "in", $product_ids]];
                        $fields3 = ['external_sku'];
                        $response3 = $client->get(self::ODOO_URL, [
                            'headers' => $headers,
                            'body' => $this->formatBody("external.sku.mapping", $where3, $fields3)
                        ]);
                        $product_data = json_decode($response3->getBody(), true);
                        $delivery['product_list'] = $product_data['result'];

                        $shipments[$k1] = $delivery;
                    }
                }

                // dd($shipments);
            }
        }

        // dd($shipments);

        return $shipments;
    }

    public function formatBody($model, $where, $fields)
    {
        $jsonArray = [
            "jsonrpc" => "2.0",
            "method" => "call",
            "params" => [
                "service" => "object",
                "method" => "execute_kw",
                "args" => [
                    "odoo_16_v2", 9, "xiang20240204",
                    $model,
                    "search_read",
                    [
                        $where,
                        $fields
                    ]
                ]
            ],
        ];

        return json_encode($jsonArray);
    }
}
