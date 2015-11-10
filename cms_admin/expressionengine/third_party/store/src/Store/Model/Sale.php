<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class Sale extends AbstractModel
{
    protected $table = 'store_sales';
    protected $fillable = array('name', 'start_date_str', 'end_date_str', 'member_group_ids',
        'entry_ids', 'category_ids', 'per_item_discount', 'percent_discount', 'notes', 'enabled');

    public function getStartDateStrAttribute()
    {
        return $this->getUnixTimeAttribute('start_date');
    }

    public function setStartDateStrAttribute($value)
    {
        return $this->setUnixTimeAttribute('start_date', $value);
    }

    public function getEndDateStrAttribute()
    {
        return $this->getUnixTimeAttribute('end_date');
    }

    public function setEndDateStrAttribute($value)
    {
        return $this->setUnixTimeAttribute('end_date', $value);
    }

    public function getMemberGroupIdsAttribute()
    {
        return $this->getPipeArrayAttribute('member_group_ids');
    }

    public function setMemberGroupIdsAttribute($value)
    {
        return $this->setPipeArrayAttribute('member_group_ids', $value);
    }

    public function getEntryIdsAttribute()
    {
        return $this->getPipeArrayAttribute('entry_ids');
    }

    public function setEntryIdsAttribute($value)
    {
        return $this->setPipeArrayAttribute('entry_ids', $value);
    }

    public function getCategoryIdsAttribute()
    {
        return $this->getPipeArrayAttribute('category_ids');
    }

    public function setCategoryIdsAttribute($value)
    {
        return $this->setPipeArrayAttribute('category_ids', $value);
    }

    public function setPerItemDiscountAttribute($value)
    {
        $this->attributes['per_item_discount'] = (float) $value ?: null;
    }

    public function setPercentDiscountAttribute($value)
    {
        $this->attributes['percent_discount'] = (float) $value ?: null;
    }
}
