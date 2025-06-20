<?php

namespace Nicelizhi\Manage\Models;

use Illuminate\Database\Eloquent\Model;


class AdminOperationLog extends Model
{

    protected $fillable = ['user_id', 'path', 'method', 'ip', 'input', 'created_at', 'updated_at'];

    public static $methodColors = [
        'GET'    => 'green',
        'POST'   => 'yellow',
        'PUT'    => 'blue',
        'DELETE' => 'red',
    ];

    public static $methods = [
        'GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH',
        'LINK', 'UNLINK', 'COPY', 'HEAD', 'PURGE',
    ];

}