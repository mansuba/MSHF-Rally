<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

use Store\FormBuilder;
use Store\Model\Order;
use Store\Model\Transaction;

if (!function_exists('store_lang_exists')) {
    /**
     * Check whether a given language key exists
     */
    function store_lang_exists($key)
    {
        return isset(ee()->lang->language[$key]);
    }
}

if (!function_exists('store_form')) {
    /**
     * Create a new form builder
     */
    function store_form($model = null, $prefix = null)
    {
        return new FormBuilder($model, $prefix);
    }
}

if (!function_exists('store_currency')) {
    /**
     * Format a currency
     */
    function store_currency($value, $force_sign = false)
    {
        if (null === $value) {
            return null;
        }

        $output = config_item('store_currency_symbol').
            number_format(abs($value),
                (int) config_item('store_currency_decimals'),
                config_item('store_currency_dec_point'),
                config_item('store_currency_thousands_sep')).
            config_item('store_currency_suffix');

        if ($force_sign) {
            return $value < 0 ? '-'.$output : '+'.$output;
        } else {
            return $value < 0 ? '-'.$output : $output;
        }
    }
}

if (!function_exists('store_currency_cp')) {
    /**
     * Format a currency for use in the control panel (no currency symbol)
     */
    function store_currency_cp($value, $force_sign = false)
    {
        if (null === $value) {
            return null;
        }

        $output = number_format(abs($value),
                (int) config_item('store_currency_decimals'),
                config_item('store_currency_dec_point'),
                config_item('store_currency_thousands_sep'));

        if ($force_sign) {
            return $value < 0 ? '-'.$output : '+'.$output;
        } else {
            return $value < 0 ? '-'.$output : $output;
        }
    }
}

if (!function_exists('store_decimal')) {
    /**
     * Localize a decimal
     */
    function store_decimal($value)
    {
        if (null === $value) {
            return null;
        }

        return str_replace('.', config_item('store_currency_dec_point'), (float) $value);
    }
}

if (!function_exists('store_parse_decimal')) {
    /**
     * Parse a localized decimal or currency
     */
    function store_parse_decimal($value)
    {
        $point = config_item('store_currency_dec_point');
        $cleaned_value = preg_replace('/[^0-9\-'.preg_quote($point, '/').']+/', '', $value);

        return str_replace($point, '.', $cleaned_value);
    }
}

if (!function_exists('store_enabled_str')) {
    /**
     * Lazy way to return a localised 'Enabled' or 'Disabled' string in view files
     */
    function store_enabled_str($enabled)
    {
        if (empty($enabled) OR strtolower($enabled) == 'n') {
            return '<span class="notice">'.lang('store.disabled').'</span>';
        }

        return '<span class="go_notice">'.lang('store.enabled').'</span>';
    }
}

if (!function_exists('store_round_currency')) {
    /**
     * Round a decimal to the correct number of decimal places
     *
     * @param float $number
     * @param bool  $allow_negative
     */
    function store_round_currency($number, $allow_negative = false)
    {
        $number = (float) $number;
        $decimals = (int) config_item('store_currency_decimals');

        if ($allow_negative) {
            return round($number, $decimals);
        }

        return max(0, round($number, $decimals));
    }
}

if (!function_exists('store_setting_input')) {
    /**
     * Generate an input field for settings using our config array format
     */
    function store_setting_input($key, $default, $value)
    {
        $input_name = "settings[$key]";
        $extra_attrs = 'id="settings_'.$key.'"';

        if ($key == 'password') {
            $extra_attrs .= ' autocomplete="off"';
        }

        if (is_bool($default)) {
            if ($value === true) $value = 'y';
            return form_dropdown($input_name, array('y' => lang('store.true'), '' => lang('store.false')), $value, $extra_attrs);
        }

        if (is_array($default)) {
            // suppert non-associative array of options
            if (isset($default[0])) {
                $options = array();
                foreach ($default as $key) {
                    $options[$key] = $key;
                }
                $default = array('type' => 'select', 'options' => $options);
            }

            if (empty($default['type'])) {
                throw new InvalidArgumentException('Missing setting type in default setting array');
            }

            switch ($default['type']) {
                case 'select':
                    if (empty($default['options'])) {
                        throw new InvalidArgumentException('Missing setting options in default setting array');
                    }

                    // run options through lang()
                    foreach ($default['options'] as $opt_value => $opt_title) {
                        $default['options'][$opt_value] = lang($opt_title);
                    }

                    return form_dropdown($input_name, $default['options'], $value, $extra_attrs);
                case 'textarea':
                    return form_textarea($input_name, $value, $extra_attrs);
                case 'password':
                    return form_password($input_name, $value, $extra_attrs);
                default:
                    throw new InvalidArgumentException('Invalid setting type "'.$default['type'].'" in default setting array');
            }
        }

        // default is just a plain text input
        return form_input($input_name, $value, $extra_attrs);
    }
}

if (!function_exists('store_setting_default')) {
    /**
     * Get the default value for settings using our config array format
     */
    function store_setting_default($setting)
    {
        if (is_array($setting)) {
            return isset($setting['default']) ? $setting['default'] : null;
        }

        return $setting;
    }
}

if (!function_exists('store_form_checkbox')) {
    function store_form_checkbox($name, $checked, $options = array())
    {
        $checkbox = array(
            'id' => trim(preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_'),
            'name' => $name,
            'value' => '1',
            'checked' => (bool) $checked,
        );

        if ( ! empty($options['disabled'])) {
            $checkbox['disabled'] = true;
        }

        return form_hidden($name, '0')."\n".form_checkbox($checkbox);
    }
}

if (!function_exists('store_select_options')) {
    function store_select_options($options, $selected = null)
    {
        $html = array();

        foreach ($options as $key => $value) {
            $option = '<option value="'.e($key).'"';
            if (null !== $selected && $key == $selected) {
                $option .= ' selected';
            }
            $option .= '>'.e($value).'</option>';

            $html[] = $option;
        }

        return implode("\n", $html);
    }
}

if (!function_exists('store_transaction_status')) {
    function store_transaction_status($payment_status)
    {
        return '<span class="store_transaction_'.$payment_status.'">'.
            lang('store.transaction_'.$payment_status).'</span>';
    }
}

if (!function_exists('store_member_link')) {
    function store_member_link($member, $anonymousTitle = '')
    {
        if (empty($member)) {
            return $anonymousTitle;
        } else {
            return '<a href="'.BASE.AMP.'C=myaccount'.AMP.'id='.$member->member_id.'">'.$member->screen_name.'</a>';
        }
    }
}

if (!function_exists('store_email_template_name')) {
    /**
     * Generate localized email template name
     */
    function store_email_template_name($name)
    {
        if (in_array($name, array('order_confirmation'))) {
            return lang('store.email.'.$name);
        }

        return $name;
    }
}

if (!function_exists('store_order_paid')) {
    /**
     * Generate pretty html to display whether order is paid
     */
    function store_order_paid(Order $order)
    {
        if ($order->order_owing < 0) {
            return '<span class="store_order_paid_over">'.lang('store.overpaid').'</span>';
        } elseif ($order->order_owing == 0) {
            return '<span class="store_order_paid_yes">'.lang('yes').'</span>';
        } elseif ($order->order_paid > 0) {
            return store_currency($order->order_paid);
        } else {
            return lang('no');
        }
    }
}

if (!function_exists('store_order_status_name')) {
    /**
     * Generate localized order status name
     */
    function store_order_status_name($status)
    {
        if ('new' === $status) {
            return lang('store.new');
        }

        return $status;
    }
}

if (!function_exists('store_order_status')) {
    /**
     * Generate pretty html to display order status
     */
    function store_order_status(Order $order)
    {
        if ($order->order_completed_date) {
            $statuses = ee()->store->orders->order_statuses();
            $style = isset($statuses[$order->order_status_name]) ? 'color:'.$statuses[$order->order_status_name]->color : '';

            return '<span style="'.$style.'">'.store_order_status_name($order->order_status_name).'</span>';
        } else {
            return '<span class="store_order_status_incomplete">'.lang('store.incomplete').'</span>';
        }
    }
}

if (!function_exists('store_format_indicator')) {
    function store_format_indicator($val1, $val2)
    {
        if (!empty($val2)) {
            $change = ($val1 - $val2) / $val2 * 100;
            $str = $change >= 0 ? '<span class="positive">&#9650; ' : '<span class="negative">&#9660; ';
            $str .= sprintf('%.2f', $change).'%</span>';

            return $str;
        }
    }
}

if (!function_exists('store_transaction_actions')) {
    function store_transaction_actions(Transaction $transaction)
    {
        $url = STORE_CP.'&amp;sc=orders&amp;id='.$transaction->id.'&amp;sm=';

        if ($transaction->canCapture()) {
            return form_open($url.'capture_transaction').
                form_submit(array(
                    'value' => lang('store.capture_transaction'),
                    'data-store-confirm' => lang('store.capture_transaction_confirm'),
                )).
                form_close();
        }

        if ($transaction->canRefund()) {
            return form_open($url.'refund_transaction').
                form_submit(array(
                    'value' => lang('store.refund_transaction'),
                    'data-store-confirm' => lang('store.refund_transaction_confirm'),
                )).
                form_close();
        }
    }
}
