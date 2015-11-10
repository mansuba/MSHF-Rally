<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use Store\Model\Product;
use Store\Module;

class Extension
{
    public $name;
    public $description;
    public $version = STORE_VERSION;
    public $docs_url = 'http://exp-resso.com/store/docs';
    public $settings_exist = 'y';
    public $settings = array();
    public $required_by = array('module');

    protected $ee;
    protected $_store_custom_fields;

    public function __construct($ee = null)
    {
        $this->ee = $ee ?: ee();
        $this->name = lang('store_module_name');
        $this->description = lang('store_module_description');
    }

    public function activate_extension()
    {
        // install handled by module
        return true;
    }

    public function update_extension($current = '')
    {
        return true;
    }

    public function disable_extension()
    {
        return true;
    }

    public function settings_form()
    {
        $this->ee->functions->redirect(BASE.'&'.STORE_CP.'&sc=settings');
    }

    /**
     * Add product information to channel entries tags
     */
    public function channel_entries_query_result($channel, $query_result)
    {
        if ($this->ee->extensions->last_call !== false) {
            $query_result = $this->ee->extensions->last_call;
        }

        $custom_fields = array_map(function($field) {
            return 'field_id_'.$field['field_id'];
        }, $this->ee->store->products->get_channel_fields());
        $store_entry_ids = array();

        foreach ($query_result as $row_id => $entry) {
            foreach ($custom_fields as $field_name) {
                if ( ! empty($entry[$field_name])) {
                    // this field needs to be replaced with store product data
                    $store_entry_ids[$entry['entry_id']] = $row_id;
                }
            }
        }

        // do we need to load additional product data?
        if ( ! empty($store_entry_ids)) {
            $products = Product::with('stock')
                ->whereIn('store_products.entry_id', array_keys($store_entry_ids))->get();

            foreach ($products as $product) {
                $this->ee->store->products->apply_sales($product);
                $entry_id = (int) $product->entry_id;
                $row_id = $store_entry_ids[$entry_id];

                foreach ($custom_fields as $field_name) {
                    if ( ! empty($query_result[$row_id][$field_name])) {
                        $query_result[$row_id][$field_name] = $product->toTagArray();
                    }
                }
            }
        }

        return $query_result;
    }

    public function cp_menu_array($menu)
    {
        if ($this->ee->extensions->last_call !== false) {
            $menu = $this->ee->extensions->last_call;
        }

        if (!config_item('store_site_enabled')) {
            return $menu;
        }

        if ($this->ee->session->userdata['group_id'] != 1) {
            // check whether the current user can access the store module
            if ( ! $this->ee->cp->allowed_group('can_access_addons', 'can_access_modules')) return $menu;

            $this->ee->db->from('modules m');
            $this->ee->db->join('module_member_groups mg', 'mg.module_id = m.module_id');
            $this->ee->db->where('mg.group_id', $this->ee->session->userdata('group_id'));
            $this->ee->db->where('m.module_name', 'Store');
            if ($this->ee->db->count_all_results() == 0) return $menu;
        }

        // if we got to this point, add the store menu
        $menu['store'] = array();
        $menu['store']['dashboard'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store';
        $menu['store'][] = '----';
        $menu['store']['orders'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;sc=orders';
        $menu['store']['customers'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;sc=customers';

        if ($this->ee->store->config->has_privilege('can_access_inventory')) {
            $menu['store']['inventory'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;sc=inventory';
        }

        $menu['store']['promotions'] = array();
        $menu['store']['promotions']['sales'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;sc=sales';
        $menu['store']['promotions']['discounts'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;sc=discounts';

        $menu['store']['reports'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;sc=reports';

        if ($this->ee->store->config->has_privilege('can_access_settings')) {
            $menu['store'][] = '----';
            $menu['store']['settings'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;sc=settings';
        }

        return $menu;
    }

    /**
     * This hook is used to work around the fact that some gateways (namely DPS) will not allow
     * return URLs which include a query string, which prevents us from using regular ACT URLs.
     */
    public function sessions_end($session)
    {
        if ($this->ee->uri->segment(1) === 'payment_return') {
            // assign the session object prematurely, since EE won't need it anyway
            // (this hook runs inside the Session object constructor, which is a bit weird)
            $this->ee->session = $session;

            $_GET['H'] = (string) $this->ee->uri->segment(2);

            $module = new Module;
            $module->act_payment_return();
        }
    }

    /**
     * Member logout hook
     *
     * Clear the user's cart on logout
     */
    public function member_member_logout()
    {
        $this->ee->store->orders->clear_cart_cookie();
    }
}
