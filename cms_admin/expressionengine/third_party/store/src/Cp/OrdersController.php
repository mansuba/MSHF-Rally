<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

use Illuminate\Database\Query\Expression as Raw;
use Store\Model\Order;
use Store\Model\Status;
use Store\Model\PaymentMethod;
use Store\Model\Transaction;

class OrdersController extends AbstractController
{
    public $data = array();

    public function index()
    {
        $this->setTitle(lang('nav_orders'));

        // handle form submit
        if (!empty($_POST['update'])) {
            $selected = Order::where('site_id', config_item('site_id'))
                ->whereIn('id', (array) $this->ee->input->post('selected'))
                ->get();

            if (count($selected) == 0) {
                $this->ee->session->set_flashdata('message_error', lang('store.no_orders_selected'));
                $this->ee->functions->redirect(store_cp_url('orders'));
            }

            $with_selected = $this->ee->input->post('with_selected');
            if ($with_selected == '@delete') {
                $data = array();
                $data['orders'] = $selected;
                $data['post_url'] = STORE_CP.'&amp;sc=orders&amp;sm=delete';

                return $this->render('orders/delete', $data);
            } else {
                $status = Status::where('site_id', config_item('site_id'))->where('name', $with_selected)->first();
                if ($status) {
                    foreach ($selected as $order) {
                        $order->updateStatus($status, $this->ee->session->userdata['member_id']);
                    }
                }

                $this->ee->session->set_flashdata('message_success', lang('store.order_status_updated'));
                $this->ee->functions->redirect(store_cp_url('orders'));
            }
        }

        $this->ee->load->library('table');
        $this->ee->table->set_base_url(STORE_CP.'&amp;sc=orders');
        $this->ee->_mcp_reference =& $this;
        $data = $this->ee->table->datasource('index_data', array(
            'sort' => array('order_date' => 'desc'),
        ));

        $data['post_url'] = STORE_CP.AMP.'sc=orders';
        $data['order_status_select_options'] = array('@any' => lang('store.any'));
        $data['with_selected_options'] = array();

        $order_statuses = Status::where('site_id', config_item('site_id'))->orderBy('sort')->get();
        foreach ($order_statuses as $status) {
            $data['order_status_select_options'][$status->name] = store_order_status_name($status->name);
            $data['with_selected_options'][$status->name] = lang('store.mark_as').' '.store_order_status_name($status->name);
        }
        $data['with_selected_options']['@delete'] = lang('store.delete');

        $data['order_status_select_options']['@incomplete'] = lang('store.incomplete');
        $data['order_paid_select_options'] = array('any' => lang('store.any'), 'paid' => lang('store.paid'), 'unpaid' => lang('store.unpaid'), 'overpaid' => lang('store.overpaid'));
        $data['date_select_options'] = array( 'date_range' => lang('date_range'), 'today' => lang('today'), 'yesterday' => lang('store.yesterday'), 'prev_month' => lang('store.prev_month'), 'past_day' => lang('past_day'), 'past_week' => lang('past_week'), 'past_month' => lang('past_month'), 'past_six_months' => lang('past_six_months'), 'past_year' => lang('past_year'));
        $data['per_page_select_options'] = array('10' => '10 '.lang('results'), '25' => '25 '.lang('results'), '50' => '50 '.lang('results'), '75' => '75 '.lang('results'), '100' => '100 '.lang('results'), '150' => '150 '.lang('results'));
        $data['search_in_options'] = array( 'All' => lang('all'), 'order_billing_name' => lang('store.billing_name'), 'order_shipping_name' => lang('store.shipping_name'), 'member' => lang('store.member'), 'order_id' => lang('store.order_id'));

        return $this->render('orders/index', $data);
    }

    public function index_data($state, $data)
    {
        $data['no_results'] = '<p class="notice">'.lang('no_entries_matching_that_criteria').'</p>';

        $pagination = array(
            'per_page' => $this->ee->input->get_post('per_page') ?: 50,
            'total_rows' => Order::count(),
        );

        $search = array();
        $search['date_range'] = $this->ee->input->get_post('date_range');
        $search['start_date'] = 0;
        $search['end_date'] = time();
        $search['order_status'] = $this->ee->input->get_post('order_status');
        $search['order_paid_status'] = $this->ee->input->get_post('order_paid_status');

        // intentionally not using sanitize_search_terms() here, it breaks "+" signs etc
        $search['keywords'] = (string) $this->ee->input->get_post('keywords');
        $search['search_in'] = $this->ee->input->get_post('search_in');
        $search['exact_match'] = $this->ee->input->get_post('exact_match');

        // find results
        $db = $this->ee->store->db;
        $orderBy = key($state['sort']);
        $query = Order::with('member')
            ->where('site_id', config_item('site_id'))
            ->select(array(
                'store_orders.*',
                $db->raw('CONCAT_WS(" ", `billing_first_name`, `billing_last_name`) AS `name`'),
            ))->leftJoin('members', 'members.member_id', '=', 'store_orders.member_id');

        if ($search['keywords'] !== '') {
            $query->where(function($query) use ($db, $search) {
                $query->where('store_orders.id', $search['keywords'])
                    ->orWhere('order_email', 'like', '%'.$search['keywords'].'%')
                    ->orWhere('members.screen_name', 'like', '%'.$search['keywords'].'%')
                    ->orWhere($db->raw('CONCAT_WS(" ", `billing_first_name`, `billing_last_name`)'), 'like', '%'.$search['keywords'].'%');
            });
        }

        $data['order_status'] = $this->ee->input->get_post('order_status') ?: '@any';
        switch ($data['order_status']) {
            case '@incomplete':
                $query->whereNull('order_completed_date');
                break;
            case '@any':

                break;
            default:
                $query->where('order_status_name', $data['order_status']);
                break;
        }

        $data['order_paid_status'] = $this->ee->input->get_post('order_paid_status') ?: '@any';
        switch ($data['order_paid_status']) {
            case 'paid':
                $query->where('order_paid', '=', new Raw('`order_total`'));
                break;
            case 'unpaid':
                $query->where('order_paid', '<', new Raw('`order_total`'));
                break;
            case 'overpaid':
                $query->where('order_paid', '>', new Raw('`order_total`'));
                break;
            default:

                break;
        }

        $order_by = key($state['sort']);
        $direction = reset($state['sort']);
        switch ($order_by) {
            case 'screen_name':
                $query->orderBy('members.screen_name', $direction);
                break;
            default:
                $query->orderBy($order_by, $direction);
        }

        $orders = $query->take($pagination['per_page'])->skip($state['offset'])->get();

        // table headings
        $this->ee->table->set_columns(array(
            'id'                => array('header' => array('data' => lang('store.#'), 'width' => '2%')),
            'name'              => array('header' => lang('store.customer')),
            'screen_name'       => array('header' => lang('store.member')),
            'order_date'        => array('header' => lang('store.order_date')),
            'order_total'       => array('header' => lang('store.total')),
            'order_paid'        => array('header' => lang('store.paid?')),
            'order_status_name' => array('header' => lang('store.status')),
            'details'           => array('header' => lang('store.details'), 'sort' => false),
            '_check'            => array(
                'header'            => array('data' => form_checkbox('select_all', 'true', false, 'class="toggle_all"'), 'width' => '2%'),
                'sort'              => false
            )
        ));

        // table data
        $data['rows'] = array();
        foreach ($orders as $order) {
            $data['rows'][] = array(
                'id'                => $order->id,
                'name'              => $order->billing_name,
                'screen_name'       => store_member_link($order->member),
                'order_date'        => $this->ee->localize->human_time($order->order_date),
                'order_total'       => store_currency($order->order_total),
                'order_paid'        => store_order_paid($order),
                'order_status_name' => store_order_status($order),
                'details'           => '<a href="'.store_cp_url('orders', 'show', array('id' => $order->id)).'">'.lang('store.details').'</a>',
                '_check'            => '<input class="toggle" type="checkbox" name="selected[]" value="'.$order->id.'" />'
            );
        }

        $data['search'] = $search;
        $data['pagination'] = $pagination;

        return $data;
    }

    public function show()
    {
        $order_id = (int) $this->ee->input->get('id');
        $order = Order::where('site_id', config_item('site_id'))->find($order_id);
        if (empty($order)) {
            return $this->show404();
        }

        $this->addBreadcrumb(store_cp_url('orders'), lang('nav_orders'));
        $this->setTitle(lang('store.order_#').$order_id);

        $data = array();
        $data['post_url'] = STORE_CP.'&amp;sc=orders&amp;sm=show&amp;id='.$order_id;
        $data['order'] = $order;
        $data['order_fields'] = $this->ee->store->config->order_fields();
        $data['payment_method'] = PaymentMethod::where('site_id', config_item('site_id'))->where('class', $order->payment_method)->first();
        $data['transactions'] = $order->transactions()->orderBy('date', 'desc')->get();
        $data['history'] = $order->history()->orderBy('order_status_updated', 'desc')->get();
        $data['can_add_payments'] = $this->ee->store->config->has_privilege('can_add_payments');

        $data['status_select_options'] = array();
        $statuses = Status::where('site_id', config_item('site_id'))->orderBy('sort')->get();
        foreach ($statuses as $status) {
            $data['status_select_options'][$status->id] = store_order_status_name($status->name);
        }
        $data['update_status_url'] = STORE_CP.'&amp;sc=orders&amp;sm=update_status&amp;order_id='.$order_id;

        if ($payment_id = (int) $this->ee->input->post('payment_id')) {
            $this->_capture_or_refund_payment($data['order'], $payment_id);
        }

        $data['new_payment_url'] = store_cp_url('orders', 'new_payment', array('order_id' => $order->id));
        $data['print_url'] = store_cp_url('orders', 'show', array('id' => $order->id, 'print' => 1));

        $data['invoice_link'] = config_item('store_order_invoice_url');
        if ( ! empty($data['invoice_link'])) {
            $data['invoice_link'] = str_replace('ORDER_ID', $order->id, $data['invoice_link']);
            $data['invoice_link'] = str_replace('ORDER_HASH', $order->order_hash, $data['invoice_link']);
            $data['invoice_link'] = $this->ee->functions->create_url($data['invoice_link']);
        }

        $html = $this->render('orders/show', $data);

        if ($this->ee->input->get('print')) {
            $layout_data = array(
                'title' => lang('store.order_#').$order->id,
                'body' => $html,
                'class' => 'order',
            );

            echo $this->ee->load->view('print_layout', $layout_data, true);
            exit;
        } else {
            return $html;
        }
    }

    public function capture_transaction()
    {
        $transaction = Transaction::where('site_id', config_item('site_id'))->find($this->ee->input->get('id'));
        if (empty($transaction)) {
            return $this->show404();
        }

        if ($transaction->canCapture()) {
            // capture transaction and display result
            $member_id = $this->ee->session->userdata('member_id');
            $child = $this->ee->store->payments->capture_transaction($transaction, $member_id);

            $message = $child->message ? ' ('.$child->message.')' : '';
            if ($child->status == Transaction::SUCCESS) {
                $this->ee->session->set_flashdata('message_success', lang('store.payment_capture_success').$message);
            } else {
                $this->ee->session->set_flashdata('message_failure', lang('store.payment_capture_failure').$message);
            }
        } else {
            $this->ee->session->set_flashdata('message_failure', lang('store.payment_capture_failure'));
        }

        $this->ee->functions->redirect(store_cp_url('orders', 'show', array('id' => $transaction->order_id)));
    }

    public function refund_transaction()
    {
        $transaction = Transaction::where('site_id', config_item('site_id'))->find($this->ee->input->get('id'));
        if (empty($transaction)) {
            return $this->show404();
        }

        if ($transaction->canRefund()) {
            // refund transaction and display result
            $member_id = $this->ee->session->userdata('member_id');
            $child = $this->ee->store->payments->refund_transaction($transaction, $member_id);

            $message = $child->message ? ' ('.$child->message.')' : '';
            if ($child->status == Transaction::SUCCESS) {
                $this->ee->session->set_flashdata('message_success', lang('store.payment_refund_success').$message);
            } else {
                $this->ee->session->set_flashdata('message_failure', lang('store.payment_refund_failure').$message);
            }
        } else {
            $this->ee->session->set_flashdata('message_failure', lang('store.payment_refund_failure'));
        }

        $this->ee->functions->redirect(store_cp_url('orders', 'show', array('id' => $transaction->order_id)));
    }

    public function delete()
    {
        $this->ee->store->orders->delete_orders($this->ee->input->post('selected'));

        $this->ee->session->set_flashdata('message_success', lang('store.orders_deleted'));
        $this->ee->functions->redirect(store_cp_url('orders'));
    }

    public function new_payment()
    {
        $this->requirePrivilege('can_add_payments');

        $order = Order::where('site_id', config_item('site_id'))->find($this->ee->input->get('order_id'));
        if (empty($order)) {
            return $this->show404();
        }

        $this->addBreadcrumb(store_cp_url('orders'), lang('nav_orders'));
        $this->addBreadcrumb(store_cp_url('orders', 'show', array('id' => $order->id)), lang('store.order_#').$order->id);
        $this->setTitle(lang('store.new_payment'));

        $transaction = new Transaction;
        $transaction->site_id = $order->site_id;
        $transaction->order_id = $order->id;
        $transaction->date = time();
        $transaction->payment_method = 'Manual';
        $transaction->type = Transaction::PURCHASE;
        $transaction->amount = $order->order_owing;
        $transaction->status = Transaction::SUCCESS;
        $transaction->member_id = (int) $this->ee->session->userdata('member_id');

        $this->ee->form_validation->set_rules('transaction[amount]', 'lang:amount', 'required|store_currency_non_zero');
        $this->ee->form_validation->set_rules('transaction[date]', 'lang:date', 'required');

        if ($this->ee->form_validation->run() === true) {
            $post = $this->ee->input->post('transaction', true);

            $transaction->amount = store_parse_decimal($post['amount']);
            $transaction->date = $this->ee->localize->string_to_timestamp($post['date']);
            $transaction->message = $post['message'];
            $transaction->reference = $post['reference'];
            $transaction->save();

            $this->ee->store->payments->update_order_paid_total($order);

            $this->ee->session->set_flashdata('message_success', lang('store.payment_added'));
            $this->ee->functions->redirect(store_cp_url('orders', 'show', array('id' => $order->id)));
        }

        $data = array();
        $data['order'] = $order;
        $data['transaction'] = $transaction;

        $this->ee->cp->add_js_script(array('ui' => 'datepicker'));

        return $this->render('orders/new_payment', $data);
    }

    public function update_status()
    {
        $order = Order::where('site_id', config_item('site_id'))->find($this->ee->input->get('order_id'));
        if (!$order) {
            return $this->show404();
        }
        $order_url = store_cp_url('orders', 'show', array('id' => $order->id));

        $status = Status::where('site_id', config_item('site_id'))->find($this->ee->input->post('status_id', true));
        if (!$status) {
            $this->ee->session->set_flashdata('message_failure', lang('store.invalid_status'));
            $this->ee->functions->redirect($order_url);
        }

        $message = $this->ee->input->post('message', true);
        $member_id = $this->ee->session->userdata('member_id');

        $order->updateStatus($status, $member_id, $message);

        $this->ee->session->set_flashdata('message_success', lang('store.order_status_updated'));
        $this->ee->functions->redirect($order_url);
    }
}
