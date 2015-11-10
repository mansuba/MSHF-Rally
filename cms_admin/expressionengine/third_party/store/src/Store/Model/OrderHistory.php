<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class OrderHistory extends AbstractModel
{
    protected $table = 'store_order_history';

    public function order()
    {
        return $this->belongsTo('\Store\Model\Order');
    }

    public function member()
    {
        return $this->belongsTo('\Store\Model\Member', 'order_status_member_id');
    }
}
