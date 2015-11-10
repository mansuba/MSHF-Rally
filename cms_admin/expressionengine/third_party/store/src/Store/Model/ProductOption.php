<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class ProductOption extends AbstractModel
{
    protected $table = 'store_product_options';
    protected $primaryKey = 'product_opt_id';
    protected $fillable = array('opt_name', 'opt_price_mod', 'opt_order');
    protected $decimal_attributes = array('opt_price_mod');
    protected $_sale_price_mod;

    public function modifier()
    {
        return $this->belongsTo('\Store\Model\ProductModifier', 'product_mod_id');
    }

    public function stockOptions()
    {
        return $this->hasMany('\Store\Model\StockOption', 'product_opt_id');
    }

    public function getSalePriceModAttribute()
    {
        return $this->_sale_price_mod;
    }

    public function setSalePriceModAttribute($value)
    {
        $this->_sale_price_mod = $value;
    }

    public function toArray()
    {
        $attributes = parent::toArray();

        unset($attributes['modifier']);

        $attributes['regular_price_mod'] = (float) $this->opt_price_mod;

        if (null === $this->sale_price_mod) {
            $attributes['opt_price_mod'] = (float) $this->opt_price_mod;
        } else {
            $attributes['opt_price_mod'] = (float) $this->sale_price_mod;
        }

        $attributes['sale_price_mod'] = $attributes['opt_price_mod'];

        return $attributes;
    }

    public function toTagArray()
    {
        $attributes = parent::toTagArray();
        $attributes['option_id'] = $this->product_opt_id;
        $attributes['option_name'] = $this->opt_name;

        if (null === $this->sale_price_mod) {
            $attributes['price_mod'] = (float) $this->opt_price_mod;
        } else {
            $attributes['price_mod'] = (float) $this->sale_price_mod;
        }

        $attributes['price_inc_mod'] = $attributes['price_mod'] + $this->modifier->product->price;
        $attributes['regular_price_mod'] = (float) $this->opt_price_mod;
        $attributes['regular_price_inc_mod'] = $attributes['regular_price_mod'] + $this->modifier->product->regular_price;
        $attributes['sale_price_mod'] = $attributes['price_mod'];
        $attributes['sale_price_inc_mod'] = $attributes['price_inc_mod'];

        $attributes['option_first'] = false;
        $attributes['option_last'] = false;

        $prices = array('price_mod', 'price_inc_mod', 'regular_price_mod',
            'regular_price_inc_mod', 'sale_price_mod', 'sale_price_inc_mod');
        foreach ($prices as $key) {
            $attributes[$key.'_val'] = $attributes[$key];
            $attributes[$key] = store_currency($attributes[$key]);
        }

        // these attributes only make sense if the product has a single modifier
        if (1 === count($this->stock_options)) {
            $attributes['option_sku'] = $this->stock_options[0]->sku;
            $attributes['option_track_stock'] = $this->stock_options[0]->stock->track_stock;
            $attributes['option_stock_level'] = $this->stock_options[0]->stock->stock_level;
            $attributes['option_min_order_qty'] = $this->stock_options[0]->stock->min_order_qty;
        } else {
            $attributes['option_sku'] = false;
            $attributes['option_track_stock'] = false;
            $attributes['option_stock_level'] = false;
            $attributes['option_min_order_qty'] = false;
        }

        return $attributes;
    }
}
