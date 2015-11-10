<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

use Carbon\Carbon;

class ReportsController extends AbstractController
{
    public function index()
    {
        // handle submissions for specific reports
        if ( ! empty($_POST)) {
            unset($_POST['submit']); // looks ugly
            $report = $this->ee->input->get('report', true);
            $this->ee->security->restore_xid();
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.AMP.'sc=reports'.AMP.'report='.$report.AMP.http_build_query($_POST));
        }

        if ($this->ee->input->get('report')) {
            return $this->_reports_view($this->ee->input->get('report'));
        }

        $data = array(
            'post_url' => STORE_CP.AMP.'sc=reports',
            'date_options' => array(),
            'stock_products_options' => array('sku' => lang('store.sku'), 'order_item_title' => lang('title'), 'item_subtotal' => lang('store.net_sales'), 'item_qty' => lang('store.quantity_sold')),
            'stock_inventory_options' => array('stockcode' => lang('store.sku'), 'product title' => lang('title')),
            'order_sort_options' => array('total' => lang('store.total'), 'last status update' => lang('store.last_status_update')),
            'start_date' => $this->ee->localize->format_date('%Y-%m-%d', time() - 52*7*24*60*60),
            'end_date' => $this->ee->localize->format_date('%Y-%m-%d', time()),
        );

        $current_month = (int) date('m', time());
        $current_year = date('Y', time());
        $data['date_options'] = array();

        for ($month = $current_month; $month > $current_month - 3; $month--) {
            if ($month > 0) {
                $date = gmmktime(0, 0, 0, $month, 1, $current_year);
            } else {
                $date = gmmktime(0, 0, 0, $month+12, 1, $current_year-1);
            }

            $key = date('Y-m', $date);
            $data['date_options'][$key] = strftime('%B %Y', $date);
        }
        $data['date_options']['custom_range'] = lang('store.custom_range');

        $data['status_options'] = array(lang('any'));
        $order_status_select_options = $this->ee->store->orders->order_statuses();
        foreach ($order_status_select_options as $option) {
            $data['status_options'][$option['name']] = store_order_status_name($option['name']);
        }

        $this->setTitle(lang('nav_reports'));
        $this->ee->cp->add_js_script(array('ui' => 'datepicker'));

        return $this->ee->load->view('reports/report_list', $data, true);
    }

    private function _reports_view($report_name, $order_id = NULL)
    {
        $this->addBreadcrumb(BASE.AMP.STORE_CP.AMP.'sc=reports', lang('nav_reports'));

        switch ($report_name) {
            case 'orders':
                if ($this->ee->input->get('orders_report_date') == 'custom_range') {
                    $start_date = Carbon::createFromFormat('Y-m-d', $this->ee->input->get('start_date'), 'UTC')->setTime(0, 0, 0);
                    $end_date = Carbon::createFromFormat('Y-m-d', $this->ee->input->get('end_date'), 'UTC')->setTime(0, 0, 0)->addDay();
                    $report_title = lang('store.orders_report_all_orders').' '.
                        lang('store.starting_from').$this->ee->store->store->format_date($start_date, ' %F %j, %Y ').
                        lang('store.through').$this->ee->store->store->format_date($end_date->copy()->subDay(), ' %F %j, %Y');
                } else {
                    $date = $this->_get_report_dates($this->ee->input->get('orders_report_date'));
                    $start_date = $date['start_date'];
                    $end_date = $date['end_date'];
                    $report_title = lang('store.orders_report_list_all').$this->ee->store->store->format_date($start_date, ' %F %Y');
                }

                $status = $this->ee->input->get('orders_report_status', true);
                if ( ! empty($status)) {
                    $report_title .= ' '.lang('store.orders_report_with_status').' '.store_order_status_name($status);
                }

                $data = $this->ee->store->reports->orders($start_date->timestamp, $end_date->timestamp, $status);
                $data['page_title'] = lang('store.orders_report');
                break;

            case 'sales_by_date':
                if ($this->ee->input->get('sales_report_options') == 'custom_range') {
                    $start_date = Carbon::createFromFormat('Y-m-d', $this->ee->input->get('sales_start_date'), 'UTC')->setTime(0, 0, 0);
                    $end_date = Carbon::createFromFormat('Y-m-d', $this->ee->input->get('sales_end_date'), 'UTC')->setTime(0, 0, 0)->addDay();
                    $report_title = lang('store.total_sales').' '.
                        lang('store.starting_from').$this->ee->store->store->format_date($start_date, ' %F %j, %Y ').
                        lang('store.through').$this->ee->store->store->format_date($end_date->copy()->subDay(), ' %F %j, %Y');
                } else {
                    $date = $this->_get_report_dates($this->ee->input->get('sales_report_options'));
                    $start_date = $date['start_date'];
                    $end_date = $date['end_date'];
                    $report_title = lang('store.total_sales_report_desc').$this->ee->store->store->format_date($start_date, ' %F %Y');
                }

                $data = $this->ee->store->reports->sales_by_date($start_date->timestamp, $end_date->timestamp);
                $data['page_title'] = lang('store.sales_report1');
                break;

            case 'stock_value':
                $data = $this->ee->store->reports->stock_value($this->ee->input->get('stock_inventory_options'));
                $data['page_title'] = lang('store.stock_report3');
                $report_title = lang('store.stock_inventory_report_desc').' '.lang('store.sorted_by').' '.$this->ee->input->get('stock_inventory_options');
                break;

            case 'stock_products':
                if ($this->ee->input->get('stock_report_options') == 'custom_range') {
                    $start_date = Carbon::createFromFormat('Y-m-d', $this->ee->input->get('stock_start_date'), 'UTC')->setTime(0, 0, 0);
                    $end_date = Carbon::createFromFormat('Y-m-d', $this->ee->input->get('stock_end_date'), 'UTC')->setTime(0, 0, 0)->addDay();
                    $report_title = lang('store.products_sold').' '.
                        lang('store.starting_from').$this->ee->store->store->format_date($start_date, ' %F %j, %Y ').
                        lang('store.through').$this->ee->store->store->format_date($end_date->copy()->subDay(), ' %F %j, %Y');
                } else {
                    $date = $this->_get_report_dates($this->ee->input->get('stock_report_options'));
                    $start_date = $date['start_date'];
                    $end_date = $date['end_date'];
                    $report_title = lang('store.stock_products_report_desc').' '.$this->ee->store->store->format_date($start_date, ' %F %Y');
                }
                $data = $this->ee->store->reports->stock_products($start_date->timestamp, $end_date->timestamp, $this->ee->input->get('stock_orderby_options'));
                $data['page_title'] = lang('store.sales_report2');
                break;

            default:
                $this->ee->session->set_flashdata('message_error', lang('store.invalid_report'));
                $this->ee->functions->redirect(BASE.AMP.STORE_CP.AMP.'sc=reports');
        }

        $data['report_title'] = $report_title;
        $data['post_url'] = STORE_CP.AMP.'sc=reports'.AMP.'report='.$report_name;
        $data['export_link'] = BASE.AMP.htmlentities(http_build_query($this->ee->security->xss_clean($_GET)));

        if ($this->ee->input->get('pdf')) {
            $html = $this->ee->load->view('reports/report_pdf', $data, true);
            $filename = $data['page_title'].'_'.$this->ee->localize->human_time(time()).'.pdf';

            $pdf = $this->ee->store->pdf->create_pdf();
            $pdf->load_html($html);
            $pdf->render();
            $pdf->stream($filename);
        } elseif ($this->ee->input->get('csv')) {
            $output = $this->ee->load->view('reports/report_csv', $data, true);
            $this->ee->load->helper('download');
            force_download($data['page_title'].'_'.$this->ee->localize->human_time(time()).'.csv', $output);
        } else {
            $this->setTitle($data['page_title']);

            return $this->ee->load->view('reports/report_html', $data, true);
        }
    }

    private function _get_report_dates($year_month)
    {
        $dates = array();
        $dates['start_date'] = Carbon::createFromFormat('Y-m', $year_month, 'UTC')->day(1)->setTime(0, 0, 0);
        $dates['end_date'] = $dates['start_date']->copy()->addMonth();

        return $dates;
    }
}
