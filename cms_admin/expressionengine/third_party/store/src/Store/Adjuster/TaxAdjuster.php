<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Adjuster;

use Store\Model\Order;
use Store\Model\OrderItem;
use Store\Model\OrderAdjustment;
use Store\Model\Tax;

/**
 * Calculate taxes applicable to an order
 */
class TaxAdjuster extends AbstractAdjuster
{
    public function adjust(Order $order)
    {
        $adjustments = array();

        $order->order_tax = 0;
        $order->order_shipping_tax = 0;
        $order->order_handling_tax = 0;
        $order->tax_id = null;
        $order->tax_name = null;
        $order->tax_rate = 0;

        // reset item taxes
        foreach ($order->items as $item) {
            $item->item_tax = 0;
        }

        // get enabled taxes
        $taxes = $this->ee->store->orders->get_order_taxes($order);
        foreach ($taxes as $tax) {
            if ($adjustment = $this->calculate($order, $tax)) {
                // update tax total
                $order->order_tax += $adjustment->amount;

                // first matched tax gets honour of being stored with order
                if (null === $order->tax_id) {
                    $order->tax_id = $tax->id;
                    $order->tax_name = $adjustment->name;
                    $order->tax_rate = $adjustment->rate;
                }

                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    public function calculate(Order $order, Tax $tax)
    {
        $total_tax = $this->calculate_total_tax($order, $tax);
        if ($total_tax) {
            $adjustment = new OrderAdjustment();
            $adjustment->name = $tax->name;
            $adjustment->type = 'tax';
            $adjustment->rate = $tax->rate;
            $adjustment->taxable = 0;
            $adjustment->included = $tax->included;
            $adjustment->amount = $total_tax;

            return $adjustment;
        }
    }

    public function calculate_tax($amount, Tax $tax)
    {
        if ($tax->included) {
            return store_round_currency($amount * (1 - 1 / (1 + $tax->rate)));
        } else {
            return store_round_currency($amount * $tax->rate);
        }
    }

    public function calculate_total_tax(Order $order, Tax $tax)
    {
        $total_tax = 0;

        // sum items whose categories match at least one of the categories this tax applies to
        foreach ($order->items as $item) {
            if ($this->is_item_taxable($item, $tax)) {
                $item_tax = $this->calculate_tax($item->item_subtotal_inc_discount, $tax);
                $total_tax += $item_tax;
                $item->item_tax += $item_tax;

                // only exclusive taxes affect item total
                if (!$tax->included) {
                    $item->item_total += $item_tax;
                }
            }
        }

        if ($tax->apply_to_shipping) {
            $shipping_tax = $this->calculate_tax($order->order_shipping_inc_discount, $tax);
            $total_tax += $shipping_tax;
            $order->order_shipping_tax += $shipping_tax;

            $handling_tax = $this->calculate_tax($order->order_handling, $tax);
            $total_tax += $handling_tax;
            $order->order_handling_tax += $handling_tax;

            // only exclusive taxes affect shipping total
            if (!$tax->included) {
                $order->order_shipping_total += $shipping_tax;
                $order->order_handling_total += $handling_tax;
            }
        }

        return $total_tax;
    }

    public function is_item_taxable(OrderItem $item, Tax $tax)
    {
        return !$tax->category_ids || array_intersect($item->category_ids, $tax->category_ids);
    }
}
