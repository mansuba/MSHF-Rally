<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Tag;

use Store\Action\PaymentAction;
use Store\Model\Order;
use Store\Model\Transaction;

class PaymentTag extends AbstractTag
{
    public function parse()
    {
        $this->tmpl_secure_check();

        // find order
        if ($this->param('order_id') !== false || $this->param('order_hash') !== false) {
            $this->params['limit'] = 1;
            $this->params['offset'] = 0;

            $order = $this->get_orders_query()->first();
            if (empty($order)) {
                return $this->no_results('no_orders');
            }
        } else {
            $order = $this->ee->store->orders->get_cart();
            if ($order->isEmpty()) {
                return $this->no_results('no_items');
            }
        }

        // check for valid payment method
        $payment_method = $this->ee->store->payments->find_payment_method($this->param('payment_method'));
        if (!$payment_method) {
            return lang('valid_payment_method');
        }

        $tag_vars = array($order->toTagArray());
        $this->add_payment_tag_vars($tag_vars);

        // display any inline form validation errors
        if (is_array(PaymentAction::$form_errors)) {
            foreach (PaymentAction::$form_errors as $key => $message) {
                $tag_vars[0]["error:$key"] = $this->wrap_error($message);
            }
        }

        // are we dealing with a transparent redirect gateway?
        if (!empty($payment_method->createGateway()->transparentRedirect)) {
            // look for existing pending transaction
            $transaction = $order->transactions()->where('payment_method', $payment_method->class)
                ->whereIn('type', array(Transaction::PURCHASE, Transaction::AUTHORIZE))
                ->where('status', Transaction::PENDING)->first();
            if ($transaction) {
                $transaction->date = time();
            } else {
                $transaction = $this->ee->store->payments->new_transaction($order);
            }

            // send request to build payment form
            $transaction->amount = $order->order_owing;
            $transaction->payment_method = $payment_method->class;
            $request = $this->ee->store->payments->process_payment($order, $transaction, null, false);
            try {
                $response = $request->send();
                if (!$response->isRedirect()) {
                    return $response->getMessage();
                }
                $transaction->status = Transaction::REDIRECT;
                $transaction->save();
            } catch (\Exception $e) {
                return $e->getMessage();
            }

            // update order return and cancel urls
            if ($this->param('return') !== false) {
                $url = $this->ee->functions->create_url($this->param('return'));
                if ($this->param('secure_return') == 'yes') {
                    $url = str_replace('http://', 'https://', $url);
                }

                $order->return_url = $url;
            }
            $order->cancel_url = $this->ee->store->store->create_url();
            $order->save();

            // start form output
            $attributes = array(
                'method' => $response->getRedirectMethod(),
                'action' => $response->getRedirectUrl(),
                'id' => $this->param('form_id'),
                'name' => $this->param('form_name'),
                'class' => $this->param('form_class'),
            );

            $attributes = array_merge($attributes, $this->html_params());

            $out = $this->build_form($attributes, $response->getRedirectData());
        } else {
            // start form output
            $hidden_fields = array(
                'order_hash' => $tag_vars[0]['order_hash'],
                'payment_method' => $payment_method->class,
                'return_url' => $this->ee->uri->uri_string,
            );

            if ($this->param('return')) {
                $hidden_fields['return_url'] = $this->param('return');
            }

            $out = $this->form_open('act_payment', $hidden_fields);
        }

        // parse tagdata variables
        $out .= $this->parse_variables($tag_vars);

        // end form output and return
        $out .= '</form>';

        return $out.$this->track_conversion($tag_vars);
    }
}
