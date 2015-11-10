<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

/**
 * Order shipping methods represent a shipping method the customer may choose for their order
 */
class OrderShippingMethod
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var float
     */
    public $amount;

    /**
     * @var string
     */
    public $days;

    /**
     * @var string
     */
    public $class;

    public function toTagArray()
    {
        $attributes = array();
        $attributes['shipping_method:id'] = $this->id;
        $attributes['shipping_method:name'] = $this->name;
        $attributes['shipping_method:amount'] = store_currency($this->amount);
        $attributes['shipping_method:amount_val'] = (float) $this->amount;
        $attributes['shipping_method:class'] = $this->class;
        $attributes['shipping_method:days'] = $this->days;

        // legacy attributes
        $attributes['method_id'] = $this->id;
        $attributes['method_title'] = $this->name;
        $attributes['method_price'] = $attributes['shipping_method:amount'];
        $attributes['method_price_val'] = $attributes['shipping_method:amount_val'];

        return $attributes;
    }
}
