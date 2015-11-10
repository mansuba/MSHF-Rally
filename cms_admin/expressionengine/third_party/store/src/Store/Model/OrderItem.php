<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class OrderItem extends AbstractModel
{
    protected $table = 'store_order_items';

    protected $currency_attributes = array('price', 'regular_price', 'price_inc_tax',
        'regular_price_inc_tax', 'you_save', 'you_save_inc_tax',
        'handling', 'handling_tax', 'handling_inc_tax',
        'item_subtotal', 'item_discount', 'item_subtotal_inc_discount',
        'item_tax', 'item_total');

    public static function createFromStock(Stock $stock)
    {
        $product = $stock->product;

        $item = new static;
        $item->stock_id = $stock->id;
        $item->entry_id = $product->entry_id;
        $item->title = $product->entry->title;
        $item->url_title = $product->entry->url_title;
        $item->channel_id = $product->entry->channel_id;

        // record category ids for calculating tax and discounts
        $item->category_ids = ee()->store->db->table('category_posts')
            ->where('entry_id', $product->entry_id)
            ->lists('cat_id');

        $item->price = $product->sale_price;
        $item->regular_price = $product->regular_price;
        $item->on_sale = $product->on_sale;
        $item->length = $product->length;
        $item->width = $product->width;
        $item->height = $product->height;
        $item->weight = $product->weight;
        $item->handling = (float) $product->handling;
        $item->free_shipping = $product->free_shipping;

        return $item;
    }

    public function stock()
    {
        return $this->belongsTo('\Store\Model\Stock');
    }

    public function product()
    {
        return $this->belongsTo('\Store\Model\Product', 'entry_id');
    }

    public function getCategoryIdsAttribute()
    {
        return $this->getPipeArrayAttribute('category_ids');
    }

    public function setCategoryIdsAttribute($value)
    {
        return $this->setPipeArrayAttribute('category_ids', $value);
    }

    public function getHandlingIncTaxAttribute()
    {
        return $this->handling + $this->handling_tax;
    }

    public function getItemSubtotalIncDiscountAttribute()
    {
        return $this->item_subtotal - $this->item_discount;
    }

    public function getModifiersAttribute($value)
    {
        $modifiers = json_decode($value, true);

        if (null === $modifiers) {
            // try to unserialize instead (Store 1.x)
            $modifiers = @unserialize(base64_decode($value));
        }

        return empty($modifiers) ? array() : $modifiers;
    }

    public function getModifiersHtmlAttribute()
    {
        $html = array();

        foreach ($this->modifiers as $mod) {
            if ($mod && $mod['modifier_name'] && $mod['modifier_value']) {
                $html[] = '<strong>'.$mod['modifier_name'].'</strong>: '.$mod['modifier_value'];
            }
        }

        return implode(', ', $html);
    }

    public function setModifiersAttribute($value)
    {
        $this->attributes['modifiers'] = json_encode($value);
    }

    public function getDimensionsAttribute()
    {
        $dimensions = array((float) $this->length, (float) $this->width, (float) $this->height);
        sort($dimensions);

        return $dimensions;
    }

    public function getYouSaveAttribute()
    {
        return max(0, $this->regular_price - $this->price);
    }

    public function getYouSaveIncTaxAttribute()
    {
        return $this->regular_price_inc_tax - $this->price_inc_tax;
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

    public function recalculate()
    {
        // skip qty validation if there is no related stock item
        $stock = $this->stock;
        if ($stock) {
            // check if the product has a minimum order qty
            if ($this->item_qty > 0 && $stock->min_order_qty > 1 && $this->item_qty < $stock->min_order_qty) {
                $this->item_qty = $stock->min_order_qty;
            }

            // if we don't allow backorders, then user cannot order more than we have in stock
            if ($stock->track_stock && $this->item_qty > $stock->stock_level) {
                $this->item_qty = $stock->stock_level;

                // make sure new item_qty complies with min_order_qty
                if ($stock->min_order_qty > 1 && $this->item_qty < $stock->min_order_qty) {
                    // this product is essentially out of stock
                    // because the current stock level is below minimum order qty
                    // so let's just remove it from the cart
                    $this->item_qty = 0;
                }
            }
        }

        $this->handling_tax = 0;
        $this->item_subtotal = store_round_currency($this->price * $this->item_qty);
        $this->item_discount = 0;
        $this->item_tax = 0;
        $this->item_total = $this->item_subtotal;
    }

    public function toTagArray()
    {
        $attributes = parent::toTagArray();
        $attributes['key'] = $this->id;
        $attributes['modifiers'] = $this->modifiers;
        $attributes['page_uri'] = $this->page_uri;
        $attributes['page_url'] = $this->page_url;
        $attributes['you_save_percent'] = $this->you_save_percent;

        // backwards compatibility with Store 1.x
        $attributes['dimension_l'] = $attributes['length'];
        $attributes['dimension_w'] = $attributes['width'];
        $attributes['dimension_h'] = $attributes['height'];

        $page = ee()->store->store->get_entry_page_url($this->site_id, $this->entry_id);
        $attributes = array_merge($attributes, $page);

        return $attributes;
    }
}
