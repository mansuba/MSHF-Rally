<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Adjuster;

use Store\Model\Order;
use Store\Model\OrderAdjustment;

/**
 * Calculate handling surcharge applicable to an order
 */
class HandlingAdjuster extends AbstractAdjuster
{
    public function adjust(Order $order)
    {
        $order->order_handling = $this->calculate($order);
        $order->order_handling_tax = 0;
        $order->order_handling_total = $order->order_handling;

        if ($order->order_handling > 0) {
            $adjustment = new OrderAdjustment();
            $adjustment->name = lang('store.handling');
            $adjustment->type = 'handling';
            $adjustment->amount = $order->order_handling;
            $adjustment->taxable = 1;
            $adjustment->included = 0;

            return array($adjustment);
        }

        return array();
    }

    public function calculate(Order $order)
    {
        $total = 0;

        foreach ($order->items as $item) {
            $total += $item->handling * $item->item_qty;
        }

        return $total;
    }
}
