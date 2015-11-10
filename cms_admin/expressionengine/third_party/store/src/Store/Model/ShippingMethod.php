<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class ShippingMethod extends AbstractModel
{
    protected $table = 'store_shipping_methods';
    protected $fillable = array('name', 'enabled');

    public function rules()
    {
        return $this->hasMany('\Store\Model\ShippingRule');
    }
}
