<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class Config extends AbstractModel
{
    protected $table = 'store_config';

    public function getValueAttribute($value)
    {
        return json_decode($value, true);
    }

    /**
     * Settings are stored in database as JSON
     */
    public function setValueAttribute($value)
    {
        $this->attributes['value'] = json_encode($value);
    }
}
