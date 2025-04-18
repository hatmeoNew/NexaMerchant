<?php

namespace Nicelizhi\Shopify\Models;

use Illuminate\Database\Eloquent\Model;

class OdooProducts extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'product_id',
        'default_code',
        'type',
        'list_price',
        'currency_id',
        'uom_id',
        'categ_id',
        'created_at',
        'updated_at',
    ];
}
