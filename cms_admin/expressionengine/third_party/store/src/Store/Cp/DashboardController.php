<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

class DashboardController extends AbstractController
{
    public function index()
    {
        $this->setTitle(lang('nav_dashboard'));

        $period = (int) $this->ee->input->get('period') ?: 30;

        $data = array();
        $data['stats'] = $this->ee->store->reports->getDashboardStats($period);

        // load google javascript library and dashboard graph data
        $this->ee->cp->add_to_foot('<script type="text/javascript" src="https://www.google.com/jsapi"></script>');
        $graph = $this->ee->store->reports->getDashboardGraphData($period);
        $this->ee->javascript->output('ExpressoStore.dashboardGraph = '.json_encode($graph).';');

        return $this->render('dashboard', $data);
    }

    public function install()
    {
        $this->setTitle(lang('store.install_new_site'));

        $data = array(
            'site_name' => config_item('site_name'),
            'post_url' => STORE_CP.'&amp;sc=dashboard&amp;sm=install',
            'duplicate_options' => array('' => lang('store.none')),
            'is_super_admin' => $this->ee->store->config->is_super_admin(),
        );

        if ($this->ee->input->post('submit')) {
            if (!$data['is_super_admin']) {
                return show_error(lang('store.no_access'));
            }

            // install default settings
            $site_id = config_item('site_id');
            $this->ee->store->install->install_site($site_id);

            // install example templates?
            if ($this->ee->input->post('install_example_templates')) {
                $this->ee->store->install->install_templates($site_id);
            }

            // redirect
            $this->ee->session->set_flashdata('message_success', lang('store.site_installed_successfully'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP);
        }

        return $this->ee->load->view('install', $data, true);
    }
}
