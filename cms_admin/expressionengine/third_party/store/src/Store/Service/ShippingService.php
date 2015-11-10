<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Store\Model\Country;
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
}
