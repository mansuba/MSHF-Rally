<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class StockOption extends AbstractModel
{
    protected $table = 'store_stock_options';

    public function stock()
    {
        return $this->belongsTo('\Store\Model\Stock');
    }

    public function toArray()
    {
        return $this->attributesToArray();
    }
}
