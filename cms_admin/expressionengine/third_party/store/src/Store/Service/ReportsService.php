<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Carbon\Carbon;
use Store\Model\Order;
use Store\Model\OrderItem;
use Store\Model\Product;
use Store\Model\Transaction;

class ReportsService extends AbstractService
{
    public function orders($start_date, $end_date, $status)
    {
        $table_head = array(
            lang('store.#'),
            lang('store.order_date'),
        );

        $order_fields = $this->ee->store->config->order_fields();
        foreach ($order_fields as $field_name => $field) {
            if (isset($field['title'])) {
                if (empty($field['title'])) {
                    unset($order_fields[$field_name]);
                } else {
                    $order_fields[$field_name] = $field['title'];
                    $table_head[] = $field['title'];
                }
            } else {
                $order_fields[$field_name] = lang('store.'.$field_name);
                $table_head[] = lang('store.'.$field_name);
            }
        }

        $table_head[] = lang('store.promo_code');
        $table_head[] = lang('store.items');
        $table_head[] = lang('store.order_qty');
        $table_head[] = lang('store.order_subtotal');
        $table_head[] = lang('store.order_discount');
        $table_head[] = lang('store.shipping');
        $table_head[] = lang('store.order_tax');
        $table_head[] = lang('store.order_total');
        $table_head[] = lang('store.paid');
        $table_head[] = lang('store.balance_due');
        $table_head[] = lang('store.paid?');
        $table_head[] = lang('store.date_paid');

        $sum = array();
        $sum['balance_due'] = 0;

        $data = array(
            'table_head' => $table_head,
            'table_data' => array(),
        );

        $orders = $this->get_orders_by_date($start_date, $end_date, $status);

        $sum = array(
            'order_qty' => 0,
            'order_subtotal' => 0,
            'order_discount' => 0,
            'order_shipping' => 0,
            'order_tax' => 0,
            'order_total' => 0,
            'order_paid' => 0,
            'order_owing' => 0,
        );

        foreach ($orders as $key => $order) {
            $sum['order_qty'] += $order['order_qty'];
            $sum['order_subtotal'] += $order['order_subtotal_val'];
            $sum['order_discount'] += $order['order_discount_val'];
            $sum['order_shipping'] += $order['order_shipping_val'];
            $sum['order_tax'] += $order['order_shipping_tax_val'] + $order['order_subtotal_tax_val'];
            $sum['order_total'] += $order['order_total_val'];
            $sum['order_paid'] += $order['order_paid_val'];
            $sum['order_owing'] += $order['order_owing_val'];

            $order_items = '';
            foreach ($order['items'] as $key => $item) {
                if (empty($item)) continue;

                $order_items .= $item['title']."\n";
            }
            $order_items = trim($order_items);

            $table_row = array(
                $order['order_id'],
                $this->ee->localize->human_time($order['order_date'])
            );

            foreach ($order_fields as $field_name => $field) {
                $table_row[] = $order[$field_name];
            }

            $table_row[] = $order['promo_code'];
            $table_row[] = $order_items;
            $table_row[] = $order['order_qty'];
            $table_row[] = $order['order_subtotal'];
            $table_row[] = $order['order_discount'];
            $table_row[] = $order['order_shipping'];
            $table_row[] = $order['order_tax'];
            $table_row[] = $order['order_total'];
            $table_row[] = $order['order_paid'];
            $table_row[] = $order['order_owing'];
            $table_row[] = $order['is_order_paid'] ? lang('yes') : lang('no');
            $table_row[] = empty($order['order_paid_date']) ? '' : $this->ee->localize->human_time($order['order_paid_date']);

            $data['table_data'][] = $table_row;
        }

        $row_totals = array('<strong>'.lang('store.totals').'</strong>', '');
        foreach ($order_fields as $field_name => $field) {
            $row_totals[] = '';
        }
        $row_totals[] = '';
        $row_totals[] = '';

        $row_totals[] = '<strong>'.$sum['order_qty'].'</strong>';
        $row_totals[] = '<strong>'.store_currency($sum['order_subtotal']).'</strong>';
        $row_totals[] = '<strong>'.store_currency($sum['order_discount']).'</strong>';
        $row_totals[] = '<strong>'.store_currency($sum['order_shipping']).'</strong>';
        $row_totals[] = '<strong>'.store_currency($sum['order_tax']).'</strong>';
        $row_totals[] = '<strong>'.store_currency($sum['order_total']).'</strong>';
        $row_totals[] = '<strong>'.store_currency($sum['order_paid']).'</strong>';
        $row_totals[] = '<strong>'.store_currency($sum['order_owing']).'</strong>';
        $row_totals[] = '';
        $row_totals[] = '';

        $data['table_data'][] = $row_totals;

        return $data;
    }

    //Returns an array of all sales occuring between the start and end dates specified
    public function sales_by_date($start_date, $end_date)
    {
        $table_head = array(
            lang('store.#'),
            lang('store.order_date'),
            lang('store.billing_name'),
            lang('store.sku'),
            lang('store.product'),
            lang('store.item_qty'),
            lang('store.item_price'),
            lang('store.item_subtotal'),
            lang('store.order_qty'),
            lang('store.order_subtotal'),
            lang('store.shipping'),
            lang('store.order_tax'),
            lang('store.order_total'),
        );

        // figure out which payment methods were used
        $sum = array();
        $payment_methods = $this->get_order_payment_methods($start_date, $end_date);

        // always display maunal payments last
        unset($payment_methods['manual']);
        $payment_methods['manual'] = 'manual';

        foreach ($payment_methods as $key => $method) {
            $table_head[] = $method;
            $sum[$method] = 0;
        }

        $table_head[] = lang('store.owing');
        $table_head[] = lang('store.date_paid');
        $sum['balance_due'] = 0;

        $data = array(
            'table_head' => $table_head,
            'table_data' => array(),
        );

        $orders = $this->get_orders_by_date($start_date, $end_date);

        $sum_order_items = 0;
        $sum_subtotals = 0;
        $sum_shipping = 0;
        $sum_tax = 0;
        $sum_totals = 0;
        $sum_items_qty = 0;
        $sum_items_subtotal = 0;

        foreach ($orders as $key => $order) {
            foreach ($order['items'] as $key => $item) {
                if (empty($item)) continue;

                $item_title = $item['title'];
                if ( ! empty($item['modifiers_desc'])) $item_title .= NBS.'('.$item['modifiers_desc'].')';

                $table_row = array(
                $order['order_id'],
                $this->ee->localize->human_time($order['order_date']),
                $order['billing_name'],
                $item['sku'],
                $item_title,
                $item['item_qty'],
                $item['price'],
                $item['item_subtotal'],
                '', // order qty
                '', // order subtotal
                '', // shipping total
                '', // tax total
                '', // order total
                );

                foreach ($payment_methods as $method) {
                    $table_row[] = ''; // each plugin
                }
                $table_row[] = ''; // amount owed
                $table_row[] = ''; // date paid

                $data['table_data'][] = $table_row;

                $sum_items_qty += $item['item_qty'];
                $sum_items_subtotal += $item['item_subtotal_val'];
            }

            $sum_order_items += $order['order_qty'];
            $sum_subtotals += $order['order_subtotal_val'];
            $sum_shipping += $order['order_shipping_val'];
            $sum_tax += $order['order_tax_val'];
            $sum_totals += $order['order_total_val'];

            $table_row = array(
                $order['order_id'],
                $this->ee->localize->human_time($order['order_date']),
                $order['billing_name'],
                '', // $item['sku']
                '', // $item['title'].' ('.$item['modifiers_desc'].')'
                '', // $item['item_qty']
                '', // $item['price']
                '', // $item['item_subtotal']
                $order['order_qty'],
                $order['order_subtotal'],
                $order['order_shipping'],
                $order['order_tax'],
                $order['order_total']
            );

            foreach ($payment_methods as $method) {
                if (isset($order[$method])) {
                    $table_row[] = store_currency($order[$method]);
                    $sum[$method] += $order[$method]; // sum amount paid for individual payment methods
                } else {
                    $table_row[] = store_currency(0);
                }
            }

            $table_row[] = $order['order_owing'];
            $table_row[] = empty($order['order_paid_date']) ? '' : $this->ee->localize->human_time($order['order_paid_date']);
            $sum['balance_due'] += $order['order_owing_val'];

            $data['table_data'][] = $table_row;
        }

        $row_totals = array(
            array('data' => '<strong>'.lang('store.totals').'</strong>', 'colspan' => 5),
            array('data' => '<strong>'.$sum_items_qty.'</strong>'),
            array('data' => ''),
            array('data' => '<strong>'.store_currency($sum_items_subtotal).'</strong>'),
            array('data' => '<strong>'.$sum_order_items.'</strong>'),
            array('data' => '<strong>'.store_currency($sum_subtotals).'</strong>'),
            array('data' => '<strong>'.store_currency($sum_shipping).'</strong>'),
            array('data' => '<strong>'.store_currency($sum_tax).'</strong>'),
            array('data' => '<strong>'.store_currency($sum_totals).'</strong>'),
        );

        foreach ($payment_methods as $method) {
            $row_totals[] = array('data' =>'<strong>'.store_currency($sum[$method]).'</strong>');
        }

        $row_totals[] = array('data' =>'<strong>'.store_currency($sum['balance_due']).'</strong>');
        $row_totals[] = array('data' =>'');

        $data['table_data'][] = $row_totals;

        return $data;
    }

    public function stock_value($stock_inventory_options)
    {
        switch ($stock_inventory_options) {
            case 'product title':
                $stock_inventory_options = 'title';
                break;
            case 'stockcode':
                $stock_inventory_options = 'sku';
                break;
        }

        $data = array(
            'table_head' => array(lang('store.sku'), lang('store.product_title'), lang('store.price'), lang('store.current_stock_level'), lang('store.total_stock_value')),
            'table_data' => array(),
        );

        $query = $this->stock_inventory($stock_inventory_options);

        $sum_total_qty = 0;
        $sum_total_value = 0;
        foreach ($query as $key => $row) {
            $row['stock_level'] = ($row['stock_level'] < 0) ? 0 : $row['stock_level'];
            $sum_total_qty += $row['stock_level'];
            $sum_total_value += $row['stock_level'] * $row['price_val'];
            $data['table_data'][] = array(
                $row['sku'],
                isset($row['description']) ? array('data' => $row['title'].NBS.'('.$row['description'].')', 'width' => '40%') : array('data' => $row['title'], 'width' => '40%'),
                $row['price'],
                isset($row['stock_level']) ? $row['stock_level'] : 0,
                store_currency($row['stock_level'] * $row['price_val']),
            );
        }
        $data['table_data'][] = array( array('data'=>'<strong>'.lang('store.totals').'</strong>', 'colspan'=>"3"), array('data' =>'<strong>'.$sum_total_qty.'</strong>'), array('data' =>'<strong>'.store_currency($sum_total_value).'</strong>'));

        return $data;
    }

    public function stock_products($start_date, $end_date, $orderby_option)
    {
        $data = array(
            'table_head' => array(lang('store.sku'), lang('store.product_title'), lang('store.quantity_sold'), lang('store.current_price'), lang('store.average_price'), lang('store.net_sales')),
            'table_data' => array(),
        );

        $query = $this->get_all_selling_stock($start_date, $end_date, $orderby_option);

        $sum_qty = 0;
        $sum_net_totals = 0;

        foreach ($query as $key => $row) {
            $sum_qty += $row['item_qty'];
            $sum_net_totals += $row['item_subtotal'];

            $product_title = $row['order_item_title'];
            if ( ! empty($row['modifiers_desc'])) $product_title .= NBS.'('.$row['modifiers_desc'].')';

            $data['table_data'][] = array(
                $row['sku'],
                $product_title,
                $row['item_qty'],
                store_currency($row['item_current_price']),
                store_currency($row['item_avg_price']),
                store_currency($row['item_subtotal']),
            );
        }
        $data['table_data'][] = array( array('data'=>'<strong>'.lang('store.totals').'</strong>', 'colspan'=>"2"), array('data' =>'<strong>'.$sum_qty.'</strong>'), '', '', array('data' =>'<strong>'.store_currency($sum_net_totals).'</strong>'));

        return $data;
    }

    public function table_from_csv($table_head, $table_data)
    {
        foreach ($table_data as $row) $string = isset($row['data']) ? $row['data'] : $row;
    }

    public function get_orders_by_date($start_date, $end_date, $status = NULL)
    {
        $query = Order::with('items')
            ->where('site_id', config_item('site_id'))
            ->where('order_completed_date', '>', 0)
            ->where('order_date', '>=', $start_date)
            ->where('order_date', '<', $end_date);

        if (!empty($status)) {
            $query->where('order_status_name', $status);
        }

        $query->orderBy('order_date', 'desc');

        $orders = array();
        foreach ($query->get() as $order) {
            $orders[$order->id] = $order->toTagArray();
            $orders[$order->id]['shipping_state'] = $orders[$order->id]['shipping_state_name'];
            $orders[$order->id]['shipping_country'] = $orders[$order->id]['shipping_country_name'];
            $orders[$order->id]['billing_state'] = $orders[$order->id]['billing_state_name'];
            $orders[$order->id]['billing_country'] = $orders[$order->id]['billing_country_name'];
        }

        if (empty($orders)) {
            return array();
        }

        // add payment totals
        $payment_totals = $this->ee->db
            ->select('order_id, payment_method, sum(amount) as amount')
            ->from('store_transactions')
            ->where_in('order_id', array_keys($orders))
            ->where_in('type', array(Transaction::PURCHASE, Transaction::CAPTURE))
            ->where('status', Transaction::SUCCESS)
            ->group_by('order_id')
            ->group_by('payment_method')
            ->get()->result_array();

        foreach ($payment_totals as $row) {
            $orders[$row['order_id']][$row['payment_method']] = $row['amount'];
        }

        return $orders;
    }

    public function get_order_payment_methods($start_date, $end_date, $status = NULL)
    {
        $this->ee->db->distinct()
            ->select('payment_method')
            ->from('store_orders o')
            ->where('o.site_id', config_item('site_id'))
            ->where('o.order_completed_date > 0')
            ->where('o.order_date >=', $start_date)
            ->where('o.order_date <', $end_date);

        if ($status) {
            $this->ee->db->where('order_status', $status);
        }

        $query = $this->ee->db->get()->result_array();
        if (empty($query)) {
            return array();
        }

        $result = array();
        foreach ($query as $key => $row) {
            $result[$row['payment_method']] = $row['payment_method'];
        }

        return $result;
    }

    public function get_all_selling_stock($start_date, $end_date, $orderby_option)
    {
        $this->ee->db->select('oi.sku, oi.title as order_item_title, oi.modifiers, sum(item_qty) as item_qty, sum(item_subtotal) as item_subtotal, sum(item_tax) as item_tax, sum(item_total) as item_total')
            ->from('store_order_items oi')
            ->join('store_orders o', 'oi.order_id = o.id')
            ->join('store_stock s', 'oi.sku = s.sku', 'left')
            ->join('channel_titles t', 's.entry_id = t.entry_id', 'left')
            ->where('o.site_id', config_item('site_id'))
            ->where('o.order_completed_date > 0')
            ->where('o.order_date >', $start_date)
            ->where('o.order_date <', $end_date)
            ->group_by('oi.sku')
            ->order_by($orderby_option, 'desc');

        $result = $this->ee->db->get()->result_array();

        $item_model = new OrderItem;
        $all_sku_prices = $this->get_current_sku_prices();
        foreach ($result as $key => $row) {
            $result[$key]['item_current_price'] = isset($all_sku_prices[$row['sku']]) ? $all_sku_prices[$row['sku']] : 0;
            $result[$key]['item_avg_price'] = $row['item_subtotal'] / $row['item_qty'];
            $result[$key]['channel_title'] = empty($row['channel_title']) ? $row['order_item_title'] : $row['channel_title'];
            $result[$key]['modifiers'] = $item_model->getModifiersAttribute($row['modifiers']);
            $modifiers_desc = array();

            foreach ($result[$key]['modifiers'] as $mod_data) {
                // only display modifiers affecting the SKU
                if ( ! empty($mod_data) AND $mod_data['modifier_type'] == 'var') {
                    $modifiers_desc[] = "<strong>{$mod_data['modifier_name']}</strong>: {$mod_data['modifier_value']}";
                }
            }

            $result[$key]['modifiers_desc'] = implode(', ', $modifiers_desc);
        }

        return $result;
    }

    public function get_current_sku_prices()
    {
        $this->ee->db->select('s.entry_id, s.sku, p.price, sum(po.opt_price_mod) as mod_price')
            ->from('store_stock s')
            ->join('store_products p', 's.entry_id = p.entry_id')
            ->join('store_stock_options so', 's.sku = so.sku', 'left')
            ->join('store_product_options po', 'po.product_opt_id = so.product_opt_id', 'left')
            ->group_by('s.sku');
        $result = $this->ee->db->get()->result_array();

        $prices = array();
        foreach ($result as $key => $row) $prices[$row['sku']] = $row['mod_price'] + $row['price'];

        return $prices;
    }

    public function stock_inventory($order_by_option)
    {
        $query = Product::join('channel_titles', 'channel_titles.entry_id', '=', 'store_products.entry_id')
            ->join('store_stock', 'store_stock.entry_id', '=', 'store_products.entry_id')
            ->select(array('store_products.*', 'store_stock.*', 'store_stock.id AS stock_id', 'channel_titles.title'))
            ->groupBy('store_stock.sku')
            ->orderBy($order_by_option);

        $stock = array();
        foreach ($query->get() as $row) {
            $stock[$row->id] = $row->toTagArray();
        }

        $options = $this->ee->db->select('so.stock_id, group_concat(po.opt_name order by po.product_mod_id desc, po.product_opt_id asc separator " - ") as description')
            ->from('store_stock_options so')
            ->join('store_product_options po', 'po.product_opt_id = so.product_opt_id')
            ->where_in('so.stock_id', array_keys($stock))
            ->group_by('so.stock_id')
            ->get()->result();

        foreach ($options as $row) {
            $stock[$row->stock_id]['description'] = $row->description;
        }

        return $stock;
    }

    public function getDashboardStats($period)
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

    public function getDashboardGraphData($period)
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
