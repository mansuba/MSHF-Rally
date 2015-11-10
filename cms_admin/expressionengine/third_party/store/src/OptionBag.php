<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use ArrayAccess;
use Store\DateTime;

class OptionBag implements ArrayAccess
{
    protected $options;
    protected $values;

    /**
     * Create a new OptionBag
     *
     * @param array $options An array of configuration options
     * @param array|null $values An array of initial values
     */
    public function __construct(array $options, $values = array())
    {
        $this->configure($options);
        $this->replace($values);
    }

    protected function configure(array $options)
    {
        $this->options = array();

        foreach ($options as $key => $option) {
            if (!is_array($option)) {
                $option = array('type' => 'text', 'default' => $option);
            }

            $this->configure_option($key, $option);
        }
    }

    protected function configure_option($key, array $option)
    {
        // default parameters
        $option = array_merge(array('default' => null), $option);

        if ($option['type'] === 'month_select') {
            // automatic options list
            $option['options'] = ee()->store->reports->month_select_options();

            // default value may be specified as integer offset
            if (is_int($option['default']) && $option['default'] < 0) {
                $option_keys = array_keys($option['options']);
                $default_offset = max(0, count($option_keys) + $option['default']);
                $option['default'] = $option_keys[$default_offset];
            }
        } elseif ($option['type'] === 'category_select') {
            // automatic categories list
            $option['options'] = array('' => lang('store.any'));
            $option['options'] += ee()->store->products->get_categories();
        }

        $this->options[$key] = $option;
    }

    /**
     * Get all option values as an associative array
     */
    public function all()
    {
        return $this->values;
    }

    /**
     * Get all option keys
     */
    public function keys()
    {
        return array_keys($this->options);
    }

    /**
     * Replace all options
     *
     * @param array|null $values An array of values
     */
    public function replace($values)
    {
        // initialize default values
        $this->values = array();
        foreach ($this->options as $key => $value) {
            $this->set($key, $this->def($key));
        }

        // set new values
        foreach ((array) $values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Get an option
     */
    public function get($key)
    {
        // intentionally using $this->values here in case the value hasn't been set yet
        if (isset($this->values[$key])) {
            $value = $this->values[$key];

            // date_select automatically returns DateTime
            if ($value && $this->options[$key]['type'] === 'date_select') {
                return DateTime::createFromFormat('Y-m-d', $value, ee()->store->reports->timezone())->startOfDay();
            }

            return $value;
        }
    }

    /**
     * Set an option
     */
    public function set($key, $value)
    {
        if ($this->has($key)) {
            $this->values[$key] = $value;
        }
    }

    /**
     * Detect whether the bag is allowed to contain a specific option
     */
    public function has($key)
    {
        return isset($this->options[$key]);
    }

    /**
     * Fetch the default value for an option (stupid PHP reserved keywords).
     */
    public function def($key)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key]['default'];
        }
    }

    public function offsetExists($key)
    {
        return $this->has($key);
    }

    public function offsetGet($key)
    {
        return $this->get($key);
    }

    public function offsetSet($key, $value)
    {
        return $this->set($key, $value);
    }

    public function offsetUnset($key)
    {
        return $this->set($key, null);
    }

    public function input($key)
    {
        $attributes = $this->options[$key];
        unset($attributes['default']);

        $attributes['name'] = "options[$key]";
        $attributes['id'] = "options_$key";
        $value = $this->get($key);

        switch ($attributes['type']) {
            case 'textarea':
                return store_html_elem('textarea', $attributes, $value);
            case 'select':
            case 'month_select':
            case 'category_select':
                $content = store_select_options($attributes['options'], $value);
                unset($attributes['options']);

                return store_html_elem('select', $attributes, $content, true);
            case 'date_select':
                $attributes['class'] = array_get($attributes, 'class').' store_date';
                $attributes['type'] = 'text';
                $attributes['value'] = $value ? $value->format('Y-m-d') : null;

                return store_html_elem('input', $attributes);
            default:
                $attributes['value'] = $value;

                return store_html_elem('input', $attributes);
        }
    }
}
