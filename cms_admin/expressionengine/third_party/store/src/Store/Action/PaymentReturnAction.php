<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Action;

use Store\Model\Transaction;

class PaymentReturnAction extends AbstractAction
{
    public function perform()
    {
        $transaction = Transaction::where('site_id', config_item('site_id'))
            ->where('hash', (string) $this->ee->input->get_post('H'))
            ->first();

        if (empty($transaction)) {
            show_error(lang('store.error_processing_order'));
        }

        $this->ee->store->payments->complete_payment($transaction);
    }
}
