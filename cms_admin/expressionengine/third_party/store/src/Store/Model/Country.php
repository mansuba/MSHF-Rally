<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class Country extends AbstractModel
{
    protected $table = 'store_countries';

    public function states()
    {
        return $this->hasMany('\Store\Model\State');
    }
}
