<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class Stock extends AbstractModel
{
    protected $table = 'store_stock';
    protected $fillable = array('sku', 'stock_level', 'min_order_qty', 'track_stock');

    public function product()
    {
        return $this->belongsTo('\Store\Model\Product', 'entry_id');
    }

    public function stockOptions()
    {
        return $this->hasMany('\Store\Model\StockOption');
    }

    public function setMinOrderQtyAttribute($value)
    {
        $this->attributes['min_order_qty'] = $value === '' ? null : $value;
    }

    public function setStockLevelAttribute($value)
    {
        $this->attributes['stock_level'] = $value === '' ? null : $value;
    }

    public function getOptValuesAttribute()
    {
        $values = array();

        foreach ($this->stock_options as $opt) {
            $values[$opt->product_mod_id] = $opt->product_opt_id;
        }

        return $values;
    }

    public static function findByModifiers($entry_id, $modifiers)
    {
        $results = static::with('stockOptions')->where('entry_id', $entry_id)->get();

        foreach ($results as $stock) {
            // test whether all common modifiers match
            $values = array_intersect_key($modifiers, $stock->opt_values);

            if ($values == $stock->opt_values) {
                return $stock;
            }
        }
    }

    public function toArray()
    {
        $attributes = parent::toArray();

        // required for store.js dynamic css variables
        $attributes['opt_values'] = $this->opt_values;

        return $attributes;
    }
}
