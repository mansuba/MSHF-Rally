<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Report;

use Store\DateTime;
use Store\Model\Order;

class RevenueSummaryReport extends AbstractReport
{
    public function default_options()
    {
        return array(
            'from' => array('type' => 'month_select', 'default' => -12),
            'to' => array('type' => 'month_select', 'default' => -1),
        );
    }

    public function run()
    {
        $total_revenue = 0;

        $this->render->initialize($this->orderby, $this->sort);
        $this->render->table_open();
        $this->render->table_header(array(
            lang('store.month'),
            array('class' => 'store_numeric', 'data' => lang('store.revenue')),
        ));

        $month = DateTime::createFromFormat('Y-m', $this->options['from'], $this->ee->store->reports->timezone())->startOfMonth();
        $to_month = DateTime::createFromFormat('Y-m', $this->options['to'], $this->ee->store->reports->timezone())->startOfMonth();

        // in case you're wondering why we don't use a SQL GROUP BY statement,
        // it's because we can't rely on MySQL's handling of timezones and DST.
        // we want each month to start and end at midnight in the reporting timezone,
        // even if DST started or ended during the month
        while ($month <= $to_month) {
            $start_of_month = $month->copy();
            $end_of_month = $month->addMonth();

            $revenue = Order::where('order_completed_date', '>', 0)
                ->where('order_date', '>=', $start_of_month->timestamp)
                ->where('order_date', '<', $end_of_month->timestamp)
                ->sum('order_paid') ?: 0;

            $month_url = store_cp_url('reports', 'show', array(
                'report' => 'order_details',
                'from' => $start_of_month->format('Y-m-d'),
                'to' => $end_of_month->copy()->subDay()->format('Y-m-d'),
            ));

            $this->render->table_row(array(
                sprintf('<a href="%s">%s</a>', $month_url, $start_of_month->formatEE('%F %Y')),
                array('class' => 'store_numeric', 'data' => store_currency($revenue)),
            ));

            $total_revenue += $revenue;
        }

        $this->render->table_row(array(
            lang('store.total'),
            array('class' => 'store_numeric', 'data' => store_currency($total_revenue)),
        ));
        $this->render->table_close();
    }
}
