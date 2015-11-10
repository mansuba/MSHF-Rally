<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Tag;

use Illuminate\Database\Eloquent\Builder;
use Store\Model\Order;

abstract class AbstractTag
{
    protected $ee;
    protected $tagdata;
    protected $params;

    public function __construct($ee, $tagdata, $params)
    {
        $this->ee = $ee;
        $this->tagdata = $tagdata;
        $this->params = $params ?: array();
    }

    abstract public function parse();

    public function param($key)
    {
        if (isset($this->params[$key])) {
            // consistent yes/no parameters
            switch ($this->params[$key]) {
                case 'y':
                case 'on':
                    return 'yes';
                    break;
                case 'n':
                case 'off':
                    return 'no';
                    break;
            }

            return $this->params[$key];
        }

        // not set
        return false;
    }

    public function parse_variables(array $tag_vars)
    {
        return $this->ee->TMPL->parse_variables($this->tagdata, $tag_vars);
    }

    protected function tmpl_secure_check($use_global = true)
    {
        if ($this->param('secure') == 'yes' OR
            ($use_global AND config_item('store_secure_template_tags')))
        {
            if ($this->ee->store->store->secure_request()) {
                // connection is secure - good. make sure form submissions are secure too
                $this->params['secure_action'] = 'yes';
                $this->params['secure_return'] = 'yes';
            } else {
                $this->ee->functions->redirect(str_replace('http://', 'https://', $this->ee->functions->fetch_current_uri()));
            }
        }
    }

    /**
     * Standard form opening tag
     */
    protected function form_open($action, $hidden_fields = array(), $extra_html = array())
    {
        $defaults = array(
            'method' => 'post',
            'id' => $this->param('form_id'),
            'name' => $this->param('form_name'),
            'class' => '',
            'enctype' => '',
        );

        $data = array_merge($defaults, $extra_html, $this->html_params());

        // class gets appended
        $data['class'] = trim($data['class'].' '.$this->param('form_class'));

        $hidden_fields['ACT'] = $this->ee->functions->fetch_action_id('Store', $action);
        $hidden_fields['RET'] = $this->ee->uri->uri_string;
        $hidden_fields['site_id'] = config_item('site_id');

        // prevents errors in case there are no tag params
        $this->params['encrypted_params'] = 1;

        $this->ee->load->library('encrypt');
        $hidden_fields['_params'] = $this->ee->encrypt->encode(json_encode($this->params));

        if (config_item('secure_forms') == 'y') {
            $hidden_fields['XID'] = '{XID_HASH}';
        }

        if ($this->param('secure_return') == 'yes') {
            $hidden_fields['secure_return'] = 1;
        }

        // Add the CSRF Protection Hash
        if (config_item('csrf_protection') == true) {
            $hidden_fields[$this->ee->security->get_csrf_token_name()] = $this->ee->security->get_csrf_hash();
        }

        if ($data['enctype'] == 'multi' OR strtolower($data['enctype']) == 'multipart/form-data') {
            $data['enctype'] = 'multipart/form-data';
        }

        return $this->build_form($data, $hidden_fields);
    }

    protected function build_form($attributes, array $hidden_fields = array())
    {
        $out = '<form ';

        foreach ($attributes as $key => $value) {
            if ($value !== '' && $value !== false) {
                $out .= htmlspecialchars($key).'="'.htmlspecialchars($value).'" ';
            }
        }

        $out .= ">\n<div style=\"margin:0;padding:0;display:inline;\">\n";

        foreach ($hidden_fields as $key => $value) {
            $out .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'" />'."\n";
        }

        $out .= "</div>\n\n";

        return $out;
    }

    /**
     * Fetch custom html:tag="value" parameters
     */
    protected function html_params()
    {
        $data = array();
        foreach ($this->params as $key => $value) {
            if (strpos($key, 'html:') === 0) {
                $data[substr($key, 5)] = $value;
            }
        }

        return $data;
    }

    protected function async_store_js()
    {
        $theme_url = $this->ee->store->config->asset_url('store.js');
        $theme_url = str_ireplace('http://', '//', $theme_url);

        return '
            (function() {
                ExpressoStore.config = '.$this->ee->store->config->config_json().';
                if (!ExpressoStore.scriptElement) {
                    var script = ExpressoStore.scriptElement = document.createElement("script");
                    script.type = "text/javascript"; script.async = true;
                    script.src = "'.$theme_url.'";
                    document.getElementsByTagName("body")[0].appendChild(script);
                }
            })();';
    }

    protected function get_orders_query()
    {
        $query = Order::with('items')
            ->where('site_id', config_item('site_id'))
            ->where('order_completed_date', '>', 0);

        $this->query_and_or_param($query, 'id', $this->param('order_id'));
        $this->query_and_or_param($query, 'order_status_name', $this->param('order_status'));

        if (false !== ($member_id = $this->param('member_id'))) {
            if ('CURRENT_USER' === $member_id) {
                $member_id = $this->ee->session->userdata['member_id'];
            }
            $this->query_and_or_param($query, 'member_id', $member_id);
        }

        if (false !== ($order_hash = $this->param('order_hash'))) {
            $query->where('order_hash', $order_hash);
        }

        $paid = $this->param('paid');
        if ('yes' == $paid) {
            $query->where('order_paid', '>=', $this->ee->store->db->raw('`order_total`'));
        } elseif ('no' == $paid) {
            $query->where('order_paid', '<', $this->ee->store->db->raw('`order_total`'));
        }

        $orderBy = $this->param('orderby');
        $sort = strtolower($this->param('sort')) == 'desc' ? 'desc' : 'asc';
        if (empty($orderBy) || $orderBy == 'order_id') {
            $query->orderBy('id', $sort);
        } elseif ('random' === strtolower($orderBy)) {
            $query->orderBy($this->ee->store->db->raw('RAND()'));
        } else {
            $query->orderBy($orderBy, $sort);
        }

        $limit = (int) $this->param('limit');
        $offset = (int) $this->param('offset');
        $query->take($limit ?: 100)->skip($offset);

        return $query;
    }

    /**
     * Build a query based on tag parameters
     *
     * Supports both "3|4|5" and "not 3|4|5" formats
     * Similar to EE_Functions::sql_andor_string() except it works with Laravel query builder
     */
    protected function query_and_or_param(Builder $query, $field, $value)
    {
        // was the parameter specified
        $value = trim($value);
        if ('' === $value) return;

        // is this a negative match?
        if ($not = (0 === stripos($value, 'not '))) {
            $value = trim(substr($value, 3));
        }

        // add to query builder
        if (false === strpos($value, '|')) {
            // standard where
            $operator = $not ? '!=' : '=';
            $query->where($field, $operator, $value);
        } else {
            // where in query
            $value = explode('|', $value);
            $query->whereIn($field, $value, 'and', $not);
        }
    }

    /**
     * Returns the portion of tagdata found between the specified {if no_results} tags,
     * or returns false if no tag exists
     */
    public function no_results($tag_name)
    {
        if ( ! empty($tag_name) AND strpos($this->tagdata, 'if '.$tag_name) !== false AND
            preg_match('/'.LD.'if '.$tag_name.RD.'(.*?)'.LD.'\/if'.RD.'/s', $this->tagdata, $match))
        {
            // currently this won't handle nested conditional statements.. lame
            return $match[1];
        } else {
            return false;
        }
    }

    /**
     * Insert Google Analytics tracking data if order has recently been completed
     * We use cookies to ensure this only happens once per order
     */
    protected function track_conversion($tag_vars)
    {
        $order_hash = $this->ee->input->cookie('store_cart_submit');
        if (empty($order_hash)) return;

        // does the current page actually contain the submitted order?
        $order = false;
        foreach ($tag_vars as $tag_var) {
            if ($tag_var['order_hash'] == $order_hash) {
                // user has just completed the order, this must be an "order completed" page
                $order = $tag_var;
            }
        }

        if (empty($order)) return;

        $out = '';

        if (config_item('store_google_analytics_ecommerce')) {
            $out .= "\n<script type='text/javascript'>\n";
            $out .= $this->track_google_analytics($order);
            $out .= "</script>";
        }

        $conversion_tracking_extra = config_item('store_conversion_tracking_extra');
        if ($conversion_tracking_extra) {
            $out .= $this->ee->TMPL->parse_variables($conversion_tracking_extra, array($order));
        }

        // conversion tracking should only ever happen once per order, unset cookie
        $this->ee->input->delete_cookie('store_cart_submit');

        return $out;
    }

    protected function track_google_analytics($order)
    {
        $ga = array();
        $ga[] = array(
            '_addTrans',
            $order['order_id'],
            config_item('site_name'),
            sprintf("%0.2f", $order['order_total_val']),
            sprintf("%0.2f", $order['order_tax_val']),
            sprintf("%0.2f", $order['order_shipping_val']),
            $order['billing_city'],
            $order['billing_state_name'],
            $order['billing_country_name']);

        $analytics = array();
        $analytics[] = array('ecommerce:addTransaction', array(
            'id' => $order['order_id'],
            'affiliation' => config_item('site_name'),
            'revenue' => sprintf("%0.2f", $order['order_total_val']),
            'shipping' => sprintf("%0.2f", $order['order_shipping_val']),
            'tax' => sprintf("%0.2f", $order['order_tax_val']),
        ));

        // GA will only allow one entry per sku
        // we need to aggregate any items in cart which have the same sku
        // if there is no SKU we default to the entry_id
        $items = array();
        foreach ($order['items'] as $item) {
            $sku = $item['sku'] ?: $item['entry_id'];
            if (isset($items[$sku])) {
                // sku exists, just increase quantity
                $items[$sku]['item_qty'] += $item['item_qty'];
            } else {
                $items[$sku] = $item;
            }
        }

        foreach ($items as $sku => $item) {
            $ga[] = array(
                '_addItem',
                $order['order_id'],
                $sku,
                $item['title'],
                '',
                sprintf("%0.2f", $item['price_val']),
                $item['item_qty'],
            );

            $analytics[] = array('ecommerce:addItem', array(
                'id' => $order['order_id'],
                'name' => $item['title'],
                'sku' => $sku,
                'price' => sprintf("%0.2f", $item['price_val']),
                'quantity' => $item['item_qty'],
            ));
        }

        // google analytics legacy
        $out = "var _gaq = _gaq || [];\n";
        $ga[] = array('_trackTrans');
        foreach ($ga as $command) {
            $out .= "_gaq.push(".json_encode($command).");\n";
        }

        // universal analytics
        $out .= "if (typeof ga !== 'undefined') {\n";
        $out .= "    ga('require', 'ecommerce', 'ecommerce.js');\n";
        foreach ($analytics as $command) {
            $out .= "    ga(".json_encode($command[0]).", ".json_encode($command[1]).");\n";
        }
        $out .= "    ga('ecommerce:send');\n";
        $out .= "}\n";

        return $out;
    }

    protected function add_payment_tag_vars(&$tag_vars)
    {
        // ensure we have an error:payment_method
        if ( ! isset($tag_vars[0]['error:payment_method'])) {
            $tag_vars[0]['error:payment_method'] = false;
        }

        if (($payment_error = $this->ee->session->flashdata('store_payment_error')) !== false) {
            $tag_vars[0]['error:payment_method'] = $this->wrap_error($payment_error);

            // support deprecated payment_status variable
            $tag_vars[0]['payment_status'] = 'failed';
            $tag_vars[0]['payment_message'] = $payment_error;
        }

        // payment_method_options variable for lazy people
        $tag_vars[0]['payment_method_options'] = '';
        if (strpos($this->tagdata, '{payment_method_options}') !== false) {
            if (($payment_method = $this->param('payment_method')) == false) {
                $payment_method = $tag_vars[0]['payment_method'];
            }

            $tag_vars[0]['payment_method_options'] = $this->ee->store->payments->get_enabled_payment_method_options($payment_method);
        }

        $tag_vars[0]['field:payment_method'] = '<select id="payment_method" name="payment_method">'.$tag_vars[0]['payment_method_options'].'</select>';

        $tag_vars[0]['exp_month_options'] = $this->exp_month_options();
        $tag_vars[0]['exp_year_options'] = $this->exp_year_options();

        // work around crazy EE bug rewriting exp_ to database prefix
        $tag_vars[0]['expiry_month_options'] =& $tag_vars[0]['exp_month_options'];
        $tag_vars[0]['expiry_year_options'] =& $tag_vars[0]['exp_year_options'];

        if (preg_match_all('/\{(\w+):issuer_options\}/', $this->tagdata, $issuer_matches)) {
            foreach ($issuer_matches[1] as $gateway) {
                $tag_vars[0][$gateway.':issuer_options'] = $this->ee->store->payments->fetch_issuer_options($gateway);
            }
        }
    }

    /**
     * Generate a list of expiry month <option> elements
     */
    public function exp_month_options()
    {
        $out = '';
        for ($i = 1; $i <= 12; $i++) {
            $out .= '<option value="'.sprintf('%02d', $i).'">'.sprintf('%02d', $i).'</option>';
        }

        return $out;
    }

    /**
     * Generate a list of expiry year <options> elements
     */
    public function exp_year_options()
    {
        $out = '';
        for ($i = gmdate('Y'); $i <= (gmdate('Y') + 9); $i++) {
            $out .= '<option value="'.$i.'">'.$i.'</option>';
        }

        return $out;
    }

    /**
     * Wrap an error message with delimiters specified in the template
     */
    protected function wrap_error($message)
    {
        if (empty($message)) return false;

        $error_delimiters = explode('|', $this->param('error_delimiters'));

        if (count($error_delimiters) == 2) {
            return $error_delimiters[0].$message.$error_delimiters[1];
        }

        return $message;
    }
}
