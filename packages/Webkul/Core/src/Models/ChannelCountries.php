<?php
namespace Webkul\Core\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelCountries extends Model
{
    protected $table = 'channel_countries';

    protected $fillable = [
        'channel_id',
        'country_id',
    ];

    public $timestamps = false;
}