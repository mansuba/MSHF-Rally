<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

use Store\Model\ProductModifier;

class Product extends AbstractModel
{
    protected $table = 'store_products';
    protected $primaryKey = 'entry_id';
    public $incrementing = false;

    /**
     * Used to store new stock matrix data from publish page
     */
    public $update_stock;

    protected $fillable = array('price', 'length', 'width', 'height', 'weight', 'handling',
        'free_shipping');

    protected $currency_attributes = array('price', 'regular_price', 'sale_price',
        'you_save', 'handling');

    protected $decimal_attributes = array('price', 'handling', 'length', 'width', 'height', 'weight');

    /**
     * Fake attribute to store the sale price
     */
    protected $_sale_price;

    public function entry()
    {
        return $this->belongsTo('\Store\Model\Entry', 'entry_id');
    }

    public function stock()
    {
        return $this->hasMany('\Store\Model\Stock', 'entry_id');
    }

    public function modifiers()
    {
        return $this->hasMany('\Store\Model\ProductModifier', 'entry_id'); //->orderBy('mod_order');
    }

    /**
     * Get modifiers as associative array
     */
    public function getModifiersArray()
    {
        $modifiers = array();

        foreach ($this->modifiers as $modifier) {
            $mod_key = $modifier->product_mod_id;
            $modifiers[$mod_key] = $modifier->attributesToArray();
            $modifiers[$mod_key]['options'] = array();
            foreach ($modifier->options as $option) {
                $opt_key = $option->product_opt_id;
                $modifiers[$mod_key]['options'][$opt_key] = $option->toArray();
            }
        }

        return $modifiers;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array                              $attributes
     * @return Illuminate\Database\Eloquent\Model
     */
    public function fill(array $attributes)
    {
        // mass assign fillable attributes
        parent::fill($attributes);

        if (isset($attributes['modifiers']) && is_array($attributes['modifiers'])) {
            // replace loaded modifiers
            $this->relations['modifiers'] = array();

            // create new modifiers from form data
            foreach ($attributes['modifiers'] as $key => $mod_attributes) {
                $modifier = new ProductModifier;
                $modifier->setRawAttributes(array(), true); // ignore defaults
                $modifier->entry_id = $this->entry_id;

                if (!empty($mod_attributes['product_mod_id'])) {
                    // existing modifier
                    $modifier->product_mod_id = $mod_attributes['product_mod_id'];
                    $modifier->exists = true;
                }

                // fill attributes *after* we have set product_mod_id so that
                // our custom filler can set product_mod_id on nested options
                $modifier->fill($mod_attributes);

                $this->relations['modifiers'][$key] = $modifier;
            }
        }

        if (isset($attributes['stock'])) {
            $this->update_stock = (array) $attributes['stock'];
        }

        return $this;
    }

    public function getCategoryIdsAttribute()
    {
        $category_ids = array();
        $query = ee()->store->db->table('category_posts')->where('entry_id', $this->entry_id)->get();
        foreach ($query as $row) {
            $category_ids[] = $row['cat_id'];
        }

        return $category_ids;
    }

    public function getMinOrderQtyAttribute()
    {
        if (count($this->stock) < 1) {
            return 1;
        }

        $min_order_qty = $this->stock[0]->min_order_qty;
        foreach ($this->stock as $item) {
            if ($item->min_order_qty < $min_order_qty) {
                $min_order_qty = $item->min_order_qty;
            }
        }

        return max($min_order_qty, 1);
    }

    public function getTotalStockAttribute()
    {
        $total = 0;
        foreach ($this->stock as $item) {
            $total += $item->stock_level;
        }

        return $total;
    }

    public function getTrackStockAttribute()
    {
        foreach ($this->stock as $item) {
            if ($item->track_stock) {
                return true;
            }
        }

        return false;
    }

    public function getPriceAttribute()
    {
        return $this->getSalePriceAttribute();
    }

    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = $value;
        $this->sale_price = $value;
    }

    public function getRegularPriceAttribute()
    {
        return isset($this->attributes['price']) ? $this->attributes['price'] : null;
    }

    public function getSalePriceAttribute()
    {
        return null === $this->_sale_price ? $this->getRegularPriceAttribute() : $this->_sale_price;
    }

    public function setSalePriceAttribute($value)
    {
        $this->_sale_price = $value;
    }

    public function getOnSaleAttribute()
    {
        return $this->sale_price < $this->regular_price;
    }

    public function getYouSaveAttribute()
    {
        return max(0, $this->regular_price - $this->sale_price);
    }

    public function getYouSavePercentAttribute()
    {
        // avoid divide by zero error on free products
        if ($this->regular_price > 0) {
            return (int) round($this->you_save / $this->regular_price * 100);
        } else {
            return 0;
        }
    }

    /**
     * Extra attributes available in JS
     */
    public function toArray()
    {
        $attributes = parent::toArray();
        $attributes['regular_price'] = $this->regular_price;
        $attributes['sale_price'] = $this->sale_price;

        return $attributes;
    }

    /**
     * Override array form for use in EE tags
     */
    public function toTagArray()
    {
        $attributes = parent::toTagArray();

        // inc_tax attributes are deprecated from 2.0
        $attributes['price_inc_tax'] = $attributes['price'];
        $attributes['price_inc_tax_val'] = $attributes['price_val'];
        $attributes['regular_price_inc_tax'] = $attributes['regular_price'];
        $attributes['regular_price_inc_tax_val'] = $attributes['regular_price_val'];
        $attributes['sale_price_inc_tax'] = $attributes['sale_price'];
        $attributes['sale_price_inc_tax_val'] = $attributes['sale_price_val'];
        $attributes['you_save_inc_tax'] = $attributes['you_save'];
        $attributes['you_save_inc_tax_val'] = $attributes['you_save_val'];

        // backwards compatibility with Store 1.x
        $attributes['dimension_l'] = $attributes['length'];
        $attributes['dimension_w'] = $attributes['width'];
        $attributes['dimension_h'] = $attributes['height'];

        // sales & stock
        $attributes['on_sale'] = $this->on_sale;
        $attributes['you_save_percent'] = $this->you_save_percent;
        $attributes['min_order_qty'] = $this->min_order_qty;
        $attributes['total_stock'] = $this->total_stock;
        $attributes['track_stock'] = $this->track_stock;

        if (isset($this->relations['modifiers'])) {
            $attributes['modifiers'] = $this->modifiers->toTagArray();
        }
        $attributes['no_modifiers'] = empty($attributes['modifiers'][0]);

        // these attributes only make sense if the product has a single SKU
        if (!isset($attributes['sku'])) {
            if (isset($this->relations['stock']) && count($this->relations['stock']) >= 1) {
                $attributes['sku'] = $this->relations['stock'][0]['sku'];
                $attributes['stock_level'] = $this->relations['stock'][0]['stock_level'];
            } else {
                $attributes['sku'] = false;
                $attributes['stock_level'] = false;
            }
        }

        return $attributes;
    }
}
