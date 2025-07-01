<?php

namespace Webkul\Sales\Repositories;

use Webkul\Core\Eloquent\Repository;

class OrderUtmRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    function model(): string
    {
        return 'NexaMerchant\Apis\Models\OrderUtm';
    }
}