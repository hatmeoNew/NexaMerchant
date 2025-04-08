<?php

namespace Nicelizhi\Shopify\Models;

use Illuminate\Database\Eloquent\Model;

class OdooCustomer extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'mobile',
        'street',
        'street2',
        'zip',
        'city',
        'state_id',
        'country_id',
        'vat',
        'function',
        'title',
        'company_id',
        'category_id',
        'user_id',
        'team_id',
        'lang',
        'tz',
        'active',
        'company_type',
        'is_company',
        'color',
        'partner_share',
        'commercial_partner_id',
        'type',
        'signup_token',
        'signup_type',
        'signup_expiration',
        'signup_url',
        'partner_gid',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'state_id' => 'json',
        'country_id' => 'json',
        'company_id' => 'json',
        'category_id' => 'json',
        'commercial_partner_id' => 'json',
    ];
}
