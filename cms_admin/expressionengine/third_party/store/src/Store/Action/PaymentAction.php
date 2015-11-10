<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Action;

use Store\FormValidation;
use Store\Model\Order;
use Store\Model\Transaction;

class PaymentAction extends AbstractAction
{
    public static $form_errors;

    public function perform()
    {
        $order = Order::where('site_id', config_item('site_id'))
            ->where('order_hash', $this->ee->input->post('order_hash'))
            ->first();

        if (empty($order)) {
            return $this->ee->output->show_user_error('general', array(lang('not_authorized')));
        }

        if ($order->is_order_paid) {
            $this->ee->session->set_flashdata('store_payment_error', lang('order_already_paid'));
            $this->ee->functions->redirect($order->parsed_return_url);
        }

        $order->payment_method = $this->ee->input->post('payment_method', true);
        $order->return_url = $this->get_return_url();
        $order->cancel_url = $this->ee->store->store->create_url();

        $this->ee->form_validation = new FormValidation;
        $this->ee->form_validation->add_rules_from_params($this->form_params());
        $this->ee->form_validation->add_rules('payment_method', 'lang:store.payment_method', 'required|valid_payment_method');

        if ($this->ee->form_validation->run()) {
            $order->save();

            // process payment info
            $credit_card = $this->ee->input->post('payment');
            $transaction = $this->ee->store->payments->new_transaction($order);
            $transaction->amount = $order->order_owing;
            $transaction->payment_method = $order->payment_method;
            $this->ee->store->payments->process_payment($order, $transaction, $credit_card);
        }

        static::$form_errors = $this->ee->form_validation->error_array();

        if ($this->form_param('error_handling') != 'inline') {
            $this->ee->output->show_user_error(false, static::$form_errors);
        }

        return $this->ee->core->generate_page();
    }
}
