<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Store\Model\Config;

class ConfigService extends AbstractService
{
    public $settings = array(
        'store_cart_expiry' => 1440,
        'store_cc_payment_method' => array('type' => 'select', 'options' => array('purchase' => 'store.settings.cc_payment_method_purchase', 'authorize' => 'store.settings.cc_payment_method_authorize'), 'default' => 'purchase'),
        'store_conversion_tracking_extra' => array('type' => 'textarea', 'default' => ''),
        'store_currency_code' => 'USD',
        'store_currency_dec_point' => '.',
        'store_currency_decimals' => 2,
        'store_currency_suffix' => '',
        'store_currency_symbol' => '$',
        'store_currency_thousands_sep' => ',',
        'store_default_country' => '',
        'store_default_order_address' => array('type' => 'select', 'options' => array('none' => 'store.none', 'shipping_same_as_billing' => 'store.shipping_same_as_billing', 'billing_same_as_shipping' => 'store.billing_same_as_shipping'), 'default' => 'none'),
        'store_default_shipping_method_id' => '',
        'store_default_state' => '',
        'store_dimension_units' => array('type' => 'select', 'options' => array('mm' => 'store.settings.dimension_units_mm', 'cm' => 'store.settings.dimension_units_cm', 'm' => 'store.settings.dimension_units_m', 'ft' => 'store.settings.dimension_units_ft', 'in' => 'store.settings.dimension_units_in'), 'default' => 'mm'),
        'store_unofficial_payment_gateways' => array('type' => 'select', 'options' => array('1' => 'yes', '0' => 'no'), 'default' => '0'),
        'store_export_pdf_orientation' => array('type' => 'select', 'options' => array('P' => 'store.settings.pdf_orientation_portrait', 'L' => 'store.settings.pdf_orientation_landscape'), 'default' => 'P'),
        'store_export_pdf_page_format' => array('type' => 'select', 'options' => array('A4' => 'store.settings.pdf_page_format_a4', 'A3' => 'store.settings.pdf_page_format_a3', 'LETTER' => 'store.settings.pdf_page_format_letter'), 'default' => 'A4'),
        'store_force_member_login' => array('type' => 'select', 'options' => array('1' => 'yes', '0' => 'no'), 'default' => '0'),
        'store_from_email' => '',
        'store_from_name' => '',
        'store_google_analytics_ecommerce' => array('type' => 'select', 'options' => array('1' => 'store.enabled', '0' => 'store.disabled'), 'default' => '1'),
        'store_order_details_footer' => array('type' => 'textarea', 'default' => ''),
        'store_order_details_header' => array('type' => 'textarea', 'default' => ''),
        'store_order_details_header_right' => array('type' => 'textarea', 'default' => ''),
        'store_order_fields' => '',
        'store_order_invoice_url' => '',
        'store_secure_template_tags' => array('type' => 'select', 'options' => array('1' => 'yes', '0' => 'no'), 'default' => '0'),
        'store_security' => '',
        'store_weight_units' => array('type' => 'select', 'options' => array('g' => 'store.settings.weight_units_g', 'kg' => 'store.settings.weight_units_kg', 'lb' => 'store.settings.weight_units_lb'), 'default' => 'g'),
    );

    protected $cached_order_fields;

    public function items()
    {
        if ($this->ee->db->table_exists('store_config')) {
            $configs = Config::where('site_id', config_item('site_id'))->get();
        }

        if (empty($configs) || count($configs) == 0) {
            return array('store_site_enabled' => false);
        }

        $items = array();
        foreach ($this->settings as $key => $default) {
            $items[$key] = store_setting_default($default);
        }

        foreach ($configs as $row) {
            $key = $row->preference;
            if (isset($this->settings[$key])) {
                $items[$key] = $row->value;
            }
        }

        $items['store_site_enabled'] = true;

        return $items;
    }

    public function load()
    {
        foreach ($this->items() as $key => $value) {
            $this->ee->config->set_item($key, $value);
        }
    }

    public function update($items)
    {
        foreach ($this->items() as $key => $value) {
            // do we have a new value for this preference?
            if (isset($items[$key])) {
                $value = $items[$key];
                $this->ee->config->set_item($key, $value);
            }

            $row = Config::where('site_id', config_item('site_id'))->where('preference', $key)->first();
            if (!$row) {
                $row = new Config;
                $row->site_id = config_item('site_id');
                $row->preference = $key;
            }

            $row->value = $value;
            $row->save();
        }
    }

    /**
     * Lazy load all order fields for current site
     */
    public function order_fields()
    {
        if (is_null($this->cached_order_fields)) {
            $this->cached_order_fields = $this->order_field_defaults();

            // load data from current site config
            $config = config_item('store_order_fields');

            // avoid PHP isset() bug #53971 on < 5.3.6
            if (is_array($config)) {
                foreach ($this->cached_order_fields as $key => $field) {
                    // does field have a custom name?
                    if (isset($field['title']) AND isset($config[$key]['title'])) {
                        $this->cached_order_fields[$key]['title'] = $config[$key]['title'];
                    }

                    // is field mapped to a member field?
                    if (isset($config[$key]['member_field'])) {
                        $this->cached_order_fields[$key]['member_field'] = $config[$key]['member_field'];
                    }
                }
            }
        }

        return $this->cached_order_fields;
    }

    public function order_field_defaults()
    {
        return array(
            'billing_first_name' => array('member_field' => ''),
            'billing_last_name' => array('member_field' => ''),
            'billing_address1' => array('member_field' => ''),
            'billing_address2' => array('member_field' => ''),
            'billing_city' => array('member_field' => ''),
            'billing_state' => array('member_field' => ''),
            'billing_country' => array('member_field' => ''),
            'billing_postcode' => array('member_field' => ''),
            'billing_phone' => array('member_field' => ''),
            'billing_company' => array('member_field' => ''),
            'shipping_first_name' => array('member_field' => ''),
            'shipping_last_name' => array('member_field' => ''),
            'shipping_address1' => array('member_field' => ''),
            'shipping_address2' => array('member_field' => ''),
            'shipping_city' => array('member_field' => ''),
            'shipping_state' => array('member_field' => ''),
            'shipping_country' => array('member_field' => ''),
            'shipping_postcode' => array('member_field' => ''),
            'shipping_phone' => array('member_field' => ''),
            'shipping_company' => array('member_field' => ''),
            'order_email' => array('member_field' => ''),
            'order_custom1' => array('title' => '', 'member_field' => ''),
            'order_custom2' => array('title' => '', 'member_field' => ''),
            'order_custom3' => array('title' => '', 'member_field' => ''),
            'order_custom4' => array('title' => '', 'member_field' => ''),
            'order_custom5' => array('title' => '', 'member_field' => ''),
            'order_custom6' => array('title' => '', 'member_field' => ''),
            'order_custom7' => array('title' => '', 'member_field' => ''),
            'order_custom8' => array('title' => '', 'member_field' => ''),
            'order_custom9' => array('title' => '', 'member_field' => ''),
        );
    }

    public function is_super_admin()
    {
        return $this->ee->session->userdata['group_id'] == 1;
    }

    public function security()
    {
        $security_defaults = array('can_access_settings', 'can_add_payments');

        $result = array();
        $security = config_item('store_security');

        foreach ($security_defaults as $key) {
            $result[$key] = (isset($security[$key]) AND is_array($security[$key])) ? $security[$key] : array();
        }

        return $result;
    }

    public function has_privilege($privilege)
    {
        if ($this->is_super_admin()) {
            return true;
        }

        if ($privilege == 'can_access_inventory') {
            $store_channels = $this->ee->store->store->get_store_channels();
            $assigned_channels = $this->ee->functions->fetch_assigned_channels();

            // must be assigned to all Store channels
            return array_intersect($store_channels, $assigned_channels) == $store_channels;
        }

        $security = $this->security();
        if (in_array($this->ee->session->userdata['group_id'], $security[$privilege])) {
            return true;
        }

        return false;
    }

    public function config_json()
    {
        $items = array();
        foreach (array('store_currency_symbol', 'store_currency_decimals',
            'store_currency_thousands_sep', 'store_currency_dec_point',
            'store_currency_suffix') as $key) {
            $items[$key] = config_item($key);
        }

        return json_encode($items);
    }

    /**
     * Add Store javascript & css to CP page header
     */
    public function load_cp_assets()
    {
        $this->ee->cp->add_to_head('<link rel="stylesheet" type="text/css" href="'.$this->asset_url('store.cp.css').'" />');
        $this->ee->cp->add_to_foot('<script type="text/javascript" src="'.$this->asset_url('store.cp.js').'"></script>');
    }

    public function asset_url($filename)
    {
        return URL_THIRD_THEMES.'store/'.$filename.'?v='.STORE_VERSION;
    }
}
