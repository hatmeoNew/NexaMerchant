<?php

namespace Webkul\CartRule\Models;

use Illuminate\Database\Eloquent\Model;

class CartRuleProduct extends Model
{
    protected $table = 'cart_rule_products';

    protected $fillable = ['cart_rule_id', 'product_id'];
}