<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Report;

use Store\DateTime;
use Store\Model\Stock;

class InventoryReport extends AbstractReport
{
    protected $totals;
    protected $orderby = 'title';

    public function default_options()
    {
        return array(
            'category_id' => array('type' => 'category_select'),
        );
    }

    public function run()
    {
        $this->totals = array(
            'stock_level' => 0,
            'stock_value' => 0,
        );

        $this->render->initialize($this->orderby, $this->sort);
        $this->render->table_open();
        $this->render->table_header(array(
            array('data' => lang('store.entry_id'), 'orderby' => 'entry_id'),
            array('data' => lang('store.sku'), 'orderby' => 'sku'),
            array('data' => lang('store.product_title'), 'orderby' => 'title'),
            array('data' => lang('store.price'), 'orderby' => 'price', 'class' => 'store_numeric'),
            array('data' => lang('store.current_stock_level'), 'orderby' => 'stock_level', 'class' => 'store_numeric'),
            array('data' => lang('store.total_stock_value'), 'orderby' => 'stock_value', 'class' => 'store_numeric'),
        ));

        $db = $this->ee->store->db;
        $query = Stock::join('channel_titles', 'channel_titles.entry_id', '=', 'store_stock.entry_id')
            ->join('store_products', 'store_products.entry_id', '=', 'store_stock.entry_id')
            ->leftJoin('store_stock_options', 'store_stock_options.stock_id', '=', 'store_stock.id')
            ->leftJoin('store_product_options', 'store_product_options.product_opt_id', '=', 'store_stock_options.product_opt_id')
            ->leftJoin('store_product_modifiers', 'store_product_modifiers.product_mod_id', '=', 'store_product_options.product_mod_id')
            ->select(array(
                'store_stock.*',
                'channel_titles.title',
                $db->raw('price + coalesce(sum(opt_price_mod), 0) as price'),
                $db->raw('group_concat(concat(mod_name, ": ", opt_name) order by mod_order, opt_order separator ", ") as description'),
                $db->raw('price * stock_level as stock_value'),
            ))
            ->where('channel_titles.site_id', config_item('site_id'))
            ->groupBy('store_stock.id');

        if ($this->options['category_id']) {
            $query->leftJoin('category_posts', 'category_posts.entry_id', '=', 'store_stock.entry_id')
                ->where('category_posts.cat_id', $this->options['category_id']);
        }

        if (in_array($this->orderby, array('entry_id', 'sku', 'stock_level'))) {
            $query->orderBy('store_stock.'.$this->orderby, $this->sort);
        } elseif (in_array($this->orderby, array('price', 'stock_value'))) {
            $query->orderBy($this->orderby, $this->sort);
        } elseif ($this->orderby === 'title') {
            $query->orderBy('channel_titles.title', $this->sort)
                ->orderBy('description', $this->sort);
        }

        $self = $this;
        $query->chunk(1000, function($chunk) use ($self) {
            foreach ($chunk as $stock) {
                $self->row($stock);
            }
        });

        $this->render->table_footer(array(
            array('data' => lang('store.totals'), 'colspan' => 4),
            array('class' => 'store_numeric', 'data' => $this->totals['stock_level']),
            array('class' => 'store_numeric', 'data' => store_currency($this->totals['stock_value'])),
        ));
        $this->render->table_close();
    }

    public function row($stock)
    {
        $title = $stock->title;
        if ($stock->description) {
            $title .= ' ('.$stock->description.')';
        }

        $this->totals['stock_level'] += $stock->stock_level;
        $this->totals['stock_value'] += $stock->stock_value;

        $this->render->table_row(array(
            $stock->entry_id,
            $stock->sku,
            $title,
            array('data' => store_currency($stock->price), 'class' => 'store_numeric'),
            array('data' => $stock->stock_level, 'class' => 'store_numeric'),
            array('data' => store_currency($stock->stock_value), 'class' => 'store_numeric'),
        ));
    }
}
