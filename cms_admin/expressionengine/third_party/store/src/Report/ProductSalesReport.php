<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Report;

use Store\DateTime;
use Store\Model\OrderItem;

class ProductSalesReport extends AbstractReport
{
    protected $orderby = 'stock_id';

    public function default_options()
    {
        $now = DateTime::now($this->ee->store->reports->timezone());

        return array(
            'from' => array('type' => 'date_select', 'default' => $now->copy()->subDays(30)->format('Y-m-d')),
            'to' => array('type' => 'date_select', 'default' => $now->format('Y-m-d')),
        );
    }

    public function run()
    {
        $totals = array('revenue' => 0, 'count' => 0);

        $this->render->initialize($this->orderby, $this->sort);
        $this->render->table_open();
        $this->render->table_header(array(
            array('data' => lang('store.#'), 'orderby' => 'stock_id'),
            array('data' => lang('store.entry_id'), 'orderby' => 'entry_id'),
            array('data' => lang('store.reports.channel_id'), 'orderby' => 'channel_id'),
            array('data' => lang('store.reports.channel_name'), 'orderby' => 'channel_title'),
            array('data' => lang('store.sku'), 'orderby' => 'sku'),
            array('data' => lang('title'), 'orderby' => 'title'),
            array('data' => lang('store.reports.sales'), 'orderby' => 'count', 'class' => 'store_numeric'),
            array('data' => lang('store.revenue'), 'orderby' => 'revenue', 'class' => 'store_numeric'),
        ));

        $db = $this->ee->store->db;
        $query = OrderItem::join('store_orders', 'store_orders.id', '=', 'store_order_items.order_id')
            ->leftJoin('channels', 'channels.channel_id', '=', 'store_order_items.channel_id')
            ->where('store_order_items.site_id', config_item('site_id'))
            ->where('store_orders.order_completed_date', '>', 0)
            ->select(array(
                'store_order_items.*',
                'channels.channel_title',
                $db->raw('sum(item_qty) as count'),
                $db->raw('sum(item_subtotal) as revenue')))
            ->groupBy('stock_id')
            ->groupBy('sku');

        if ($this->options['from']) {
            $query->where('order_date', '>=', $this->options['from']->timestamp);
        }

        if ($this->options['to']) {
            $query->where('order_date', '<', $this->options['to']->addDay()->timestamp);
        }

        if (in_array($this->orderby, array('stock_id', 'entry_id', 'channel_id', 'channel_title', 'sku', 'title', 'count', 'revenue'))) {
            $query->orderBy($this->orderby, $this->sort);
        }

        foreach ($query->get() as $row) {
            /* $member_name = $row->member_id ? $row->username : lang('store.reports.anonymous'); */
            /* $link = store_cp_url('reports', 'show', array_merge(array('report' => 'order_details', 'member_id' => $row->member_id ?: 'anonymous'), $this->options->all())); */

            $this->render->table_row(array(
                $row->stock_id,
                $row->entry_id,
                $row->channel_id,
                $row->channel_title,
                $row->sku,
                $row->title,
                array('class' => 'store_numeric', 'data' => $row->count),
                array('class' => 'store_numeric', 'data' => store_currency($row->revenue)),
            ));

            $totals['revenue'] += $row->revenue;
            $totals['count'] += $row->count;
        }

        $this->render->table_footer(array(
            array('data' => lang('store.totals'), 'colspan' => 6),
            array('class' => 'store_numeric', 'data' => $totals['count']),
            array('class' => 'store_numeric', 'data' => store_currency($totals['revenue'])),
        ));
        $this->render->table_close();
    }
}
