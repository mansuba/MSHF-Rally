<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Action;

abstract class AbstractAction
{
    protected $ee;
    protected $form_params;

    public function __construct($ee)
    {
        $this->ee = $ee;
    }

    abstract public function perform();

    protected function form_param($key)
    {
        $this->form_params();

        return isset($this->form_params[$key]) ? $this->form_params[$key] : false;
    }

    protected function form_params()
    {
        if (null === $this->form_params) {
            $this->ee->load->library('encrypt');
            $this->form_params = json_decode($this->ee->encrypt->decode($this->ee->input->post('_params')), true);

            if (empty($this->form_params)) {
                return $this->ee->output->show_user_error('general', array(lang('not_authorized')));
            }

            // automatically convert require="" parameter into rules:field="required"
            if (isset($this->form_params['require'])) {
                $fields = explode('|', $this->form_params['require']);
                foreach ($fields as $field) {
                    $this->form_params['rules:'.$field] = isset($this->form_params['rules:'.$field]) ?
                        $this->form_params['rules:'.$field].'|required' : 'required';
                }
                unset($this->form_params['require']);
            }
        }

        return $this->form_params;
    }

    protected function get_return_url($name = 'return_url')
    {
        $url = $this->ee->functions->create_url($this->ee->input->post($name));
        if ($this->ee->input->post('secure_return')) {
            $url = str_replace('http://', 'https://', $url);
        }

        return $url;
    }
}
