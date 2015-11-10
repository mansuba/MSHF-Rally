<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use Store\FormValidation;

class Cp
{
    protected $ee;
    protected $site_id;

    public function __construct($ee = null)
    {
        $this->ee = $ee ?: ee();
    }

    public function index()
    {
        $this->ee->lang->loadfile('content');
        $this->ee->lang->loadfile('design');
        $this->ee->load->library(array('javascript', 'table'));
        $this->ee->load->helper(array('form', 'text', 'search'));

        $this->ee->form_validation = new FormValidation;
        $this->ee->form_validation->set_error_delimiters('<p><strong class="notice">', '</strong></p>');

        // check site enabled
        if (!config_item('store_site_enabled')) {
            return $this->route('dashboard', 'install');
        }

        // load store css + js
        $this->ee->store->config->load_cp_assets();

        // default global view variables
        $this->ee->load->vars(array(
            'store_table_template' => array(
                'table_open' => '<table class="mainTable store_table">'),
            'store_fixed_table_template' => array(
                'table_open' => '<table class="mainTable store_table store_table_fixed">'),
            'store_sortable_table_template' => array(
                'table_open' => '<table class="mainTable store_table store_table_sortable">'),
        ));

        // simple router
        if ($controller = $this->ee->input->get('sc')) {
            $method = $this->ee->input->get('sm') ?: 'index';

            return $this->route($controller, $method);
        }

        return $this->route('dashboard');
    }

    protected function route($controller, $method = 'index')
    {
        $class = 'Store\Cp\\'.ucfirst(strtolower($controller)).'Controller';

        if (class_exists($class)) {
            $controller = new $class($this->ee);

            if (is_callable(array($controller, $method))) {
                return $controller->$method();
            }
        }

        show_404();
    }
}
