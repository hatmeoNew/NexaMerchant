<?php

namespace Nicelizhi\Shopify\Console\Commands\Fulfillments;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Webkul\Sales\Models\Order;
use Webkul\Product\Models\Product;
use Webkul\Sales\Repositories\ShipmentRepository;

class CreateOdoo extends Command
{
    protected $signature = 'odoo:fulfillments:create {--order_id=}';

    protected $description = '获取订单面单数据，写入表中，并更新订单状态为已完成';

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

        // dd($line_items);

        // dd($order->items->toArray());
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
                    if ($shipment_product['default_code'] == $line_item['product_sku'] || $shipment_product['name'] == $line_item['product_sku']) {
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

            $createData['items'] = $shipment_items;

            // dd($createData);
            $data['shipment'] = $createData;

            $shipment = app(ShipmentRepository::class);
            $shipment->create(array_merge($data, [
                'order_id' => $order->id,
            ]));
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
        $fields = ["picking_ids"];
        $response = $client->get(self::ODOO_URL, [
            'headers' => $headers,
            'body' => $this->formatBody("sale.order", $where, $fields)
        ]);
        $data = json_decode($response->getBody(), true);
        // dd($data);
        $shipments = [];
        if ($data['result']) {
            foreach ($data['result'] as $k1 => $saleOrder) {
                $picking_ids = $saleOrder['picking_ids'];
                // dd($picking_ids);
                $where1 = [["id", "in", $picking_ids]];
                $fields1 = ['carrier_tracking_ref', 'delivery_type', 'move_ids'];
                $response1 = $client->get(self::ODOO_URL, [
                    'headers' => $headers,
                    'body' => $this->formatBody("stock.picking", $where1, $fields1)
                ]);

                $picking_data = json_decode($response1->getBody(), true);
                if ($picking_data) {
                    // dd($picking_data['result']);
                    // 处理每个发货单
                    foreach ($picking_data['result'] as $picking) {
                        $delivery = [
                            "delivery_type" => $picking['delivery_type'],
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
                        $where3 = [["id", "in", $product_ids]];
                        $fields3 = ['default_code', 'name'];
                        $response3 = $client->get(self::ODOO_URL, [
                            'headers' => $headers,
                            'body' => $this->formatBody("product.product", $where3, $fields3)
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
