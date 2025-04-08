<?php

namespace Nicelizhi\Shopify\Models;

use Illuminate\Database\Eloquent\Model;

class OdooOrder extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'partner_id',
        'partner_invoice_id',
        'partner_shipping_id',
        'pricelist_id',
        'currency_id',
        'payment_term_id',
        'team_id',
        'user_id',
        'company_id',
        'warehouse_id',
        'client_order_ref',
        'date_order',
        'state',
        'invoice_status',
        'picking_policy',
        'amount_tax',
        'amount_total',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'partner_id' => 'json',
        'partner_invoice_id' => 'json',
        'partner_shipping_id' => 'json',
        'pricelist_id' => 'json',
        'currency_id' => 'json',
        'payment_term_id' => 'json',
        'team_id' => 'json',
        // 'user_id' => 'json',
        'company_id' => 'json',
        'warehouse_id' => 'json',
    ];
}
