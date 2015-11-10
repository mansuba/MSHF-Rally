<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

use Store\Model\Order;

class CustomersController extends AbstractController
{
    public function index()
    {
        $this->setTitle(lang('nav_customers'));

        $this->ee->load->library('table');
        $this->ee->table->set_base_url(STORE_CP.'&amp;sc=customers');
        $this->ee->_mcp_reference =& $this;
        $data = $this->ee->table->datasource('index_data', array(
            'sort' => array('customer_name' => 'asc'),
        ));

        $data['post_url'] = STORE_CP.'&amp;sc=customers';
        $data['per_page_select_options'] = array('10' => '10 '.lang('results'), '25' => '25 '.lang('results'), '50' => '50 '.lang('results'), '75' => '75 '.lang('results'), '100' => '100 '.lang('results'), '150' => '150 '.lang('results'));

        return $this->render('customers/index', $data);
    }

    public function index_data($state, $data)
    {
        $search = array();
        $search['keywords'] = (string) $this->ee->input->get_post('keywords');

        // find results
        $query = Order::where('order_completed_date', '>', 0)
            ->groupBy('order_email')
            ->select(array(
                'store_orders.*',
                $this->ee->store->db->raw('CONCAT_WS(" ", `billing_first_name`, `billing_last_name`) AS `customer_name`'),
                $this->ee->store->db->raw('COUNT(`id`) AS customer_orders'),
                $this->ee->store->db->raw('SUM(`order_total`) AS customer_revenue'),
            ));

        if ($search['keywords'] !== '') {
            $query->where(function($query) use ($search) {
                $query->where('store_orders.order_email', 'like', '%'.$search['keywords'].'%')
                    ->orWhere('store_orders.billing_first_name', 'like', '%'.$search['keywords'].'%')
                    ->orWhere('store_orders.billing_last_name', 'like', '%'.$search['keywords'].'%');
            });
        }

        $order_by = key($state['sort']);
        $direction = reset($state['sort']);
        switch ($order_by) {
            default:
                $query->orderBy($order_by, $direction);
        }

        $per_page = $this->ee->input->get_post('per_page') ?: 50;
        $customers = $query->take($per_page)
            ->skip($state['offset'])
            ->get();

        // table headings
        $this->ee->table->set_columns(array(
            'customer_name'     => array('header' => lang('store.customer_name')),
            'order_email'       => array('header' => lang('store.order_email')),
            'customer_orders'   => array('header' => lang('store.customer_orders')),
            'customer_revenue'  => array('header' => lang('store.customer_revenue')),
        ));

        // table data
        $data['rows'] = array();
        foreach ($customers as $customer) {
            $customer_url = BASE.AMP.STORE_CP.'&amp;sc=orders&amp;keywords='.urlencode($customer->order_email);
            $data['rows'][] = array(
                'customer_name'     => '<a href="'.$customer_url.'">'.$customer->customer_name.'</a>',
                'order_email'       => $customer->order_email,
                'customer_orders'   => array('data' => $customer->customer_orders, 'class' => 'store_numeric'),
                'customer_revenue'  => array('data' => store_currency($customer->customer_revenue), 'class' => 'store_numeric'),
            );
        }

        $data['no_results'] = '<p class="notice">'.lang('no_entries_matching_that_criteria').'</p>';
        $data['search'] = $search;
        $data['pagination'] = array(
            'per_page' => $per_page,
            'total_rows' => Order::distinct()->where('order_completed_date', '>', 0)->count('order_email'),
        );

        return $data;
    }
}
