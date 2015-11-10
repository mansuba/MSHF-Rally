<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class OrderAdjustment extends AbstractModel
{
    protected $table = 'store_order_adjustments';

    public function order()
    {
        return $this->belongsTo('\Store\Model\Order');
    }

    public function getPercentAttribute()
    {
        return $this->rate * 100;
    }

    public function toTagArray()
    {
        $attributes['adjustment:id'] = $this->id;
        $attributes['adjustment:name'] = $this->name;
        $attributes['adjustment:type'] = $this->type;
        $attributes['adjustment:rate'] = (float) $this->rate;
        $attributes['adjustment:percent'] = (float) $this->percent;
        $attributes['adjustment:amount'] = store_currency($this->amount);
        $attributes['adjustment:amount_val'] = (float) $this->amount;
        $attributes['adjustment:taxable'] = (bool) $this->taxable;
        $attributes['adjustment:included'] = (bool) $this->included;

        return $attributes;
    }
}
