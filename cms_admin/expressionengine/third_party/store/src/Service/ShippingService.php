<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Store\Model\Country;
use Store\Model\Order;
use Store\Model\OrderShippingMethod;
use Store\Model\ShippingMethod;
use Store\Model\ShippingRule;
use Store\Model\State;

class ShippingService extends AbstractService
{
    private static $cachedCountries;
    private static $cachedRegions;

    /**
     * A cached list of countries and states for the current site
     */
    public function get_countries()
    {
        if (static::$cachedCountries === null) {
            static::$cachedCountries = array();

            $countries = Country::with(array(
                'states' => function($query) { $query->orderBy('name'); }
            ))->where('site_id', config_item('site_id'))->orderBy('name')->get();

            foreach ($countries as $country) {
                $data = $country->toTagArray();
                $data['states'] = array();
                foreach ($country->states as $state) {
                    $data['states'][$state->code] = $state->toTagArray();
                }
                static::$cachedCountries[$country->code] = $data;
            }
        }

        return static::$cachedCountries;
    }

    /**
     * A cached list of countries and states for the current site, in JSON format
     */
    public function get_countries_json()
    {
        return json_encode($this->get_countries());
    }

    /**
     * Look up the name of a specified country from the cached country list
     */
    public function get_country_name($countryCode)
    {
        $countries = $this->get_countries();
        if (isset($countries[$countryCode])) {
            return $countries[$countryCode]['name'];
        }
    }

    /**
     * Look up the name of a specified region from the cached regions list
     */
    public function get_state_name($countryCode, $stateCode)
    {
        $countries = $this->get_countries();
        if (isset($countries[$countryCode]['states'][$stateCode])) {
            return $countries[$countryCode]['states'][$stateCode]['name'];
        }
    }

    /**
     * Get a list of <option> elements representing available countries
     */
    public function get_enabled_country_options($selectedCountryCode = null, $placeholder = null)
    {
        $options = array();
        $countries = $this->get_countries();
        foreach ($countries as $code => $country) {
            if ($country['enabled']) {
                $selected = $code === $selectedCountryCode ? 'selected' : '';
                $options[] = "<option value='$code' $selected>{$country['name']}</option>";
            }
        }

        if (empty($options)) {
            return "<option value=''>$placeholder</option>";
        }

        if (null !== $placeholder) {
            array_unshift($options, "<option value=''>$placeholder</option>");
        }

        return implode("\n", $options);
    }

    public function get_enabled_state_options($countryCode, $selectedStateCode = null, $placeholder = null)
    {
        $options = array();
        $countries = $this->get_countries();

        if (empty($countries[$countryCode]['states'])) {
            return "<option value=''>$placeholder</option>";
        }

        foreach ($countries[$countryCode]['states'] as $code => $state) {
            $selected = $code === $selectedStateCode ? 'selected' : '';
            $options[] = "<option value='$code' $selected>{$state['name']}</option>";
        }

        if (null !== $placeholder) {
            array_unshift($options, "<option value=''>$placeholder</option>");
        }

        return implode("\n", $options);
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
        if ($rule->postcode && !$this->glob_match($rule->postcode, $order->shipping_postcode)) {
            return false;
        }

        // order qty rules
        if ($rule->min_order_qty && $rule->min_order_qty > $order->order_shipping_qty) {
            return false;
        }
        if ($rule->max_order_qty && $rule->max_order_qty < $order->order_shipping_qty) {
            return false;
        }

        // order total rules
        if ($rule->min_order_total && $rule->min_order_total > $order->order_shipping_subtotal) {
            return false;
        }
        if ($rule->max_order_total && $rule->max_order_total < $order->order_shipping_subtotal) {
            return false;
        }

        // order weight rules
        if ($rule->min_weight && $rule->min_weight > $order->order_shipping_weight) {
            return false;
        }
        if ($rule->max_weight && $rule->max_weight < $order->order_shipping_weight) {
            return false;
        }

        // all rules match
        return true;
    }

    /**
     * Match postcode glob patterns, for example 123* or 123?
     */
    protected function glob_match($pattern, $subject)
    {
        // convert glob pattern to regex
        $regex = '/^'.str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($pattern, '/')).'$/i';

        return (bool) preg_match($regex, $subject);
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
}
