<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use Illuminate\Database\Eloquent\Model;

class FormBuilder
{
    public $model;
    public $prefix;

    public function __construct($model = null, $prefix = null)
    {
        $this->model = $model;
        $this->prefix = $prefix;

        if ($model) {
            $class_parts = explode('\\', get_class($model));
            $this->prefix = $prefix ?: snake_case(end($class_parts));
        }
    }

    /**
     * Create opening form tag
     */
    public function open(array $options = array())
    {
        $options = array_merge(array(
            'action' => ee()->store->request->getRequestUri(), // EE 2.8.0 throws JS errors if action is not present
            'method' => 'post',
        ), $options);

        $out = $this->elem('form', $options, null, false);
        $out .= "\n<div style='margin:0;padding:0;display:inline;'>";
        if (config_item('secure_forms') == 'y') {
            $out .= $this->elem('input', array(
                'type' => 'hidden',
                'name' => 'XID',
                'value' => XID_SECURE_HASH,
            ));
        }
        $out .= "</div>";

        return $out;
    }

    /**
     * Create closing form tag
     */
    public function close()
    {
        return '</form>';
    }

    /**
     * Create label
     */
    public function label($property, $name = null, array $options = array())
    {
        $options = array_merge(array(
            'for' => $this->id($property),
        ), $options);
        $subtext = null;

        if (null === $name) {
            // see if lang key exists for prefixed input name
            if (store_lang_exists('store.'.$options['for'])) {
                $name = 'store.'.$options['for'];
            } else {
                // fall back to input name without prefix
                $name = 'store.'.$property;
            }
        }

        if (store_lang_exists($name.'_subtext')) {
            $subtext = '<div class="subtext">'.$this->e(lang($name.'_subtext')).'</div>';
        }

        $content = $this->e(lang($name));

        if (!empty($options['required'])) {
            $content .= ' <em class="required">*</strong>';
            unset($options['required']);
        }

        return $this->elem('label', $options, $content).$subtext;
    }

    /**
     * Create text input
     */
    public function input($property, array $options = array())
    {
        $options = array_merge(array(
            'type' => 'text',
            'id' => $this->id($property),
            'name' => $this->name($property),
            'value' => $this->value($property),
        ), $options);

        return $this->elem('input', $options);
    }

    public function hidden($property, array $options = array())
    {
        $options['type'] = 'hidden';

        return $this->input($property, $options);
    }

    public function currency($property, array $options = array())
    {
        $options['value'] = store_currency_cp($this->value($property));

        return $this->input($property, $options);
    }

    public function decimal($property, array $options = array())
    {
        $options['value'] = store_decimal($this->value($property));

        return $this->input($property, $options);
    }

    public function percent($property, array $options = array())
    {
        $value = (float) $this->value($property);
        $options['value'] = $value ? $value.'%' : null;

        return $this->input($property, $options);
    }

    public function datetime($property, array $options = array())
    {
        $options['value'] = ee()->localize->human_time($this->value($property));
        $options['class'] = (isset($options['class']) ? $options['class'] : '').'store_datetime';

        return $this->input($property, $options);
    }

    /**
     * Create textarea
     */
    public function text($property, array $options = array())
    {
        $options = array_merge(array(
            'id' => $this->id($property),
            'name' => $this->name($property),
        ), $options);

        return $this->elem('textarea', $options, $this->e($this->value($property)));
    }

    /**
     * Create checkbox
     */
    public function checkbox($property, array $options = array())
    {
        $hidden = array();
        $hidden['type'] = 'hidden';
        $hidden['name'] = $this->name($property);
        $hidden['value'] = 0;

        $options = array_merge(array(
            'type' => 'checkbox',
            'id' => $this->id($property),
            'name' => $this->name($property),
            'value' => 1,
            'checked' => (bool) $this->value($property),
        ), $options);

        return $this->elem('input', $hidden).$this->elem('input', $options);
    }

    /**
     * Create select menu
     */
    public function select($property, $items, array $options = array())
    {
        $options = array_merge(array(
            'id' => $this->id($property),
            'name' => $this->name($property),
        ), $options);

        $html = '';

        if (!empty($options['multiple'])) {
            // add default select multiple size
            $options['name'] .= '[]';
            $options = array_merge(array(
                'size' => 6,
                'class' => 'store-multiselect',
            ), $options);

            // add blank hidden input to support selecting no items
            $html .= $this->elem('input', array(
                'type' => 'hidden',
                'name' => $options['name'],
                'value' => '',
            ));
        }

        if (isset($options['selected'])) {
            $selected = $options['selected'];
            unset($options['selected']);
        } else {
            $selected = $this->value($property);
        }

        if (is_array($items)) {
            $content = '';
            foreach ($items as $key => $value) {
                $opt = array();
                $opt['value'] = $key;
                if ($selected == $key) {
                    $opt['selected'] = true;
                }
                if (is_array($selected) && (in_array($key, $selected) || (empty($selected) && $key == ''))) {
                    $opt['selected'] = true;
                }

                $content .= $this->elem('option', $opt, $this->e($value))."\n";
            }
        } else {
            $content = $items;
        }

        return $html.$this->elem('select', $options, $content);
    }

    /**
     * Get property error message from CI form validation library
     */
    public function error($property)
    {
        return ee()->form_validation->error($this->name($property));
    }

    /**
     * Create a generic HTML element
     */
    public function elem($name, $options, $content = null, $close = true)
    {
        $html = '<'.$name;
        foreach ($options as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $html .= ' '.$this->e($key);
                }
            } else {
                $html .= ' '.$this->e($key).'="'.$this->e($value).'"';
            }
        }

        if (!$close) {
            // content will normally be null for open tag
            $html .= '>'.$content;
        } elseif (null === $content) {
            $html .= ' />';
        } else {
            // content must already be escaped
            $html .= '>'.$content.'</'.$name.'>';
        }

        return $html;
    }

    public function id($property)
    {
        return $this->prefix.'_'.$property;
    }

    public function name($property)
    {
        return $this->prefix.'['.$property.']';
    }

    public function value($property)
    {
        if ($this->model) {
            return $this->model->$property;
        }
    }

    /**
     * Escape HTML
     */
    public function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, null, false);
    }
}
