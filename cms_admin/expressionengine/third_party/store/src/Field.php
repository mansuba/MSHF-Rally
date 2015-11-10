<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use EE_Fieldtype;
use Store\Model\Product;
use Store\Model\Stock;

class Field extends EE_Fieldtype
{
    public $info = array(
        'name' => 'Store Product Details',
        'version' => STORE_VERSION
    );

    public $has_array_data = true;

    /**
     * Display field on the publish tab
     */
    public function display_field($field_data)
    {
        foreach (array('length_with_units', 'width_with_units', 'height_with_units') as $key) {
            ee()->lang->language['store.'.$key] = sprintf(
                lang('store.'.$key), config_item('store_dimension_units'));
        }
        ee()->lang->language['store.weight_with_units'] = sprintf(
            lang('store.weight_with_units'), config_item('store_weight_units'));

        ee()->load->library('table');

        $data = array();
        $data['field_name'] = $this->field_name;
        $data['field_required'] = $this->is_required();

        $product = $this->find_or_create_product(ee()->input->get('entry_id'));
        $data['product'] = $product;

        $post_data = ee()->input->post('store_product_field', true);
        if ($post_data) {
            $product->fill((array) $post_data);
        }

        $data['modifiers'] = isset($post_data['modifiers']) ? $post_data['modifiers'] : $product->getModifiersArray();

        // load store css + js
        ee()->store->config->load_cp_assets();
        ee()->cp->add_to_foot('
            <script type="text/javascript">
            ExpressoStore.productStock = '.$product->stock->toJSON().';
            </script>');
        ee()->cp->add_js_script(array(
            'ui' => array('datepicker', 'sortable'),
            'file' => array('underscore'),
        ));

        return ee()->load->view('field', $data, true);
    }

    protected function find_or_create_product($entry_id)
    {
        $entry_id = (int) $entry_id;
        $product = Product::with(array(
            'modifiers' => function($query) { $query->orderBy('mod_order'); },
            'modifiers.options' => function($query) { $query->orderBy('opt_order'); },
            'stock',
            'stock.stockOptions',
        ))->find($entry_id);

        if (!$product) {
            $product = new Product;
            $product->entry_id = $entry_id;
        }

        return $product;
    }

    /**
     * Prep the data for saving
     *
     * Cache product SKUs inside our custom field, so that it can be found by EE search tags.
     * We never actually use the data stored in the custom field, it is purely here for search.
     */
    public function save($data)
    {
        $field_data = ee()->input->post('store_product_field', true);
        $skus = array('[store]');

        if (!empty($field_data['stock'])) {
            foreach ($field_data['stock'] as $stock) {
                $skus[] = $stock['sku'];
            }
        }

        return implode(' ', $skus);
    }

    /**
     * Runs after an entry has been saved
     */
    public function post_save($data)
    {
        $product = $this->find_or_create_product($this->settings['entry_id']);
        $product->fill((array) ee()->input->post('store_product_field', true));

        // recursively save product
        ee()->store->products->save_product($product);
    }

    public function delete($entry_ids)
    {
        ee()->store->products->delete_all($entry_ids);
    }

    public function validate($data)
    {
        $entry_id = (int) ee()->input->post('entry_id');
        $field_data = ee()->input->post('store_product_field');
        $error = false;

        if ($this->is_required() && !$this->run_validation("store_product_field[price]", 'lang:store.price', 'required')) {
            $error = true;
        }

        // require names for any modifiers which haven't been removed
        if (isset($field_data['modifiers'])) {
            foreach ($field_data['modifiers'] as $mod_id => $modifier) {
                if (isset($modifier['mod_type'])) {
                    if (!$this->run_validation("store_product_field[modifiers][{$mod_id}][mod_name]", 'lang:name', 'required')) {
                        $error = true;
                    }
                }
            }
        }

        return $error;
    }

    protected function is_required()
    {
        return 'y' === $this->settings['field_required'];
    }

    /**
     * Immediately run validation rules
     */
    protected function run_validation($field, $label = '', $rules = '')
    {
        // set up validation rules
        $validation = ee()->form_validation;
        $validation->set_rules($field, $label, $rules);

        // inject post data into validation library
        $row =& $validation->_field_data[$field];
        $row['postdata'] = $validation->_reduce_array($_POST, $row['keys']);

        // run new validation rules
        $validation->_execute($row, explode('|', $row['rules']), $row['postdata']);

        return empty($row['error']);
    }

    /**
     * Allow {product_details} to be used as a tag pair
     */
    public function replace_tag($data, $params = array(), $tagdata = false)
    {
        if ($tagdata) {
            return ee()->TMPL->parse_variables($tagdata, array($data));
        }
    }

    /**
     * EE bug: replace_tag_catchall doesn't seem to work with conditionals
     * e.g. {if product_details:on_sale}
     */
    public function replace_on_sale($data)
    {
        if (isset($data['on_sale'])) {
            return $data['on_sale'];
        }
    }

    public function replace_tag_catchall($data, $params = array(), $tagdata = false, $modifier)
    {
        if (isset($data[$modifier])) {
            return $data[$modifier];
        }
    }

    public function load_settings($data)
    {
        $settings = array();
        $settings['enable_custom_prices'] = empty($data['enable_custom_prices']) ? false : true;
        $settings['enable_custom_weights'] = empty($data['enable_custom_weights']) ? false : true;

        return $settings;
    }

    /**
     * Display Settings Screen
     *
     * @access	public
     * @return default global settings
     *
     */
    public function display_settings($data)
    {
        $data['settings'] = $this->load_settings($data);

        return ee()->load->view('field_settings', $data, true);
    }

    /**
     * Save Settings
     *
     * @access	public
     * @return field settings
     *
     */
    public function save_settings($data)
    {
        $settings = ee()->input->post('store', true);

        $settings['field_fmt'] = 'none';
        $settings['field_show_fmt'] = 'n';
        $settings['field_type'] = 'store';

        return $settings;
    }

    /**
     * Support Entry API v3
     */
    public function entry_api_pre_process($data = null, $free_access = false, $entry_id = 0)
    {
        $product = Product::with(array(
            'modifiers' => function($query) { $query->orderBy('mod_order'); },
            'modifiers.options' => function($query) { $query->orderBy('opt_order'); },
            'stock',
        ))->whereNotNull('price')
        ->find($entry_id);

        if (empty($product)) return;

        ee()->store->products->apply_sales($product);

        // ew, gross
        ee()->load->helper('form');

        return $product->toTagArray();
    }
}
