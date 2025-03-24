<?php

namespace Webkul\CartRule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\CartRule\Contracts\CartRuleProduct as CartRuleProductContract;

class CartRuleProduct extends Model implements CartRuleProductContract
{
    protected $table = 'cart_rule_products';

    protected $fillable = ['cart_rule_id', 'product_id'];

    /**
     * Get the cart rule that owns the cart rule coupon.
     */
    public function cart_rule(): BelongsTo
    {
        return $this->belongsTo(CartRuleProxy::modelClass());
    }


}