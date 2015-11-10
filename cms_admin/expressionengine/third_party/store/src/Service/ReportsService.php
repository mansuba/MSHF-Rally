<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Carbon\Carbon;
use Store\DateTime;
use Store\Model\Member;
use Store\Model\Order;
use Store\Model\OrderItem;
use Store\Model\Product;
use Store\Model\Transaction;

class ReportsService extends AbstractService
{
    public function get_reports()
    {
        $reports = array(
            'customer_summary'      => '\Store\Report\CustomerSummaryReport',
            'inventory'             => '\Store\Report\InventoryReport',
            'order_details'         => '\Store\Report\OrderDetailsReport',
            'order_items'           => '\Store\Report\OrderItemsReport',
            'product_sales'         => '\Store\Report\ProductSalesReport',
            'revenue_summary'       => '\Store\Report\RevenueSummaryReport',
        );

        if ($this->ee->extensions->active_hook('store_reports')) {
            $reports = $this->ee->extensions->call('store_reports', $reports);
        }

        ksort($reports);

        return $reports;
    }

    public function timezone()
    {
        return config_item('store_reporting_timezone') ?: 'UTC';
    }

    public function month_select_options()
    {
        $now = DateTime::now($this->timezone());
        $min_order_date = Order::where('order_date', '>', 0)->min('order_date') ?: $now->timestamp;
        $month = DateTime::createFromTimeStamp($min_order_date, $this->timezone())->startOfMonth();

        $options = array();
        while($month <= $now) {
            $options[$month->formatEE('%Y-%m')] = $month->formatEE('%F %Y');
            $month->addMonth();
        }

        return $options;
    }

    public function member_select_options()
    {
        $query = Member::join('store_orders', 'store_orders.member_id', '=', 'members.member_id')
            ->where('order_completed_date', '>', 0)
            ->groupBy('members.member_id')
            ->select('members.member_id', 'members.screen_name');

        $members = array();
        foreach ($query->get() as $row) {
            $members[$row->member_id] = $row->screen_name;
        }

        return $members;
    }

    public function get_dashboard_stats($period)
    {
        $period = (int) $period;

        // current period
        $current = $this->ee->store->db->table('store_orders')
            ->select(
                array(
                    $this->ee->store->db->raw('COALESCE(SUM(order_total), 0) AS `revenue`'),
                    $this->ee->store->db->raw('COUNT(id) AS `orders`'),
                    $this->ee->store->db->raw('COALESCE(SUM(order_qty), 0) AS `items`'),
                    $this->ee->store->db->raw('CASE COUNT(id) WHEN 0 THEN 0 ELSE SUM(order_total)/COUNT(id) END AS `average_order`'),
                )
            )->where('site_id', config_item('site_id'))
            ->where('order_completed_date', '>=', $this->ee->store->db->raw("UNIX_TIMESTAMP(DATE(NOW() - INTERVAL $period DAY))"))
            ->where('order_completed_date', '<', $this->ee->store->db->raw("UNIX_TIMESTAMP(DATE(NOW()))"))
            ->first();

        // previous period
        $previous_days = $period * 2;
        $previous = $this->ee->store->db->table('store_orders')
            ->select(
                array(
                    $this->ee->store->db->raw('SUM(order_total) AS `prev_revenue`'),
                    $this->ee->store->db->raw('COUNT(id) AS `prev_orders`'),
                    $this->ee->store->db->raw('SUM(order_qty) AS `prev_items`'),
                    $this->ee->store->db->raw('SUM(order_total)/COUNT(id) AS `prev_average_order`'),
                )
            )->where('site_id', config_item('site_id'))
            ->where('order_completed_date', '>=', $this->ee->store->db->raw("UNIX_TIMESTAMP(DATE(NOW() - INTERVAL $previous_days DAY))"))
            ->where('order_completed_date', '<', $this->ee->store->db->raw("UNIX_TIMESTAMP(DATE(NOW() - INTERVAL $period DAY))"))
            ->first();

        return array_merge($current, $previous);
    }

    public function get_dashboard_graph_data($period)
    {
        $period = (int) $period;

        // for now dashboard data is grouped by timezone of mysql server
        $totals = $this->ee->store->db->table('store_orders')
            ->select(
                array(
                    $this->ee->store->db->raw('DATE(FROM_UNIXTIME(`order_completed_date`)) AS `date`'),
                    $this->ee->store->db->raw('SUM(order_total) AS `total`'),
                )
            )->where('site_id', config_item('site_id'))
            ->where('order_completed_date', '>=', $this->ee->store->db->raw("UNIX_TIMESTAMP(DATE(NOW() - INTERVAL $period DAY))"))
            ->where('order_completed_date', '<', $this->ee->store->db->raw("UNIX_TIMESTAMP(DATE(NOW()))"))
            ->groupBy($this->ee->store->db->raw('DATE(FROM_UNIXTIME(order_completed_date))'))
            ->orderBy('order_completed_date')
            ->lists('total', 'date');

        // ask MySQL for the start and end dates too, we can't assume PHP timezone matches MySQL
        $dates = $this->ee->store->db->select("SELECT DATE(NOW() - INTERVAL $period DAY) AS `start`, DATE(NOW()) AS `end`");
        $start_date = Carbon::createFromFormat('Y-m-d', $dates[0]['start'], 'UTC')->setTime(0, 0, 0);
        $end_date = Carbon::createFromFormat('Y-m-d', $dates[0]['end'], 'UTC')->setTime(0, 0, 0);

        /**
         * Format data for google charts
         * @link https://developers.google.com/chart/interactive/docs/reference#dataparam
         */
        $data = array();
        $data['cols'] = array(
            array('type' => 'string'),
            array('label' => lang('store.revenue'), 'type' => 'number'),
        );

        $date = $start_date->copy();
        while ($date < $end_date) {
            $ymd = $date->toDateString();
            $total = isset($totals[$ymd]) ? $totals[$ymd] : 0;

            $data['rows'][] = array(
                'c' => array(
                    array('v' => $ymd, 'f' => $this->ee->store->store->format_date($date, '%M %j')),
                    array('v' => $total, 'f' => store_currency($total)),
                ),
            );

            $date->addDay();
        }

        return $data;
    }
}
