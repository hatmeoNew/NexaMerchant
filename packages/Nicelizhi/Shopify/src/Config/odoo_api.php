<?php
return [
    'host' => env('ODOO_HOST'),
    'api_token' => env('ODOO_API_TOKEN'),
    'order_prefix' => env('ODOO_ORDER_PRE'),
    'website_name' => env('WEBSITE_NAME'),
    'odoo_app_host_name' => env('SHOPIFY_APP_HOST_NAME'),
    'wcom_noticle_url' => env('WCOME_NOTICLE_URL'),
    'feishu_noticle_url' => env('FEISU_NOTICLE_URL'),
    'order_pre' => env('SHOPIFY_ORDER_PRE'),
    'enable' => env('SHOPIFY_ENABLE', true)
];