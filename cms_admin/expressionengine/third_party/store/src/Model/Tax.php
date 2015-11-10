<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class Tax extends AbstractModel
{
    protected $table = 'store_taxes';
    protected $fillable = array('name', 'rate', 'rate_percent', 'country_code', 'state_code',
        'apply_to_shipping', 'included', 'enabled');
    protected $decimal_attributes = array('rate');

    public function categories()
    {
        return $this->belongsToMany('\Store\Model\Category', 'store_taxes_categories');
    }

    public function getCategoryIdsAttribute()
    {
        $ids = array();
        foreach ($this->categories as $category) {
            $ids[] = $category->cat_id;
        }

        return $ids;
    }

    public function getRatePercentAttribute()
    {
        return ($this->rate * 100).'%';
    }

    public function setRatePercentAttribute($value)
    {
        $value = (float) $value;
        $this->rate = $value / 100;
    }

    public function getCountryNameAttribute()
    {
        return ee()->store->shipping->get_country_name($this->country_code) ?: lang('store.any');
    }

    public function getStateNameAttribute()
    {
        return ee()->store->shipping->get_state_name($this->country_code, $this->state_code)
            ?: lang('store.any');
    }
}
