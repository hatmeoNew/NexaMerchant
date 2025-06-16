<?php

namespace Nicelizhi\Shopify\Models;

use Webkul\Sales\Models\Order;
use Illuminate\Database\Eloquent\Model;

class EmailSendRecords extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;

    const METRIC_TYPE_100 = 100;
    const METRIC_TYPE_200 = 200;
    const METRIC_TYPE_300 = 300;

    public static $metric_type_map = [
        self::METRIC_TYPE_100 => 'Place Order',
        self::METRIC_TYPE_200 => 'Fulfilled Order',
        self::METRIC_TYPE_300 => 'Cancelled Order',
    ];

    protected $fillable = [
        'order_id',
        'email',
        'metric_name',
        'sender',
        'send_status',
        'failure_reason',
        'created_at',
        'updated_at',
    ];

    // 关联订单
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
