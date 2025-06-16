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

    public static $metric_type_map_name = [
        'Place Order' => '订单确认',
        'Fulfilled Order' => '发货确认',
        'Cancelled Order' => '取消订单',
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
        self::METRIC_TYPE_100 => 'Placed Order',
        self::METRIC_TYPE_200 => 'Fulfilled Order',
        self::METRIC_TYPE_300 => 'Cancelled Order',
    ];

    public static $metric_type_map_name = [
        'Placed Order' => '订单确认',
        'Fulfilled Order' => '发货确认',
        'Cancelled Order' => '取消订单',
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

    // 定义访问器，自动格式化metric_name
    public function getMetricNameAttribute($value)
    {
        return self::$metric_type_map_name[$value] ?? $value;
    }

    // 关联订单
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
