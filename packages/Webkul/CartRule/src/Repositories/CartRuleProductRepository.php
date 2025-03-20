<?php
namespace Webkul\CartRule\Repositories;

use Webkul\Core\Eloquent\Repository;

class CartRuleProductRepository extends Repository{

    /**
     * Specify Model class name
     *
     * @return string
     */
    function model(): string
    {
        return 'Webkul\CartRule\Contracts\CartRuleProduct';
    }

    
}