<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Report;

use Store\DateTime;
use Store\Model\OrderItem;

class OrderItemsReport extends AbstractReport
{
    protected $orderby = 'order_date';
    protected $totals;

    public function default_options()
    {
        $now = DateTime::now($this->ee->store->reports->timezone());
        $options = array(
            'from' => array('type' => 'date_select', 'default' => $now->copy()->subDays(30)->format('Y-m-d')),
            'to' => array('type' => 'date_select', 'default' => $now->format('Y-m-d')),
            'member_id' => array('type' => 'select'),
        );

        $options['member_id']['options'] =
            array('' => lang('store.any'), 'anonymous' => lang('store.reports.anonymous')) +
            $this->ee->store->reports->member_select_options();

        return $options;
    }

    public function run()
    {
        $this->totals = array(
            'item_qty' => 0,
            'item_subtotal' => 0,
            'item_discount' => 0,
            'item_tax' => 0,
            'item_total' => 0,
        );

        $this->render->initialize($this->orderby, $this->sort);
        $this->render->table_open();
        $this->render->table_header(array(
            array('data' => lang('store.#'), 'orderby' => 'id'),
            array('data' => lang('store.order_id'), 'orderby' => 'order_id'),
            array('data' => lang('store.order_date'), 'orderby' => 'order_date'),
            array('data' => lang('store.reports.member_id'), 'orderby' => 'member_id'),
            array('data' => lang('store.reports.member_name'), 'orderby' => 'screen_name'),
            array('data' => lang('store.reports.channel_id'), 'orderby' => 'channel_id'),
            array('data' => lang('store.reports.channel_name'), 'orderby' => 'channel_title'),
            array('data' => lang('store.entry_id'), 'orderby' => 'entry_id'),
            array('data' => lang('store.stock_id'), 'orderby' => 'stock_id'),
            array('data' => lang('store.sku'), 'orderby' => 'sku'),
            array('data' => lang('title'), 'orderby' => 'title'),
            array('data' => lang('store.modifiers')),
            array('data' => lang('store.price'), 'orderby' => 'price', 'class' => 'store_numeric'),
            array('data' => lang('store.quantity'), 'orderby' => 'item_qty', 'class' => 'store_numeric'),
            array('data' => lang('store.reports.subtotal'), 'orderby' => 'item_subtotal', 'class' => 'store_numeric'),
            array('data' => lang('store.reports.discount'), 'orderby' => 'item_discount', 'class' => 'store_numeric'),
            array('data' => lang('store.reports.tax'), 'orderby' => 'item_tax', 'class' => 'store_numeric'),
            array('data' => lang('store.total'), 'orderby' => 'item_total', 'class' => 'store_numeric'),
        ));

        $query = OrderItem::join('store_orders', 'store_orders.id', '=', 'store_order_items.order_id')
            ->leftJoin('members', 'members.member_id', '=', 'store_orders.member_id')
            ->leftJoin('channels', 'channels.channel_id', '=', 'store_order_items.channel_id')
            ->where('store_order_items.site_id', config_item('site_id'))
            ->where('store_orders.order_completed_date', '>', 0)
            ->select(array('store_order_items.*', 'store_orders.order_date', 'store_orders.member_id', 'members.screen_name', 'channels.channel_title'));

        if ($this->options['from']) {
            $query->where('store_orders.order_date', '>=', $this->options['from']->timestamp);
        }

        if ($this->options['to']) {
            $query->where('store_orders.order_date', '<', $this->options['to']->addDay()->timestamp);
        }

        if ($this->options['member_id'] === 'anonymous') {
            $query->whereNull('store_orders.member_id');
        } elseif ($this->options['member_id'] > 0) {
            $query->where('store_orders.member_id', $this->options['member_id']);
        }

        if ($this->orderby === 'id') {
            $query->orderBy('store_order_items.id', $this->sort);
        } elseif (in_array($this->orderby, array('order_id', 'order_date', 'member_id',
            'screen_name', 'channel_id', 'channel_title', 'entry_id', 'stock_id', 'sku',
            'title', 'price', 'item_qty', 'item_subtotal', 'item_discount', 'item_tax',
            'item_total'))) {
            $query->orderBy($this->orderby, $this->sort);
        }

        $self = $this;
        $query->chunk(1000, function($chunk) use ($self) {
            foreach ($chunk as $item) {
                $self->run_item($item);
            }
        });

        $this->render->table_footer(array(
            array('data' => lang('store.totals'), 'colspan' => 13),
            array('class' => 'store_numeric', 'data' => $this->totals['item_qty']),
            array('class' => 'store_numeric', 'data' => store_currency($this->totals['item_subtotal'])),
            array('class' => 'store_numeric', 'data' => store_currency($this->totals['item_discount'])),
            array('class' => 'store_numeric', 'data' => store_currency($this->totals['item_tax'])),
            array('class' => 'store_numeric', 'data' => store_currency($this->totals['item_total'])),
        ));
        $this->render->table_close();
    }

    public function run_item($item)
    {
        $member_name = $item->member_id ? $item->screen_name : lang('store.reports.anonymous');

        $row = array(
            $item->id,
            $item->order_id,
            $this->ee->localize->format_date('%d %M %Y %H:%i:%s %O', $item->order_date, $this->ee->store->reports->timezone()),
            $item->member_id ?: null, // display 0 as empty
            e($member_name),
            $item->channel_id,
            $item->channel_title,
            $item->entry_id,
            $item->stock_id,
            $item->sku,
            $item->title,
            $item->modifiers_html,
            array('class' => 'store_numeric', 'data' => store_currency($item->price)),
            array('class' => 'store_numeric', 'data' => $item->item_qty),
            array('class' => 'store_numeric', 'data' => store_currency($item->item_subtotal)),
            array('class' => 'store_numeric', 'data' => store_currency($item->item_discount)),
            array('class' => 'store_numeric', 'data' => store_currency($item->item_tax)),
            array('class' => 'store_numeric', 'data' => store_currency($item->item_total)),
        );

        foreach ($this->totals as $key => $value) {
            $this->totals[$key] += $item->$key;
        }

        $this->render->table_row($row);
    }
}
