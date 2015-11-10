<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

use Store\Report\HtmlRenderer;
use Store\Report\CsvRenderer;

class ReportsController extends AbstractController
{
    public function index()
    {
        $this->setTitle(lang('nav_reports'));

        $data = array();
        $data['reports'] = $this->ee->store->reports->get_reports();

        return $this->render('reports/index', $data);
    }

    public function show()
    {
        $reports = $this->ee->store->reports->get_reports();
        $report_name = snake_case($this->ee->input->get('report', true));

        if (!isset($reports[$report_name])) {
            return $this->show404();
        }

        // submitted form values are converted query string for easy bookmarking etc
        if (isset($_POST['options'])) {
            $options = array_merge(array('report' => $report_name), (array) $_POST['options']);

            return $this->ee->functions->redirect(store_cp_url('reports', 'show', $options));
        }

        $this->addBreadcrumb(store_cp_url('reports'), lang('nav_reports'));
        $this->setTitle(lang("store.reports.$report_name"));

        $class = $reports[$report_name];
        if ($this->ee->input->get('csv')) {
            $renderer = new CsvRenderer;
            $report = new $class($this->ee, $renderer, $_GET);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="'.$report_name.'.csv"');
            $report->run();
            exit;
        }

        $renderer = new HtmlRenderer;
        $data['report'] = new $class($this->ee, $renderer, $_GET);

        if ($this->ee->input->get('print')) {
            $layout_data = array(
                'title' => lang("store.reports.$report_name"),
                'body' => $data['report'],
                'class' => 'report',
            );

            echo $this->ee->load->view('print_layout', $layout_data, true);
            exit;
        }

        $data['post_url'] = STORE_CP.'&amp;sc=reports&amp;sm=show&amp;report='.$report_name;
        $data['export_url'] = store_cp_url('reports', 'show', $_GET);

        $this->ee->cp->add_js_script(array('ui' => 'datepicker'));

        return $this->render('reports/show', $data);
    }
}
