<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Adjuster;

use Store\Model\Order;

/**
 * Adjusters are used to add extra "Adjustments" to an order, such as shipping, tax, discounts etc.
 */
interface AdjusterInterface
{
    /**
     * Check for and return adjustments for an order.
     *
     * @param  OrderModel $order The order to adjust
     * @return array      An array of adjustments to add to the order
     */
    public function adjust(Order $order);
}
