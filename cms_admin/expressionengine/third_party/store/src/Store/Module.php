<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use Store\Action\CheckoutAction;
use Store\Action\DownloadAction;
use Store\Action\PaymentAction;
use Store\Action\PaymentReturnAction;
use Store\Tag\CartTag;
use Store\Tag\CheckoutTag;
use Store\Tag\DownloadTag;
use Store\Tag\OrdersTag;
use Store\Tag\PaymentTag;
use Store\Tag\ProductFormTag;
use Store\Tag\ProductTag;
use Store\Tag\SearchTag;

class Module
{
    protected $ee;

    public function __construct()
    {
        $this->ee = ee();

        // having a submit button named "submit" can cause JS issues
        // we provide "commit" as an alternative button name
        if (isset($_POST['commit'])) {
            $_POST['submit'] = $_POST['commit'];
        }
    }

    public function cart()
    {
        $tag = new CartTag($this->ee, $this->ee->TMPL->tagdata, $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    public function checkout()
    {
        $tag = new CheckoutTag($this->ee, $this->ee->TMPL->tagdata, $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    public function checkout_debug()
    {
        $tag = new CheckoutTag($this->ee, $this->ee->load->view('checkout_debug', null, true), $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    public function download()
    {
        $tag = new DownloadTag($this->ee, $this->ee->TMPL->tagdata, $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    public function orders()
    {
        $tag = new OrdersTag($this->ee, $this->ee->TMPL->tagdata, $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    public function payment()
    {
        $tag = new PaymentTag($this->ee, $this->ee->TMPL->tagdata, $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    public function product()
    {
        $tag = new ProductTag($this->ee, $this->ee->TMPL->tagdata, $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    public function product_form()
    {
        $tag = new ProductFormTag($this->ee, $this->ee->TMPL->tagdata, $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    public function search()
    {
        $tag = new SearchTag($this->ee, $this->ee->TMPL->tagdata, $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    public function act_checkout()
    {
        $action = new CheckoutAction($this->ee);

        return $action->perform();
    }

    public function act_download_file()
    {
        $action = new DownloadAction($this->ee);

        return $action->perform();
    }

    public function act_payment()
    {
        $action = new PaymentAction($this->ee);

        return $action->perform();
    }

    public function act_payment_return()
    {
        $action = new PaymentReturnAction($this->ee);

        return $action->perform();
    }
}
