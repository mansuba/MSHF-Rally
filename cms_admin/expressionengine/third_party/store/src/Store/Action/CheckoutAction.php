<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Action;

use Store\Exception\CartException;
use Store\FormValidation;
use Store\Model\Order;
use Store\Model\Transaction;

class CheckoutAction extends AbstractAction
{
    public static $form_errors;

    public function perform()
    {
        $this->ee->lang->loadfile('myaccount');

        // allow XID reuse (if order is complete we manually delete the XID)
        $this->ee->security->restore_xid();

        // don't submit order when submitting add to cart form
        if (!empty($_POST['nosubmit'])) {
            unset($_POST['submit']);
        }

        if (isset($_POST['entry_id'])) {
            // simple add to cart form, add details to items array
            $_POST['items'] = array($_POST);
        }

        if ($this->ee->input->post('empty_cart')) {
            $this->ee->store->orders->clear_cart_cookie();

            // are there any items to add after emptying the cart?
            $add_items = false;
            if (isset($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (isset($item['entry_id'])) {
                        $add_items = true;
                    }
                }
            }

            // only finish now if there are no new products to add to the cart
            if (!$add_items) {
                $return_url = $this->ee->store->store->create_url($this->ee->input->post('RET'));

                return $this->ee->functions->redirect($return_url);
            }
        }

        $form_params = $this->form_params();
        $update_data = $this->ee->security->xss_clean($_POST);
        $cart = $this->ee->store->orders->get_cart();

        try {
            $cart->fill($update_data, $form_params);
        } catch (CartException $e) {
            $this->ee->output->show_user_error(false, array("Store: ".$e->getMessage()));
        }

        // remember whether return_url in cart should be https
        if (isset($update_data['return_url'])) {
            $cart->return_url = $this->get_return_url();
        }
        $cart->cancel_url = $this->ee->store->store->create_url();

        // validate form input
        $address_fields = array('name', 'first_name', 'last_name',
            'address1', 'address2', 'address3', 'city',
            'state', 'region', 'country', 'postcode', 'phone', 'company');

        foreach ($address_fields as $field) {
            // shorthand for requiring both billing and shipping fields
            if (isset($form_params['rules:'.$field])) {
                $rules = $form_params['rules:'.$field];
                $form_params['rules:billing_'.$field] = $rules;
                $form_params['rules:shipping_'.$field] = $rules;
                unset($form_params['rules:'.$field]);
            }

            // ignore shipping rules when shipping same as billing, and vice versa
            if ($cart->shipping_same_as_billing) {
                unset($form_params['rules:shipping_'.$field]);
            }
            if ($cart->billing_same_as_shipping) {
                unset($form_params['rules:billing_'.$field]);
            }
        }

        $this->ee->form_validation = new FormValidation;
        $this->ee->form_validation->add_rules_from_params($form_params);
        $this->ee->form_validation->add_rules('payment_method', 'lang:store.payment_method', 'valid_payment_method');
        $this->ee->form_validation->add_rules('shipping_method', 'lang:store.shipping_method', 'valid_shipping_method');

        // on final checkout step, payment_method is required
        if (isset($update_data['submit'])) {
            $this->ee->form_validation->add_rules('payment_method', 'lang:store.payment_method', 'required');
        }

        // accept terms checkbox
        if (isset($update_data['accept_terms'])) {
            $this->ee->form_validation->add_rules('accept_terms', 'lang:accept_terms', 'require_accept_terms');
        }

        // validate email address
        $this->ee->form_validation->add_rules('order_email', 'lang:store.order_email', 'valid_email');

        // if registering member, ensure email does not already exist
        if ($cart->register_member) {
            $this->ee->form_validation->add_rules('order_email', 'lang:store.order_email', 'valid_user_email');
            $this->ee->form_validation->add_rules('username', 'lang:username', 'valid_username');
            $this->ee->form_validation->add_rules('screen_name', 'lang:screen_name', 'valid_screen_name');
            $this->ee->form_validation->add_rules('password', 'lang:password', 'valid_password');
            $this->ee->form_validation->add_rules('password_confirm', 'lang:password', 'matches[password]');
        }

        // trigger unique checks for member registration fields
        $this->ee->form_validation->set_old_value('username', ' ');
        $this->ee->form_validation->set_old_value('email', ' ');
        $this->ee->form_validation->set_old_value('screen_name', ' ');

        // validate promo code
        if (isset($update_data['promo_code'])) {
            $this->ee->form_validation->add_rules('promo_code', 'lang:store.promo_code', 'valid_promo_code');
        }

        if ($this->ee->form_validation->run('', $cart->toTagArray())) {
            // update cart
            $cart->recalculate();
            $this->ee->store->orders->set_cart_cookie();

            // where to next?
            if (!$cart->isEmpty() && isset($_POST['submit'])) {
                if (config_item('store_force_member_login') AND
                    empty($this->ee->session->userdata['member_id']) AND
                    !$cart->register_member)
                {
                    // admin has set order submission to members only,
                    // but customer is not logged in
                    $this->ee->output->show_user_error(false, array(lang('store.submit_order_not_logged_in')));
                }

                // prevent duplicate payment submissions
                $this->ee->security->delete_xid();

                // set submit cookie (triggers conversion tracking code on order summary page)
                $this->ee->input->set_cookie('store_cart_submit', $cart->order_hash, 0);

                if ($cart->is_order_paid) {
                    // skip payment for free orders
                    $cart->markAsComplete();

                    return $this->ee->functions->redirect($cart->parsed_return_url);
                }

                // submit to payment gateway (this will either redirect to a third party site,
                // or the order's return or cancel url)
                $credit_card = $this->ee->input->post('payment');
                $transaction = $this->ee->store->payments->new_transaction($cart);
                $transaction->amount = $cart->order_owing;
                $transaction->payment_method = $cart->payment_method;
                $this->ee->store->payments->process_payment($cart, $transaction, $credit_card);

            } elseif (!$cart->isEmpty() && isset($_POST['next']) && isset($_POST['next_url'])) {
                $this->ee->functions->redirect($this->get_return_url('next_url'));
            }

            // AJAX requests return JSON
            if ($this->ee->input->is_ajax_request()) {
                $this->ee->output->send_ajax_response($cart->toTagArray());
            }

            // default is to update totals and return
            if (empty($_POST['nosubmit'])) {
                $return_url = $this->ee->store->store->create_url($this->ee->input->post('RET'));
            } else {
                $return_url = $this->get_return_url();
            }
            $this->ee->functions->redirect($return_url);
        }

        static::$form_errors = $this->ee->form_validation->error_array();

        if ($this->ee->input->is_ajax_request()) {
            $this->ee->output->send_ajax_response(array_merge($cart->toTagArray(), static::$form_errors));
        }

        if ($this->form_param('error_handling') != 'inline') {
            $this->ee->output->show_user_error(false, static::$form_errors);
        }

        return $this->ee->core->generate_page();
    }
}
