<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Tag;

use Store\Action\CheckoutAction;

class CheckoutTag extends AbstractTag
{
    public function parse()
    {
        $this->tmpl_secure_check();

        $cart = $this->ee->store->orders->get_cart();

        // redisplay submitted form data
        $cart->fill($this->ee->security->xss_clean($_POST));
        $tag_vars = array($cart->toTagArray());

        $order_fields = $this->ee->store->config->order_fields();

        // legacy fields available for errors and text fields
        $order_fields['billing_name']       = array();
        $order_fields['billing_address3']   = array();
        $order_fields['billing_region']     = array();
        $order_fields['shipping_name']      = array();
        $order_fields['shipping_address3']  = array();
        $order_fields['shipping_region']    = array();

        // blank out inline error fields
        $error_fields = array_merge(array_keys($order_fields), array('promo_code',
            'accept_terms', 'register_member', 'shipping_method', 'shipping_same_as_billing',
            'billing_same_as_shipping', 'username', 'screen_name', 'password', 'password_confirm'));
        foreach ($error_fields as $key) {
            $tag_vars[0]["error:$key"] = false;
        }

        // add form validation errors
        $tag_vars[0]['field_errors'] = array();
        if (is_array(CheckoutAction::$form_errors)) {
            foreach (CheckoutAction::$form_errors as $key => $message) {
                $tag_vars[0]["error:$key"] = $this->wrap_error($message);
                $tag_vars[0]['field_errors'][] = array('error' => $this->wrap_error($message));
            }
        }
        $tag_vars[0]['field_errors:count'] = count($tag_vars[0]['field_errors']);

        // check for empty cart
        if ($cart->isEmpty()) {
            return $this->no_results('no_items');
        }

        // load available shipping & payment methods
        $tag_vars[0]['shipping_methods'] = array();
        $tag_vars[0]['shipping_method_options'] = '';

        $shipping_methods = $this->ee->store->orders->get_order_shipping_methods($cart);
        foreach ($shipping_methods as $method) {
            $tag_vars[0]['shipping_methods'][] = array_merge(
                $method->toTagArray(),
                array('method_selected' => $method->id == $cart->shipping_method)
            );
            $selected_str = $method->id == $cart->shipping_method ? 'selected' : '';
            $tag_vars[0]['shipping_method_options'] .= "<option value='{$method->id}' $selected_str>{$method->name}</option>";
        }

        // load available countries and regions
        $tag_vars[0]['billing_country_options'] = $this->ee->store->shipping->get_enabled_country_options($tag_vars[0]['billing_country']);
        $tag_vars[0]['shipping_country_options'] = $this->ee->store->shipping->get_enabled_country_options($tag_vars[0]['shipping_country']);
        $tag_vars[0]['billing_state_options'] = $this->ee->store->shipping->get_enabled_state_options($tag_vars[0]['billing_country'], $tag_vars[0]['billing_state']);
        $tag_vars[0]['shipping_state_options'] = $this->ee->store->shipping->get_enabled_state_options($tag_vars[0]['shipping_country'], $tag_vars[0]['shipping_state']);
        $tag_vars[0]['billing_region_options'] =& $tag_vars[0]['billing_state_options'];
        $tag_vars[0]['shipping_region_options'] =& $tag_vars[0]['shipping_state_options'];

        // helper variables for checkboxes
        $tag_vars[0]['shipping_same_as_billing_checked'] = $tag_vars[0]['shipping_same_as_billing'] ? 'checked="checked"' : null;
        $tag_vars[0]['billing_same_as_shipping_checked'] = $tag_vars[0]['billing_same_as_shipping'] ? 'checked="checked"' : null;
        $tag_vars[0]['accept_terms_checked'] = $tag_vars[0]['accept_terms'] ? 'checked="checked"' : null;
        $tag_vars[0]['register_member_checked'] = $tag_vars[0]['register_member'] ? 'checked="checked"' : null;

        // form input helpers
        $text_inputs = array_merge(array_keys($order_fields),
            array('promo_code', 'username', 'screen_name', 'password', 'password_confirm'));
        foreach ($text_inputs as $field_name) {
            $field_type = 'text';
            if ($field_name == 'order_email') {
                $field_type = 'email';
            }
            if (strpos($field_name, 'password') === 0) {
                $field_type = 'password';
            }
            $tag_vars[0]['field:'.$field_name] = '<input type="'.$field_type.'" '.
                'id="'.$field_name.'" name="'.$field_name.'" value="'.$tag_vars[0][$field_name].'" />';
        }

        // select inputs
        foreach (array('billing_region', 'billing_state', 'billing_country', 'shipping_region', 'shipping_state', 'shipping_country', 'shipping_method') as $field_name) {
            $tag_vars[0]['field:'.$field_name] = '<select id="'.$field_name.'" name="'.$field_name.'">'.$tag_vars[0][$field_name.'_options'].'</select>';
        }

        // hidden inputs
        foreach (array('shipping_same_as_billing', 'billing_same_as_shipping', 'accept_terms', 'register_member') as $field_name) {
            $tag_vars[0]['field:'.$field_name] = '<input type="hidden" name="'.$field_name.'" value="0" />'.
                '<input type="checkbox" id="'.$field_name.'" name="'.$field_name.'" value="1" '.$tag_vars[0][$field_name.'_checked'].' />';
        }

        $out = '';

        if ($this->param('disable_javascript') != 'yes') {
            // store regions array as js array
            $out .= '<script type="text/javascript">
                window.ExpressoStore = window.ExpressoStore || {};
                ExpressoStore.countries = '.$this->ee->store->shipping->get_countries_json().';
                '.$this->async_store_js().'
            </script>';
        }

        $hidden_fields = array(
            'return_url' => $this->ee->uri->uri_string,
        );

        $this->add_payment_tag_vars($tag_vars);
        if (($payment_method = $this->param('payment_method')) !== false) {
            $hidden_fields['payment_method'] = $payment_method;
        }

        if ($this->param('register_member') == 'yes') {
            $hidden_fields['register_member'] = 1;
        }

        // previous_url variable helpful for a "continue shopping" link
        $tag_vars[0]['previous_url'] = isset($this->ee->session->tracker[1]) ?
            $this->ee->functions->create_url($this->ee->session->tracker[1]) : false;

        foreach (array('next', 'return') as $param) {
            if ($this->param($param)) {
                $hidden_fields[$param.'_url'] = $this->param($param);
            }
        }

        // start our form output
        $out .= $this->form_open('act_checkout', $hidden_fields);

        // parse tagdata variables
        $out .= $this->parse_variables($tag_vars);

        // end form output and return
        $out .= '</form>';

        return $out;
    }
}
