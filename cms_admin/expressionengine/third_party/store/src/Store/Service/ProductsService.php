<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Store\Model\Product;
use Store\Model\ProductModifier;
use Store\Model\ProductOption;
use Store\Model\Sale;
use Store\Model\Stock;
use Store\Model\StockOption;

/**
 * Products Service
 */
class ProductsService extends AbstractService
{
    protected $active_sales;
    protected $channel_fields;

    public function get_categories()
    {
        $cat_group_ids = $this->ee->store->db->table('channels')
            ->join('channel_fields', 'channel_fields.group_id', '=', 'channels.field_group')
            ->where('channel_fields.field_type', 'store')
            ->lists('cat_group');

        $this->ee->load->library('api');
        $this->ee->api->instantiate('channel_categories');

        $category_data = $this->ee->api_channel_categories->category_tree(implode('|', $cat_group_ids), null, 'a');

        $categories = array();
        if (!empty($category_data)) {
            foreach ($category_data as $row) {
                $categories[$row['0']] = str_repeat(NBS.NBS.NBS, $row['5']-1).$row['1'];
            }
        }

        return $categories;
    }

    /**
     * Quick list of all products for use in multi select
     */
    public function get_product_titles()
    {
        $query = $this->ee->store->db->table('store_products')
            ->join('channel_titles', 'channel_titles.entry_id', '=', 'store_products.entry_id')
            ->select(array('channel_titles.entry_id', 'channel_titles.title'))
            ->orderBy('channel_titles.title')
            ->get();

        $products = array();
        foreach ($query as $row) {
            $products[$row['entry_id']] = $row['title'];
        }

        return $products;
    }

    /**
     * Find all channels which have a store fieldtype, with fieldtype settings
     */
    public function get_channel_fields()
    {
        if (null === $this->channel_fields) {
            $this->channel_fields = array();
            $query = $this->ee->store->db->table('channels')
                ->join('channel_fields', 'channel_fields.group_id', '=', 'channels.field_group')
                ->where('channel_fields.field_type', 'store')
                ->get();

            foreach ($query as $row) {
                // decode settings
                $row['field_settings'] = unserialize(base64_decode($row['field_settings']));
                $this->channel_fields[$row['channel_id']] = $row;
            }
        }

        return $this->channel_fields;
    }

    /**
     * Recursively save product and modifiers/options/stock
     */
    public function save_product(Product $product)
    {
        $product->save();
        $this->save_product_modifiers($product);
        $this->save_product_stock($product);
    }

    public function save_product_modifiers(Product $product)
    {
        // save modifiers
        foreach ($product->modifiers as $modifier) {
            if ($modifier->mod_name) {
                $modifier->save();

                // save options
                foreach ($modifier->options as $option) {
                    if (null === $option->opt_name || '' === $option->opt_name) {
                        // option has been removed
                        $option->delete();
                    } else {
                        // update product_mod_id in case this modifier was just created
                        $option->product_mod_id = $modifier->product_mod_id;
                        $option->save();
                    }
                }
            } else {
                // modifier has been removed
                $modifier->options()->delete();
                $modifier->delete();
            }
        }
    }

    public function save_product_stock(Product $product)
    {
        // clear existing stock options
        StockOption::where('entry_id', $product->entry_id)->delete();

        // remember stock rows we insert or update
        $stock_ids = array();

        foreach ($product->update_stock as $stock_attributes) {
            // find or create stock
            $stock = null;
            if (!empty($stock_attributes['id'])) {
                $stock = Stock::where('entry_id', $product->entry_id)
                    ->where('id', $stock_attributes['id'])->first();
            }
            if (!$stock) {
                $stock = new Stock;
                $stock->entry_id = $product->entry_id;
            }
            $stock->fill($stock_attributes);
            $stock->save();
            $stock_ids[] = $stock->id;

            // create new stock options
            if (!empty($stock_attributes['stock_options'])) {
                foreach ($stock_attributes['stock_options'] as $option_data) {
                    $stock_option = new StockOption;
                    $stock_option->entry_id = $product->entry_id;
                    $stock_option->stock_id = $stock->id;
                    $stock_option->sku = $stock->sku;

                    // look up real modifier and option ids (newly created modifiers will have different ID)
                    $mod_id = $option_data['product_mod_id'];
                    $opt_id = $option_data['product_opt_id'];
                    $stock_option->product_mod_id = (int) $product->modifiers[$mod_id]['product_mod_id'];
                    $stock_option->product_opt_id = (int) $product->modifiers[$mod_id]['options'][$opt_id]['product_opt_id'];
                    $stock_option->save();
                }
            }
        }

        // remove any rogue stock entries
        $query = Stock::where('entry_id', $product->entry_id);
        if ($stock_ids) {
            $query->whereNotIn('id', $stock_ids);
        }
        $query->delete();
    }

    public function delete_all($entry_ids)
    {
        $entry_ids = (array) $entry_ids;
        if (empty($entry_ids)) {
            return;
        }

        // delete modifiers
        $modifier_ids = ProductModifier::whereIn('entry_id', $entry_ids)->lists('product_mod_id');
        if (!empty($modifier_ids)) {
            ProductModifier::whereIn('product_mod_id', $modifier_ids)->delete();
            ProductOption::whereIn('product_mod_id', $modifier_ids)->delete();
        }

        // delete stock
        Stock::whereIn('entry_id', $entry_ids)->delete();
        StockOption::whereIn('entry_id', $entry_ids)->delete();

        // delete product
        Product::whereIn('entry_id', $entry_ids)->delete();
    }

    public function get_active_sales()
    {
        // find and cache current sales
        if (null === $this->active_sales) {
            $this->active_sales = Sale::where('site_id', config_item('site_id'))
                ->where('enabled', 1)
                ->where(function($query) {
                    $query->whereNull('start_date')->orWhere('start_date', '<=', time());
                })->where(function($query) {
                    $query->whereNull('end_date')->orWhere('end_date', '>=', time());
                })->where(function($query) {
                    $query->where('per_item_discount', '>', 0)->orWhere('percent_discount', '>', 0);
                })->orderBy('sort')
                ->get();
        }

        return $this->active_sales;
    }

    public function apply_sales(Product $product)
    {
        // set initial option sale prices
        if (isset($product->modifiers)) {
            foreach ($product->modifiers as $modifier) {
                foreach ($modifier->options as $option) {
                    $option->sale_price_mod = $option->opt_price_mod;
                }
            }
        }

        // find any sales which apply to this product
        $member_group_id = (int) $this->ee->session->userdata['group_id'];
        foreach ($this->get_active_sales() as $sale) {
            if ($this->sale_applies_to_product($sale, $product, $member_group_id)) {
                if ((float) $sale->per_item_discount) {
                    $product->sale_price = store_round_currency($product->sale_price - $sale->per_item_discount);
                }
                if ((float) $sale->percent_discount) {
                    $product->sale_price = store_round_currency($product->sale_price - ($sale->percent_discount * $product->sale_price / 100));

                    // percentage discount also needs to be applied to loaded modifiers
                    if (isset($product->modifiers)) {
                        foreach ($product->modifiers as $modifier) {
                            foreach ($modifier->options as $option) {
                                if ($option->opt_price_mod) {
                                    $option->sale_price_mod = store_round_currency($option->sale_price_mod - ($sale->percent_discount * $option->sale_price_mod / 100), true);
                                }
                            }
                        }
                    }
                }

                $product->on_sale = true;
            }
        }

        return $product;
    }

    /**
     * Returns true if the product matches
     */
    public function sale_applies_to_product(Sale $sale, Product $product, $member_group_id)
    {
        // check member group id
        if ($sale->member_group_ids && !in_array($member_group_id, $sale->member_group_ids)) {
            return false;
        }

        // check entry id
        if ($sale->entry_ids && !in_array($product->entry_id, $sale->entry_ids)) {
            return false;
        }

        // check category id
        if ($sale->category_ids && !array_intersect($product->category_ids, $sale->category_ids)) {
            return false;
        }

        return true;
    }
}
