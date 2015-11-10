<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

/**
 * Store module class
 *
 * This class is responsible for handling EE tags and actions.
 * No logic is kept in this class. Instead, all the logic is found in individual
 * Tag and Action classes. This keeps the code clean and makes testing easier.
 */
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
        return $this->parse_tag('cart');
    }

    public function checkout()
    {
        return $this->parse_tag('checkout');
    }

    public function checkout_debug()
    {
        $this->ee->TMPL->tagdata = $this->ee->load->view('checkout_debug', null, true);

        return $this->parse_tag('checkout');
    }

    public function download()
    {
        return $this->parse_tag('download');
    }

    public function orders()
    {
        return $this->parse_tag('orders');
    }

    public function payment()
    {
        return $this->parse_tag('payment');
    }

    public function product()
    {
        return $this->parse_tag('product');
    }

    public function product_form()
    {
        return $this->parse_tag('product_form');
    }

    public function search()
    {
        return $this->parse_tag('search');
    }

    public function act_checkout()
    {
        return $this->perform_action('checkout');
    }

    public function act_download_file()
    {
        return $this->perform_action('download');
    }

    public function act_payment()
    {
        return $this->perform_action('payment');
    }

    public function act_payment_return()
    {
        return $this->perform_action('payment_return');
    }

    protected function parse_tag($name)
    {
        $class = '\\Store\\Tag\\'.studly_case($name).'Tag';
        $tag = new $class($this->ee, $this->ee->TMPL->tagdata, $this->ee->TMPL->tagparams);

        return $tag->parse();
    }

    protected function perform_action($name)
    {
        $class = '\\Store\\Action\\'.studly_case($name).'Action';
        $action = new $class($this->ee);

        return $action->perform();
    }
}
