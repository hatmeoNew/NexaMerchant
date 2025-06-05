<?php

namespace Webkul\Core\Models;

use Webkul\Core\Eloquent\TranslatableModel;
use Webkul\Core\Contracts\CountryState as CountryStateContract;

class CountryState extends TranslatableModel implements CountryStateContract
{
    public $timestamps = false;

    public $translatedAttributes = ['default_name'];

    protected $with = ['translations'];

    protected $fillable = ['country_id', 'country_code', 'code', 'default_name'];

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        $array['default_name'] = $this->default_name;

        return $array;
    }
}