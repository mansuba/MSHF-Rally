<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Adjuster;

use Store\Model\Discount;
use Store\Model\Order;
use Store\Model\OrderAdjustment;
use Store\Model\OrderItem;

/**
 * Calculate promotions applicable to an order
 */
class DiscountAdjuster extends AbstractAdjuster
{
    public function adjust(Order $order)
    {
        $this->reset_discount_totals($order);

        $adjustments = array();
        $discounts = $this->ee->store->orders->get_available_discounts($order->promo_code);

        foreach ($discounts as $discount) {
            if ($adjustment = $this->calculate($order, $discount)) {
                // adjustment amount is negative, but order_discount is positive
                $order->order_discount -= $adjustment->amount;

                // first matched discount is stored with order
                if (null === $order->discount_id) {
                    $order->discount_id = $discount->id;
                }

                $adjustments[] = $adjustment;

                // should we stop processing discounts?
                if ($discount->break) {
                    return $adjustments;
                }
            }
        }

        return $adjustments;
    }

    public function reset_discount_totals(Order $order)
    {
        $order->order_discount = 0;
        $order->order_shipping_discount = 0;
        $order->discount_id = null;

        foreach ($order->items as $item) {
            $item->item_discount = 0;
        }
    }

    public function calculate(Order $order, Discount $discount)
    {
        if ($discount->type == 'items') {
            return $this->calculate_items_discount($order, $discount);
        } elseif ($discount->type == 'bulk') {
            return $this->calculate_bulk_discount($order, $discount);
        }
    }

    protected function calculate_items_discount(Order $order, Discount $discount)
    {
        $matched_items = array();
        $matched_qty = 0;
        $matched_total = 0;

        foreach ($order->items as $item) {
            if ($this->match_item($item, $discount)) {
                $matched_items[] = array('item' => $item, 'qty' => $item->item_qty);
                $matched_qty += $item->item_qty;
                $matched_total += $item->item_qty * $item->price;
            }
        }

        if ($matched_items) {
            if ($discount->purchase_qty && $matched_qty < $discount->purchase_qty) {
                return;
            }

            if ($discount->purchase_total && $matched_total < $discount->purchase_total) {
                return;
            }

            return $this->generate_adjustment($matched_items, $order, $discount);
        }
    }

    protected function calculate_bulk_discount(Order $order, Discount $discount)
    {
        $matched_items = array();
        $repeat_qty = $discount->step_qty + $discount->discount_qty;

        foreach ($order->items as $item) {
            if ($this->match_item($item, $discount)) {
                $repetitions = floor($item->item_qty / $repeat_qty);
                $matched_qty = $discount->discount_qty * $repetitions;

                $remainder = $item->item_qty % $repeat_qty;
                if ($remainder > $discount->step_qty) {
                    $matched_qty += $remainder - $discount->step_qty;
                }

                if ($matched_qty > 0) {
                    $matched_items[] = array('item' => $item, 'qty' => $matched_qty);
                }
            }
        }

        if ($matched_items) {
            return $this->generate_adjustment($matched_items, $order, $discount);
        }
    }

    protected function generate_adjustment(array $matched_items, Order $order, Discount $discount)
    {
        // calculate discount amount
        $amount = $discount->base_discount;
        $matched_qty = 0;

        foreach ($matched_items as $item) {
            $matched_qty += $item['qty'];
            $amount += $discount->per_item_discount * $item['qty'];
            $amount += $discount->percent_discount / 100 * $item['qty'] * $item['item']->price;
        }

        // now that we have the total, update item_discount
        $amount_per_item = store_round_currency($amount / $matched_qty);
        $allocated_amount = 0;
        foreach ($matched_items as $item) {
            $item_discount = $amount_per_item * $item['qty'];
            $allocated_amount += $item_discount;
            $item['item']->item_discount += $item_discount;
            $item['item']->item_total -= $item_discount;
        }

        // account for rounding errors when splitting discount amount between items
        $last_matched_item = end($matched_items);
        $rounding_offset = store_round_currency($amount - $allocated_amount);
        $last_matched_item['item']->item_discount += $rounding_offset;
        $last_matched_item['item']->item_total -= $rounding_offset;

        // apply free shipping discount
        if ($discount->free_shipping) {
            // take into account any existing discount
            $shipping_discount = $order->order_shipping_inc_discount;
            $amount += $shipping_discount;
            $order->order_shipping_discount += $shipping_discount;
            $order->order_shipping_total -= $shipping_discount;
        }

        // create adjustment (amount should be negative)
        $adjustment = new OrderAdjustment;
        $adjustment->name = $discount->name;
        $adjustment->type = 'discount';
        $adjustment->amount = store_round_currency(-$amount, true);
        $adjustment->taxable = 0;
        $adjustment->included = 0;

        return $adjustment;
    }

    /**
     * Match item with discount
     *
     * @return bool True if a discount applies to a particular item
     */
    public function match_item(OrderItem $item, Discount $discount)
    {
        // match entry ids
        if ($discount->entry_ids && !in_array($item->entry_id, $discount->entry_ids)) {
            return false;
        }

        // match categories
        if ($discount->category_ids && !array_intersect($item->category_ids, $discount->category_ids)) {
            return false;
        }

        // exclude on sale items
        if ($discount->exclude_on_sale && $item->on_sale) {
            return false;
        }

        return true;
    }
}
