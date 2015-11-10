<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Omnipay\Common\CreditCard;
use Omnipay\Common\Exception\OmnipayException;
use Omnipay\Common\Helper as OmnipayHelper;
use Omnipay\Common\ItemBag;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Omnipay;
use Store\Exception\CartException;
use Store\Model\Order;
use Store\Model\PaymentMethod;
use Store\Model\Status;
use Store\Model\Transaction;

/**
 * Payments Service
 */
class PaymentsService extends AbstractService
{
    /**
     * Create a new transaction
     */
    public function new_transaction(Order $order)
    {
        $transaction = new Transaction;
        $transaction->site_id = $order->site_id;
        $transaction->order_id = $order->id;
        $transaction->date = time();
        $transaction->status = Transaction::PENDING;

        return $transaction;
    }

    /**
     * Find all installed payment gateways
     *
     * @return array An array of Omnipay gateway names
     */
    public function get_payment_gateways()
    {
        $unsupported = array('MultiSafepay');
        $gateways = array_diff(Omnipay::find(), $unsupported);

        if ($this->ee->extensions->active_hook('store_payment_gateways')) {
            $gateways = $this->ee->extensions->call('store_payment_gateways', $gateways);
        }

        usort($gateways, 'strcasecmp');

        return $gateways;
    }

    /**
     * Find a payment method record
     *
     * @return Record. Null if not found or missing gateway class
     */
    public function find_payment_method($name)
    {
        $payment_method = PaymentMethod::where('site_id', config_item('site_id'))
            ->where('class', $name)->where('enabled', 1)->first();

        if ($payment_method) {
            $gateways = $this->get_payment_gateways();
            $real_class = OmnipayHelper::getGatewayClassName($payment_method->class);
            if (in_array($payment_method->class, $gateways) && class_exists($real_class)) {
                return $payment_method;
            }
        }
    }

    /**
     * Create and initialize a payment gateway
     */
    public function load_payment_method($name)
    {
        $payment_method = $this->find_payment_method($name);

        if (!$payment_method) {
            throw new CartException(lang('valid_payment_method'));
        }

        return $payment_method->createGateway();
    }

    /**
     * Add a new payment for an order
     */
    public function process_payment(Order $order, $transaction, $card_data, $send = true)
    {
        // load driver
        $gateway = $this->load_payment_method($transaction->payment_method);

        // decide which action to use
        if ('Manual' === $gateway->getShortName()) {
            $action = 'authorize';
        } elseif ('authorize' === config_item('store_cc_payment_method') &&
            $gateway->supportsAuthorize()) {
            $action = 'authorize';
        } else {
            $action = 'purchase';
        }

        // save transaction
        $transaction->type = $action;
        $transaction->save();

        $request = $gateway->$action($this->build_payment_request($transaction));
        $request->setCard($this->build_payment_credit_card($order, $card_data));
        $request->setItems($this->build_payment_items($order));

        // set token and issuer directly on request if available
        if (isset($card_data['token'])) {
            $request->setToken($card_data['token']);
        }
        if (isset($card_data['issuer']) && method_exists($request, 'setIssuer')) {
            $request->setIssuer($card_data['issuer']);
        }

        if ($send) {
            $this->send_payment_request($request, $transaction);
        } else {
            return $request;
        }
    }

    /**
     * Handle off-site payment return
     */
    public function complete_payment(Transaction $transaction)
    {
        $order = $transaction->order;

        // ignore already processed transactions
        if ($transaction->status != Transaction::REDIRECT) {
            if ($transaction->status == Transaction::SUCCESS) {
                $this->ee->functions->redirect($order->parsed_return_url);
            } else {
                $this->ee->session->set_flashdata('store_payment_error', $transaction->message);
                $this->ee->functions->redirect($order->cancel_url);
            }
        }

        // load payment driver
        $gateway = $this->load_payment_method($transaction->payment_method);

        $action = 'complete'.ucfirst($transaction->type);
        $supportsAction = 'supports'.ucfirst($action);
        if ($gateway->$supportsAction()) {
            $request = $gateway->$action($this->build_payment_request($transaction));
            $request->setItems($this->build_payment_items($order));

            $this->send_payment_request($request, $transaction);
        } else {
            exit('Payment return not supported');
        }
    }

    public function capture_transaction(Transaction $transaction, $member_id = null)
    {
        return $this->process_capture_or_refund($transaction, Transaction::CAPTURE, $member_id);
    }

    public function refund_transaction(Transaction $transaction, $member_id = null)
    {
        return $this->process_capture_or_refund($transaction, Transaction::REFUND, $member_id);
    }

    protected function process_capture_or_refund(Transaction $parent, $action, $member_id = null)
    {
        $order = $parent->order;
        $child = $this->new_transaction($order);
        $child->payment_method = $order->payment_method;
        $child->parent_id = $parent->id;
        $child->member_id = (int) $member_id;
        $child->payment_method = $parent->payment_method;
        $child->type = $action;
        $child->amount = $parent->amount;
        $child->save();

        $gateway = $this->load_payment_method($child->payment_method);
        $request = $gateway->$action($this->build_payment_request($child));
        $request->setTransactionReference($parent->reference);

        try {
            $response = $request->send();
            $this->update_transaction($child, $response);
        } catch (\Exception $e) {
            $child->status = Transaction::FAILED;
            $child->message = $e->getMessage();
            $child->save();
        }

        return $child;
    }

    /**
     * Build Omnipay payment request array
     */
    public function build_payment_request(Transaction $transaction)
    {
        $request = array();
        $request['amount'] = $transaction->amount;
        $request['currency'] = config_item('store_currency_code');
        $request['transactionId'] = $transaction->id;
        $request['description'] = lang('store.order').' #'.$transaction->order->id;
        $request['transactionReference'] = $transaction->reference;
        $request['returnUrl'] = $this->build_return_url($transaction);
        $request['notifyUrl'] = $request['returnUrl'];
        $request['cancelUrl'] = $transaction->order->cancel_url;
        $request['clientIp'] = $this->ee->input->ip_address();

        // custom gateways may wish to access the order directly
        $request['order'] = $transaction->order;
        $request['orderId'] = $transaction->order->id;

        // these only apply to PayPal
        $request['noShipping'] = 1;
        $request['allowNote'] = 0;

        return $request;
    }

    /**
     * Buidl transaction return URL
     */
    public function build_return_url(Transaction $transaction)
    {
        return $this->ee->store->store->get_action_url('act_payment_return').'&H='.$transaction->hash;
    }

    /**
     * Build Omnipay credit card array
     */
    public function build_payment_credit_card(Order $order, $post_data)
    {
        $card = new CreditCard;

        // default to order details
        foreach (array('first_name', 'last_name', 'address1', 'address2',
            'city', 'postcode', 'state', 'country', 'phone', 'company') as $key) {
            $card->{'setBilling'.studly_case($key)}($order->{'billing_'.$key});
            $card->{'setShipping'.studly_case($key)}($order->{'shipping_'.$key});
        }
        $card->setEmail($order->order_email);

        // map legacy parameters to new CreditCard object
        $map = array(
            'card_no' => 'number',
            'card_name' => 'name',
            'exp_month' => 'expiryMonth',
            'exp_year' => 'expiryYear',
            'start_month' => 'startMonth',
            'start_year' => 'startYear',
            'csc' => 'cvv',
        );

        foreach ($map as $old => $new) {
            if (isset($post_data[$old])) {
                $post_data[$new] = $post_data[$old];
                unset($post_data[$old]);
            }
        }

        // initialize card attributes with post data
        OmnipayHelper::initialize($card, $post_data);

        return $card;
    }

    /*
     * Build Omnipay items array
     */
    public function build_payment_items(Order $order)
    {
        $items = new ItemBag;

        foreach ($order->items as $item) {
            $items->add(array(
                'name' => $item->title,
                'quantity' => $item->item_qty,
                'price' => $item->price,
            ));
        }

        foreach ($order->adjustments as $adjustment) {
            if (!$adjustment->included) {
                $items->add(array(
                    'name' => $adjustment->name,
                    'quantity' => 1,
                    'price' => $adjustment->amount,
                ));
            }
        }

        return $items;
    }

    /**
     * Send a payment request to the gateway, and redirect appropriately
     */
    public function send_payment_request(RequestInterface $request, $transaction)
    {
        try {
            $response = $request->send();
            $this->update_transaction($transaction, $response);

            if ($transaction->status == Transaction::REDIRECT) {
                // redirect to off-site gateway
                return $response->redirect();
            }

            // exception required for SagePay Server
            if (method_exists($response, 'confirm')) {
                $response->confirm($this->build_return_url($transaction));
            }
        } catch (OmnipayException $e) {
            $transaction->status = Transaction::FAILED;
            $transaction->message = $e->getMessage();
            $transaction->save();
        } catch (\Exception $e) {
            $transaction->status = Transaction::FAILED;
            $transaction->message = lang('store.payment.communication_error');
            $transaction->save();
        }

        $gateways_which_call_us_directly = array(
            'AuthorizeNet_SIM',
            'Realex_Redirect',
            'SecurePay_DirectPost',
            'WorldPay',
        );
        if (in_array($transaction->payment_method, $gateways_which_call_us_directly)) {
            // send the customer's browser to our return URL instead of letting the
            // gateway display the page directly to the customer, otherwise they
            // end up on our payment failed or order complete page without their
            // session cookie which obviously won't work
            $this->redirect_form($this->build_return_url($transaction));
        }

        if ($transaction->status == Transaction::SUCCESS) {
            $this->ee->functions->redirect($transaction->order->parsed_return_url);
        } else {
            $this->ee->session->set_flashdata('store_payment_error', $transaction->message);
            $this->ee->functions->redirect($transaction->order->cancel_url);
        }
    }

    public function update_transaction(Transaction &$transaction, ResponseInterface $response)
    {
        if ($this->ee->extensions->active_hook('store_transaction_update_start')) {
            $this->ee->extensions->call('store_transaction_update_start', $transaction, $response);
            if ($this->ee->extensions->end_script) return;
        }

        if ($response->isSuccessful()) {
            $transaction->status = Transaction::SUCCESS;
        } elseif ($response->isRedirect()) {
            $transaction->status = Transaction::REDIRECT;
        } else {
            $transaction->status = Transaction::FAILED;
        }

        $transaction->reference = $response->getTransactionReference();
        $transaction->message = $response->getMessage();
        $transaction->save();

        if ($response->isSuccessful()) {
            $transaction->order->payment_method = $transaction->payment_method;
            $this->update_order_paid_total($transaction->order);
        }

        if ($this->ee->extensions->active_hook('store_transaction_update_end')) {
            $this->ee->extensions->call('store_transaction_update_end', $transaction, $response);
        }
    }

    public function update_order_paid_total(Order $order)
    {
        $order->order_paid = $order->getTotalPaid();

        if ($order->is_order_paid && !$order->order_paid_date) {
            $order->order_paid_date = time();
        }
        $order->save();

        if (!$order->is_order_complete) {
            if ($order->is_order_paid) {
                $order->markAsComplete();
            } else {
                // maybe enough funds are authorized to complete the order
                if ($order->getTotalAuthorized() >= $order->order_total) {
                    $order->markAsComplete();
                }
            }
        }
    }

    public function redirect_form($url)
    {
        $site_name = htmlspecialchars(config_item('site_name'), ENT_QUOTES);
        $url = htmlspecialchars($url, ENT_QUOTES);

        $out = <<<EOF
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="1;URL=$url" />
    <title>Redirecting...</title>
</head>
<body onload="document.payment.submit();">
    <p>Please wait while we redirect you back to $site_name...</p>
    <form name="payment" action="$url" method="post">
        <p><input type="submit" value="Continue" /></p>
    </form>
</body>
</html>
EOF;
        echo $out;
        exit;
    }

    public function get_enabled_payment_method_options($selectedClass = null)
    {
        $methods = PaymentMethod::where('site_id', config_item('site_id'))->where('enabled', 1)->orderBy('title')->get();

        $html = '';
        foreach ($methods as $method) {
            $selected = $method->class == $selectedClass ? 'selected' : '';
            $html .= "<option value='{$method->class}' $selected>{$method->title}</option>\n";
        }

        return $html;
    }

    public function fetch_issuers($gateway_name)
    {
        // create cached http gateway
        $payment_method = $this->find_payment_method($gateway_name);
        if (!$payment_method) {
            return array(lang('valid_payment_method'));
        }

        try {
            // create cached payment gateway to store list of issuers
            $gateway = $payment_method->createGateway($this->ee->store->cached_http);

            $response = $gateway->fetchIssuers()->send();
            if ($response->isSuccessful()) {
                return $response->getIssuers();
            } else {
                return array($response->getMessage());
            }
        } catch (OmnipayException $e) {
            return array($e->getMessage());
        } catch (\Exception $e) {
            return array(lang('store.payment.communication_error'));
        }
    }

    public function fetch_issuer_options($gateway_name)
    {
        $issuers = $this->fetch_issuers($gateway_name);

        return store_select_options($issuers);
    }
}
