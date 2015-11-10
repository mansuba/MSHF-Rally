<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Tag;

use Channel;
use Store\Model\Order;
use Store\Model\Product;

class SearchTag extends AbstractTag
{
    public function parse()
    {
        $query = Product::join('store_stock', 'store_stock.entry_id', '=', 'store_products.entry_id')
            ->whereNotNull('price')
            ->groupBy('store_products.entry_id')
            ->select(array('store_products.entry_id', $this->ee->store->db->raw('SUM(`stock_level`) AS `total_stock`')));

        // min and max prices
        if (!empty($this->params['search:price:min'])) {
            $query->where('price', '>=', (float) $this->params['search:price:min']);
        }
        if (!empty($this->params['search:price:max'])) {
            $query->where('price', '<', (float) $this->params['search:price:max']);
        }

        // on sale
        if (!empty($this->params['search:on_sale'])) {
            $query->where(function($query) {
                // products must be on sale, otherwise don't match
                $query->whereRaw('false');

                $member_group_id = (int) $this->ee->session->userdata['group_id'];
                foreach ($this->ee->store->products->get_active_sales() as $sale) {
                    // ensure sale applies to current member group
                    if ($sale->member_group_ids && !in_array($member_group_id, $sale->member_group_ids)) {
                        continue;
                    }

                    $query->orWhere(function($query) use ($sale) {
                        // restrict to entry ids
                        if ($sale->entry_ids) {
                            $query->whereIn('store_products.entry_id', $sale->entry_ids);
                        }

                        // restrict to category ids
                        if ($sale->category_ids) {
                            $query->whereExists(function($query) use ($sale) {
                                $query->from('category_posts')
                                    ->whereIn('cat_id', $sale->category_ids)
                                    ->whereRaw('`exp_category_posts`.`entry_id` = `exp_store_products`.`entry_id`');
                            });
                        }
                    });
                }
            });
        }

        // in stock
        if (!empty($this->params['search:in_stock'])) {
            if ($this->params['search:in_stock'] == 'yes') {
                $query->having('total_stock', '>', 0);
            } elseif ($this->params['search:in_stock'] == 'no') {
                $query->having('total_stock', '<=', 0);
            }
        }

        // custom order
        $entries_param = 'entry_id';
        if (!empty($this->params['orderby']) &&
            in_array($this->params['orderby'], array('price', 'regular_price', 'total_stock'))) {

            $sort = isset($this->params['sort']) && strtolower($this->params['sort']) == 'desc' ? 'desc' : 'asc';

            switch ($this->params['orderby']) {
                case 'price':
                case 'regular_price':
                    $query->orderBy('price', $sort);
                case 'total_stock':
                    $query->orderBy('total_stock', $sort);
            }

            $entries_param = 'fixed_order';
            unset($this->params['orderby']);
            unset($this->params['sort']);
        }

        $entry_ids = $query->lists('entry_id');

        // pass entry ids and remaining tagdata to channel entries tag
        $this->params[$entries_param] = '0|'.implode('|', $entry_ids);
        $this->ee->TMPL->tagparams = $this->params;

        $channel = new Channel();

        return $channel->entries();
    }
}
