<?php

namespace Nicelizhi\Shopify\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSendRecords extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;

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
}
