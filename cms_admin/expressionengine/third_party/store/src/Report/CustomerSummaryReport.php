<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Report;

use Store\DateTime;
use Store\Model\Order;

class CustomerSummaryReport extends AbstractReport
{
    protected $orderby = 'username';

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
            array('data' => lang('store.#'), 'orderby' => 'id'),
            array('data' => lang('username'), 'orderby' => 'username'),
            array('data' => lang('screen_name'), 'orderby' => 'screen_name'),
            array('data' => lang('email'), 'orderby' => 'email'),
            array('data' => lang('store.orders'), 'orderby' => 'count'),
            array('data' => lang('store.revenue'), 'orderby' => 'revenue', 'class' => 'store_numeric'),
        ));

        $db = $this->ee->store->db;
        $query = Order::leftJoin('members', 'members.member_id', '=', 'store_orders.member_id')
            ->where('store_orders.site_id', config_item('site_id'))
            ->where('store_orders.order_completed_date', '>', 0)
            ->select(array(
                'members.*',
                $db->raw('sum(order_paid) as revenue'),
                $db->raw('count(*) as count')))
            ->groupBy('members.member_id');

        if ($this->options['from']) {
            $query->where('order_date', '>=', $this->options['from']->timestamp);
        }

        if ($this->options['to']) {
            $query->where('order_date', '<', $this->options['to']->addDay()->timestamp);
        }

        if ($this->orderby === 'id') {
            $query->orderBy('members.member_id', $this->sort);
        } elseif (in_array($this->orderby, array('username', 'screen_name', 'email', 'count', 'revenue'))) {
            $query->orderBy($this->orderby, $this->sort);
        }

        foreach ($query->get() as $row) {
            $member_name = $row->member_id ? $row->username : lang('store.reports.anonymous');
            $link = store_cp_url('reports', 'show', array_merge(array('report' => 'order_details', 'member_id' => $row->member_id ?: 'anonymous'), $this->options->all()));

            $this->render->table_row(array(
                $row->member_id,
                sprintf('<a href="%s">%s</a>', $link, e($member_name)),
                e($row->screen_name),
                e($row->email),
                $row->count,
                array('class' => 'store_numeric', 'data' => store_currency($row->revenue)),
            ));

            $totals['revenue'] += $row->revenue;
            $totals['count'] += $row->count;
        }

        $this->render->table_footer(array(
            array('data' => lang('store.totals'), 'colspan' => 4),
            $totals['count'],
            array('class' => 'store_numeric', 'data' => store_currency($totals['revenue'])),
        ));
        $this->render->table_close();
    }
}
