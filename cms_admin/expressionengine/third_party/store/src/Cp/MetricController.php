<?php

namespace Store\Cp;

use DateTime;

class MetricController extends AbstractController
{
    private $metric = array();
    private $db;

    // ********************************************************************************* //

    public function __construct($ee)
    {
        parent::__construct($ee);

        ee()->db->save_queries = TRUE;

        $this->db = $this->ee->store->db;

        $this->metric = array();
        $this->metric['success'] = true;
        $this->metric['error'] = '';
        $this->metric['prev_days'] = '';
        $this->metric['trend_direction'] = '';
        $this->metric['trend_difference'] = '';
        $this->metric['trend_percent'] = '0%';
        $this->metric['chart_numbers'] = array();
        $this->metric['chart_labels'] = array();
        $this->metric['periods'] = array();
        $this->metric['periods']['current']['start'] = '';
        $this->metric['periods']['current']['end'] = '';
        $this->metric['periods']['current']['diff'] = '';
        $this->metric['periods']['current']['sum'] = 0;
        $this->metric['periods']['current']['sum_str'] = '0';
        $this->metric['periods']['current']['days'] = null;
        $this->metric['periods']['prev']['start'] = '';
        $this->metric['periods']['prev']['end'] = '';
        $this->metric['periods']['prev']['diff'] = '';
        $this->metric['periods']['prev']['sum'] = 0;
        $this->metric['periods']['prev']['sum_str'] = '0';
        $this->metric['periods']['prev']['days'] = null;

        $this->calculcateDateRange(false, false);
    }

    // ********************************************************************************* //

    public function index()
    {
        $metric = $this->metric;
        $metricName = $this->ee->input->get_post('metric');
        $metricMethod = store_camel('metric'.ucfirst($metricName));

        if (method_exists($this, $metricMethod)) {
            $metric = $this->{$metricMethod}($metric);
        }

        $metric = $this->calculateTrend($metric);
        return $this->ee->output->send_ajax_response($metric);
    }

    // ********************************************************************************* //

    private function metricRevenue($metric)
    {
        foreach ($metric['periods'] as $period => &$dates) {
            //----------------------------------------
            // Active Subscriptions this month
            //----------------------------------------
            $query = $this->db->table('store_orders')
            ->select(array($this->db->raw('SUM(order_total) AS revenue')))
            ->where('order_completed_date', '>', $dates['start_unix'])
            ->where('order_completed_date', '<', $dates['end_unix'])
            ->where('site_id', config_item('site_id'))
            ->first();

            $dates['sum'] = $query['revenue'] ? $query['revenue'] : 0;
            $dates['sum'] = number_format($dates['sum'], 2);
            $dates['sum_str'] = store_currency($this->numberAbbr($dates['sum']));

            //----------------------------------------
            // Get all sum per day
            //----------------------------------------
            $query = $this->db->table('store_orders')
            ->select(array($this->db->raw('SUM(order_total) AS day_amount, FROM_UNIXTIME(order_completed_date, \'%Y-%m-%d\') AS order_completed_date')))
            ->where('order_completed_date', '>', $dates['start_unix'])
            ->where('order_completed_date', '<', $dates['end_unix'])
            ->where('site_id', config_item('site_id'))
            ->groupBy($this->db->raw('FROM_UNIXTIME(order_completed_date, \'%Y-%m-%d\')'))
            ->get();

            $dates['days'] = array();

            foreach ($query as $key => $row) {
                $dates['days'][$row['order_completed_date']] = number_format($row['day_amount'], 2);
            }
        }

        return $metric;
    }

    // ********************************************************************************* //

    private function metricOrders($metric)
    {
        foreach ($metric['periods'] as $period => &$dates) {
            //----------------------------------------
            // Active Subscriptions this month
            //----------------------------------------
            $query = $this->db->table('store_orders')
            ->select(array($this->db->raw('count(order_total) AS revenue')))
            ->where('order_completed_date', '>', $dates['start_unix'])
            ->where('order_completed_date', '<', $dates['end_unix'])
            ->where('site_id', config_item('site_id'))
            ->first();

            $dates['sum'] = $query['revenue'] ? $query['revenue'] : 0;
            $dates['sum_str'] = $dates['sum'] . ' <small>Orders</small>';

            //----------------------------------------
            // Get all sum per day
            //----------------------------------------
            $query = $this->db->table('store_orders')
            ->select(array($this->db->raw('count(order_total) AS day_amount, FROM_UNIXTIME(order_completed_date, \'%Y-%m-%d\') AS order_completed_date')))
            ->where('order_completed_date', '>', $dates['start_unix'])
            ->where('order_completed_date', '<', $dates['end_unix'])
            ->where('site_id', config_item('site_id'))
            ->groupBy($this->db->raw('FROM_UNIXTIME(order_completed_date, \'%Y-%m-%d\')'))
            ->get();

            $dates['days'] = array();

            foreach ($query as $key => $row) {
                $dates['days'][$row['order_completed_date']] = $row['day_amount'];
            }
        }

        return $metric;
    }

    // ********************************************************************************* //

    private function metricProductsSold($metric)
    {
        foreach ($metric['periods'] as $period => &$dates) {
            //----------------------------------------
            // Active Subscriptions this month
            //----------------------------------------
            $query = $this->db->table('store_orders')
            ->select(array($this->db->raw('COALESCE(SUM(order_qty), 0) AS `items`')))
            ->where('order_completed_date', '>', $dates['start_unix'])
            ->where('order_completed_date', '<', $dates['end_unix'])
            ->where('site_id', config_item('site_id'))
            ->first();

            $dates['sum'] = $query['items'] ? $query['items'] : 0;
            $dates['sum_str'] = $dates['sum'] . ' <small>Products</small>';

            //----------------------------------------
            // Get all sum per day
            //----------------------------------------
            $query = $this->db->table('store_orders')
            ->select(array($this->db->raw('COALESCE(SUM(order_qty), 0) AS `items`, FROM_UNIXTIME(order_completed_date, \'%Y-%m-%d\') AS order_completed_date')))
            ->where('order_completed_date', '>', $dates['start_unix'])
            ->where('order_completed_date', '<', $dates['end_unix'])
            ->where('site_id', config_item('site_id'))
            ->groupBy($this->db->raw('FROM_UNIXTIME(order_completed_date, \'%Y-%m-%d\')'))
            ->get();

            $dates['days'] = array();

            foreach ($query as $key => $row) {
                $dates['days'][$row['order_completed_date']] = $row['items'];
            }
        }

        return $metric;
    }

    // ********************************************************************************* //

    private function metricAverageOrder($metric)
    {
        foreach ($metric['periods'] as $period => &$dates) {
            //----------------------------------------
            // Active Subscriptions this month
            //----------------------------------------
            $query = $this->db->table('store_orders')
            ->select(array($this->db->raw('CASE COUNT(id) WHEN 0 THEN 0 ELSE SUM(order_total)/COUNT(id) END AS `average_order`')))
            ->where('order_completed_date', '>', $dates['start_unix'])
            ->where('order_completed_date', '<', $dates['end_unix'])
            ->where('site_id', config_item('site_id'))
            ->first();

            $dates['sum'] = $query['average_order'] ? $query['average_order'] : 0;
            $dates['sum_str'] = store_currency($this->numberAbbr($dates['sum']));

            //----------------------------------------
            // Get all sum per day
            //----------------------------------------
            $query = $this->db->table('store_orders')
            ->select(array($this->db->raw('CASE COUNT(id) WHEN 0 THEN 0 ELSE SUM(order_total)/COUNT(id) END AS `average_order`, FROM_UNIXTIME(order_completed_date, \'%Y-%m-%d\') AS order_completed_date')))
            ->where('order_completed_date', '>', $dates['start_unix'])
            ->where('order_completed_date', '<', $dates['end_unix'])
            ->where('site_id', config_item('site_id'))
            ->groupBy($this->db->raw('FROM_UNIXTIME(order_completed_date, \'%Y-%m-%d\')'))
            ->get();

            $dates['days'] = array();

            foreach ($query as $key => $row) {
                $dates['days'][$row['order_completed_date']] = store_currency($row['average_order']);
            }
        }

        return $metric;
    }

    // ********************************************************************************* //

    private function calculcateDateRange($date_start=false, $date_end=false)
    {
        $o = $this->metric;
        $date_start  = $date_start ? $date_start : $this->ee->input->get_post('date_start');
        $date_end = $date_end ? $date_end : $this->ee->input->get_post('date_end');
        $date_diff  = 1;

        if (version_compare(PHP_VERSION, '5.3.0', '>')) {
            $dStart = new DateTime($date_start);
            $dEnd  = new DateTime($date_end);
            $dDiff = $dStart->diff($dEnd);
            $date_diff = (int)$dDiff->format("%r%a");
        } else {
            $dStart = strtotime($date_start . ' 00:00:01');
            $dEnd = strtotime($date_end . ' 23:59:59');
            $dDiff = $dStart - $dEnd;
            $date_diff = floor($dDiff/(60*60*24));
        }

        $date_start = $date_start . ' 00:00:00';
        $date_end = $date_end . ' 23:59:59';
        $date_diff = $date_diff + 1;
        $prev_date_start = date('Y-m-d 00:00:00', strtotime("-{$date_diff} days", strtotime($date_start)));
        $prev_date_end = date('Y-m-d 23:59:59', strtotime($date_start));

        $o['periods']['current']['start'] = $date_start;
        $o['periods']['current']['start_unix'] = strtotime($date_start);
        $o['periods']['current']['end'] = $date_end;
        $o['periods']['current']['end_unix'] = strtotime($date_end);
        $o['periods']['current']['diff'] = $date_diff;
        $o['periods']['prev']['start'] = $prev_date_start;
        $o['periods']['prev']['start_unix'] = strtotime($prev_date_start);
        $o['periods']['prev']['end'] = $prev_date_end;
        $o['periods']['prev']['end_unix'] = strtotime($prev_date_end);
        $o['periods']['prev']['diff'] = $date_diff;

        $o['prev_days'] = str_replace('{days}', $date_diff, $this->ee->lang->line('store.prev_days'));


        $this->metric = $o;
    }

    // ********************************************************************************* //

    private function numberAbbr($n, $precision = 2)
    {
        if ($n < 100000) {
            // Anything less than a 100k
            $n_format = number_format($n, 0);
        }
        elseif ($n < 1000000) {
            // Anything less than a million
            $n_format = number_format($n / 1000, $precision) . 'K';
        } else if ($n < 1000000000) {
            // Anything less than a billion
            $n_format = number_format($n / 1000000, $precision) . 'M';
        } else {
            // At least a billion
            $n_format = number_format($n / 1000000000, $precision) . 'B';
        }

        return $n_format;
    }

    // ********************************************************************************* //

    private function calculateTrend($metric)
    {
        // Trend Percentage
        $currentSum = $metric['periods']['current']['sum'];
        $prevSum = $metric['periods']['prev']['sum'];

        if ($currentSum != $prevSum) {
            $metric['trend_difference'] = number_format(($prevSum - $currentSum), 2);
            $metric['trend_percent'] = number_format((1 - @($prevSum / $currentSum) ) * 100, 2). '%';
        }


        // Trend Direction
        $metric['trend_direction'] = 'neutral';
        if ($currentSum > $prevSum) $metric['trend_direction'] = 'up';
        if ($currentSum < $prevSum) $metric['trend_direction'] = 'down';

        return $metric;
    }

    // ********************************************************************************* //
}
