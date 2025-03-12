<?php

namespace Webkul\Core\Models;

use Webkul\Core\Eloquent\TranslatableModel;
use Illuminate\Database\Eloquent\Model;
use Webkul\Core\Contracts\Country as CountryContract;

class Country extends Model implements CountryContract
{
    public $timestamps = false;

    //public $translatedAttributes = ['name'];

    //protected $with = ['translations'];

    /**
     * Get the States.
     */
    public function states()
    {
        return $this->hasMany(CountryStateProxy::modelClass());
    }
}