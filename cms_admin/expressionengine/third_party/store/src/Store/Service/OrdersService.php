<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Store\Adjuster\DiscountAdjuster;
use Store\Adjuster\HandlingAdjuster;
use Store\Adjuster\ShippingAdjuster;
use Store\Adjuster\TaxAdjuster;
use Store\Model\Discount;
use Store\Model\Order;
use Store\Model\OrderAdjustment;
use Store\Model\OrderHistory;
use Store\Model\OrderItem;
use Store\Model\OrderShippingMethod;
use Store\Model\ShippingMethod;
use Store\Model\ShippingRule;
use Store\Model\Status;
use Store\Model\Tax;
use Store\Model\Transaction;

/**
 * Orders Service
 *
 * Provdes methods to handle cart, order, and payment processing.
 */
class OrdersService extends AbstractService
{
    private $cart;
    protected $cookie_name = 'store_cart';
    protected $cached_order_statuses;
    protected $empty_cart = false;

    public function get_cart()
    {
        if (null === $this->cart) {
            $cookie = $this->get_cart_cookie();
            if ($cookie && !$this->empty_cart) {
                $this->cart = Order::with(array('items', 'adjustments'))
                    ->where('site_id', config_item('site_id'))
                    ->where('order_hash', $cookie)
                    ->whereNull('order_completed_date')
                    ->first();
            }

            if (empty($this->cart)) {
                $this->cart = new Order;
                $this->cart->site_id = config_item('site_id');
            }

            $this->cart->order_date = time();
            $this->cart->ip_address = $this->ee->input->ip_address();
            $this->cart->ip_country = $this->ee->store->store->ip_country($this->cart->ip_address);

            $member_id = (int) $this->ee->session->userdata['member_id'];
            if ( ! $this->cart->isEmpty() && (int) $this->cart->member_id != $member_id) {
                // member_id has changed, reload the cart
                $this->cart->member_id = $member_id;
                $this->cart->recalculate();
                $this->cart->save();
            }
        }

        return $this->cart;
    }

    /**
     * Get the cart cookie value
     */
    public function get_cart_cookie()
    {
        return $this->ee->input->cookie($this->cookie_name);
    }

    /**
     * Set a cookie for the current cart
     */
    public function set_cart_cookie()
    {
        $cart = $this->get_cart();
        if ($cart->exists) {
            $this->ee->input->set_cookie(
                $this->cookie_name,
                $cart->order_hash,
                config_item('store_cart_expiry') * 60
            );
        } else {
            $this->clear_cart_cookie();
        }
    }

    /**
     * Clear the cookie for the current cart (effectively emptying the cart)
     */
    public function clear_cart_cookie()
    {
        $old_cart = $this->get_cart_cookie() ? $this->get_cart() : null;

        $this->ee->input->delete_cookie($this->cookie_name);
        $this->empty_cart = true; // prevent reloading cart during current request
        $this->cart = null;

        if ($this->ee->extensions->active_hook('store_order_empty_cart')) {
            $this->ee->extensions->call('store_order_empty_cart', $old_cart);
        }
    }

    /**
     * Get all active order adjusters
     */
    public function get_adjusters()
    {
        $adjusters = array(
            10 => new ShippingAdjuster($this->ee),
            15 => new HandlingAdjuster($this->ee),
            20 => new DiscountAdjuster($this->ee),
            30 => new TaxAdjuster($this->ee),
        );

        if ($this->ee->extensions->active_hook('store_order_adjusters')) {
            $adjusters = $this->ee->extensions->call('store_order_adjusters', $adjusters);
        }

        ksort($adjusters);

        return $adjusters;
    }

    public function get_order_shipping_methods(Order $order)
    {
        $methods = ShippingMethod::with(array('rules' => function($query) {
            $query->orderBy('sort');
        }))->where('site_id', config_item('site_id'))
            ->where('enabled', 1)
            ->orderBy('sort')->get();

        $options = array();
        foreach ($methods as $method) {
            $rule = $this->match_shipping_rule($order, $method);
            if ($rule) {
                $option = new OrderShippingMethod;
                $option->id = $method->id;
                $option->name = $method->name;
                $option->amount = $this->calculate_shipping_rule($order, $rule);

                $options[$option->id] = $option;
            }
        }

        /**
         * store_order_shipping_methods hook
         * @since 2.0.0
         */
        if ($this->ee->extensions->active_hook('store_order_shipping_methods')) {
           $options = $this->ee->extensions->call('store_order_shipping_methods', $order, $options);
        }

        return $options;
    }

    public function match_shipping_rule(Order $order, ShippingMethod $method)
    {
        foreach ($method->rules as $rule) {
            if ($this->test_shipping_rule($order, $rule)) {
                return $rule;
            }
        }
    }

    public function test_shipping_rule(Order $order, ShippingRule $rule)
    {
        if (!$rule->enabled) {
            return false;
        }

        // geographical filters
        if ($rule->country_code && $rule->country_code != $order->shipping_country) {
            return false;
        }
        if ($rule->state_code && $rule->state_code != $order->shipping_state) {
            return false;
        }
        if ($rule->postcode != '' AND $rule->postcode != $order->shipping_postcode) {
            return false;
        }

        // order qty rules are inclusive (min <= x <= max)
        if ($rule->min_order_qty AND $rule->min_order_qty > $order->order_shipping_qty) {
            return false;
        }
        if ($rule->max_order_qty AND $rule->max_order_qty < $order->order_shipping_qty) {
            return false;
        }

        // order total rules exclude maximum limit (min <= x < max)
        if ($rule->min_order_total AND $rule->min_order_total > $order->order_shipping_subtotal) {
            return false;
        }
        if ($rule->max_order_total AND $rule->max_order_total <= $order->order_shipping_subtotal) {
            return false;
        }

        // order weight rules exclude maximum limit (min <= x < max)
        if ($rule->min_weight AND $rule->min_weight > $order->order_shipping_weight) {
            return false;
        }
        if ($rule->max_weight AND $rule->max_weight <= $order->order_shipping_weight) {
            return false;
        }

        // all rules match
        return true;
    }

    public function calculate_shipping_rule(Order $order, ShippingRule $rule)
    {
        if ($order->order_shipping_qty == 0) {
            return 0.0;
        }

        $amount = $rule->base_rate;
        $amount += $rule->per_item_rate * $order->order_shipping_qty;
        $amount += $rule->per_weight_rate * $order->order_shipping_weight;
        $amount += $rule->percent_rate / 100 * $order->order_shipping_subtotal;
        $amount = max($amount, $rule->min_rate);

        if ($rule->max_rate > 0) {
            $amount = min($amount, $rule->max_rate);
        }

        return $amount;
    }

    public function get_order_taxes(Order $order)
    {
        $taxes = Tax::with('categories')
            ->where('site_id', $order->site_id)
            ->where('enabled', 1)
            ->where(function($query) use ($order) {
                $query->whereNull('country_code')
                    ->orWhere('country_code', '')
                    ->orWhere('country_code', $order->shipping_country);
            })->where(function($query) use ($order) {
                $query->whereNull('state_code')
                    ->orWhere('state_code', '')
                    ->orWhere('state_code', $order->shipping_state);
            })->get();

        if ($this->ee->extensions->active_hook('store_order_taxes')) {
           $taxes = $this->ee->extensions->call('store_order_taxes', $order, $taxes);
        }

        return $taxes;
    }

    /**
     * Get all active discounts for the current site
     *
     * @param  string $promo_code        Include discounts matching the specified promo code
     * @param  bool   $include_automatic Include automatic discounts (those with no promo code required)
     * @return array
     */
    public function get_available_discounts($promo_code, $include_automatic = true)
    {
        $query = Discount::where('site_id', config_item('site_id'))
            ->where('enabled', 1)
            ->where(function($query) use ($promo_code, $include_automatic) {
                $query->where('code', (string) $promo_code);
                if ($include_automatic) {
                    $query->orWhere('code', '')->orWhereNull('code');
                }
            })->where(function($query) {
                $query->whereNull('start_date')->orWhere('start_date', '<=', time());
            })->where(function($query) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', time());
            })->whereRaw('(total_use_limit IS NULL OR total_use_limit > total_use_count)')
            ->orderBy('sort')
            ->get();

        $result = array();
        $member_id = $this->ee->session->userdata['member_id'];
        $group_id = $this->ee->session->userdata['group_id'];
        foreach ($query as $discount) {
            // match member group
            if (count($discount->member_group_ids) > 0 &&
                !in_array($group_id, $discount->member_group_ids)) {
                continue;
            }

            // if per user limit, user must be logged in
            if ($discount->per_user_limit > 0 && empty($member_id)) {
                continue;
            }

            // check per user limit has not been reached
            if ($discount->per_user_limit > 0) {
                $user_use_count = Order::where('order_completed_date', '>', 0)
                    ->where('member_id', $member_id)
                    ->where('discount_id', $discount->id)
                    ->count();

                if ($user_use_count >= $discount->per_user_limit) {
                    continue;
                }
            }

            $result[] = $discount;
        }

        return $result;
    }

    /**
     * Delete records and related
     */
    public function delete_orders($order_ids)
    {
        // ensure orders exist in current site
        $order_ids = Order::where('site_id', config_item('site_id'))->whereIn('id', (array) $order_ids)->lists('id');

        if (!empty($order_ids)) {
            Order::whereIn('id', $order_ids)->delete();
            OrderAdjustment::whereIn('order_id', $order_ids)->delete();
            OrderHistory::whereIn('order_id', $order_ids)->delete();
            OrderItem::whereIn('order_id', $order_ids)->delete();
            Transaction::whereIn('order_id', $order_ids)->delete();
        }
    }

    /**
     * Lazy load and cache all statuses for current site
     */
    public function order_statuses()
    {
        if (is_null($this->cached_order_statuses)) {
            $query = Status::where('site_id', config_item('site_id'))->orderBy('sort')->get();

            $this->cached_order_statuses = array();
            foreach ($query as $row) {
                $this->cached_order_statuses[$row->name] = $row;
            }
        }

        return $this->cached_order_statuses;
    }

    /**
     * Generate secure download key
     *
     * Secure enough that someone would need to know your EE license number before
     * they would be able to brute force a valid download link
     */
    public function generate_download_key(Order $order, $file_id, $expire)
    {
        return sha1($order->order_id.$order->order_hash.$file_id.$expire.config_item('license_number'));
    }

    public function is_download_expired(Order $order, $expire)
    {
        // return false if there is no expiry date
        if ($expire <= 0) return false;

        // return true if the expiry date is in the past
        $expire_date = $order->order_paid_date + ($expire * 60);

        return $expire_date <= time();
    }
}
