<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class Discount extends Sale
{
    protected $table = 'store_discounts';
    protected $fillable = array('name', 'code', 'start_date_str', 'end_date_str',
        'member_group_ids', 'entry_ids', 'category_ids', 'exclude_on_sale', 'type',
        'purchase_qty', 'purchase_total', 'step_qty', 'discount_qty', 'base_discount',
        'per_item_discount', 'percent_discount', 'free_shipping', 'per_user_limit',
        'total_use_limit', 'break', 'notes', 'enabled');

    public function setPurchaseQtyAttribute($value)
    {
        $this->attributes['purchase_qty'] = (int) $value ?: null;
    }

    public function setPurchaseTotalAttribute($value)
    {
        $this->attributes['purchase_total'] = (float) $value ?: null;
    }

    public function setStepQtyAttribute($value)
    {
        $this->attributes['step_qty'] = (int) $value ?: null;
    }

    public function setDiscountQtyAttribute($value)
    {
        $this->attributes['discount_qty'] = (int) $value ?: null;
    }

    public function setBaseDiscountAttribute($value)
    {
        $this->attributes['base_discount'] = (float) $value ?: null;
    }

    public function setPerUserLimitAttribute($value)
    {
        $this->attributes['per_user_limit'] = (int) $value ?: null;
    }

    public function setTotalUseLimitAttribute($value)
    {
        $this->attributes['total_use_limit'] = (int) $value ?: null;
    }
}
