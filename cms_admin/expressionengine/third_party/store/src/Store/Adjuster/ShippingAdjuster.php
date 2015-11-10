<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Adjuster;

use Store\Model\Order;
use Store\Model\OrderAdjustment;

/**
 * Calculate shipping applicable to an order
 */
class ShippingAdjuster extends AbstractAdjuster
{
    public function adjust(Order $order)
    {
        $adjustments = array();

        $order->order_shipping = 0;
        $order->order_shipping_discount = 0;
        $order->order_shipping_tax = 0;
        $order->order_shipping_total = 0;
        $order->order_handling = 0;
        $order->shipping_method_name = null;

        $methods = $this->ee->store->orders->get_order_shipping_methods($order);

        if (isset($methods[$order->shipping_method])) {
            $method = $methods[$order->shipping_method];

            $adjustment = new OrderAdjustment();
            $adjustment->name = $method->name;
            $adjustment->type = 'shipping';
            $adjustment->amount = $method->amount;
            $adjustment->taxable = 1;
            $adjustment->included = 0;

            $order->shipping_method_name = $method->name;
            $order->shipping_method_class = $method->class;
            $order->order_shipping += $method->amount;

            $adjustments[] = $adjustment;
        }

        $order->order_shipping_total = $order->order_shipping;

        return $adjustments;
    }
}
