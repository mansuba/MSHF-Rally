<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class AbstractModel extends EloquentModel
{
    /**
     * We don't use Eloquent timestamps
     */
    public $timestamps = false;

    /**
     * Specify attributes which will be formatted as currency in toArray() method
     */
    protected $currency_attributes;

    /**
     * Decimal attributes are assumed to be localized when mass assigned
     */
    protected $decimal_attributes = array();

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array                               $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function fill(array $attributes)
    {
        // parse decimals
        foreach ($this->decimal_attributes as $key) {
            if (isset($attributes[$key])) {
                if (null === $attributes[$key] || '' === $attributes[$key]) {
                    $attributes[$key] = null;
                } else {
                    $attributes[$key] = store_parse_decimal($attributes[$key]);
                }
            }
        }

        parent::fill($attributes);
    }

    public function newCollection(array $models = array())
    {
        return new Collection($models);
    }

    /**
     * Convert the record to a template tag variables array
     *
     * @return array
     */
    public function toTagArray()
    {
        $attributes = $this->attributesToArray();

        if (is_array($this->currency_attributes)) {
            foreach ($this->currency_attributes as $key) {
                // ensure attribute exists
                if ( ! isset($attributes[$key])) {
                    $attributes[$key] = $this->$key;
                }

                // convert to currency format
                $attributes[$key.'_val'] = (float) $attributes[$key];
                $attributes[$key] = store_currency($attributes[$key]);
            }
        }

        return $attributes;
    }

    protected function setBooleanAttribute($name, $value)
    {
        $this->attributes[$name] = $value == 'y' ? 1 : (int) $value;
    }

    protected function getUnixTimeAttribute($name)
    {
        $value = empty($this->attributes[$name]) ? null : $this->attributes[$name];

        return $value ? ee()->localize->human_time($value) : null;
    }

    protected function setUnixTimeAttribute($name, $value)
    {
        $this->attributes[$name] = $value ? ee()->localize->string_to_timestamp($value) : null;
    }

    protected function getPipeArrayAttribute($name)
    {
        return empty($this->attributes[$name]) ? array() : explode('|', $this->attributes[$name]);
    }

    protected function setPipeArrayAttribute($name, $value)
    {
        $value = implode('|', array_filter((array) $value, function($x) {
            return $x !== null && $x !== '';
        }));

        $this->attributes[$name] = $value === '' ? null : $value;
    }
}
