<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

abstract class AbstractController
{
    protected $ee;
    protected $breadcrumbs = array();

    public function __construct($ee)
    {
        $this->ee = $ee;
        $this->addBreadcrumb(BASE.AMP.STORE_CP, lang('store_module_name'));
    }

    protected function render($view, array $data)
    {
        return $this->ee->load->view($view, $data, true);
    }

    protected function requirePrivilege($privilege)
    {
        if (!$this->ee->store->config->has_privilege($privilege)) {
            show_error(lang('store_no_access'));
        }
    }

    protected function show404()
    {
        show_404();
    }

    protected function sortableAjax($class, $with_site_id = true)
    {
        $sort = 0;
        foreach ((array) $this->ee->input->post('sorted_ids') as $id) {
            $query = new $class;
            if ($with_site_id) {
                $query = $query->where('site_id', config_item('site_id'));
            }
            $query->where('id', $id)->update(array('sort' => $sort++));
        }

        return $this->ee->output->send_ajax_response(array(
            'type'      => 'success',
            'message'   => lang('store.settings.updated'),
        ));
    }

    /**
     * Set the CP page title
     */
    protected function setTitle($title)
    {
        $this->setVariable('cp_page_title', $title);
    }

    /**
     * We use our own breadcrumb function to override the useless "Modules" crumb added by
     * the modules controller.
     */
    protected function addBreadcrumb($link, $title)
    {
        $this->breadcrumbs[$link] = $title;

        $this->setVariable('cp_breadcrumbs', $this->breadcrumbs);
    }

    /**
     * Backwards compatible view variable setter
     */
    protected function setVariable($key, $value)
    {
        $this->ee->view->$key = $value;
    }
}
