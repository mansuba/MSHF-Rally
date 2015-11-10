<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

use Store\FormBuilder;
use Store\Model\MemberGroup;
use Store\Model\Sale;

class SalesController extends AbstractController
{
    public function __construct($ee)
    {
        parent::__construct($ee);

        $this->addBreadcrumb(BASE.AMP.STORE_CP.'&amp;sc=sales', lang('nav_promotions'));
    }

    public function index()
    {
        $this->setTitle(lang('nav_sales'));

        // handle form submit
        if ( ! empty($_POST['submit'])) {
            $selected = Sale::where('site_id', config_item('site_id'))->whereIn('id', (array) $this->ee->input->post('selected'));

            switch ($this->ee->input->post('with_selected')) {
                case 'enable':
                    $selected->update(array('enabled' => 1));
                    break;
                case 'disable':
                    $selected->update(array('enabled' => 0));
                    break;
                case 'delete':
                    $selected->delete();
                    break;
            }

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=sales');
        }

        // sortable ajax post
        if (!empty($_POST['sortable_ajax'])) {
            return $this->sortableAjax('\Store\Model\Sale');
        }

        $data = array();
        $data['post_url'] = STORE_CP.'&amp;sc=sales';
        $data['edit_url'] = BASE.AMP.STORE_CP.'&amp;sc=sales&amp;sm=edit&amp;id=';
        $data['sales'] = Sale::where('site_id', config_item('site_id'))->orderBy('sort')->get();

        return $this->ee->load->view('sales/index', $data, true);
    }

    public function edit()
    {
        $this->addBreadcrumb(BASE.AMP.STORE_CP.'&amp;sc=sales&amp;sm=index', lang('nav_sales'));

        $sale_id = $this->ee->input->get('id');
        if ($sale_id == 'new') {
            $sale = new Sale;
            $sale->site_id = config_item('site_id');
            $sale->enabled = 1;

            $this->setTitle(lang('store.sale_new'));
        } else {
            $sale = Sale::where('site_id', config_item('site_id'))->find($sale_id);

            if (empty($sale)) {
                return $this->show404();
            }

            $this->setTitle(lang('store.sale_edit'));
        }

        // handle form submit
        $sale->fill((array) $this->ee->input->post('sale'));
        $this->ee->form_validation->set_rules('sale[name]', 'lang:name', 'required');
        if ($this->ee->form_validation->run() === true) {
            $sale->save();
            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=sales');
        }

        $data = array();
        $data['post_url'] = STORE_CP.AMP.'sc=promotions&amp;sm=edit&amp;id='.$sale_id;
        $data['sale'] = $sale;
        $data['form'] = new FormBuilder($sale);
        $data['category_options'] = $this->ee->store->products->get_categories();
        $data['product_options'] = $this->ee->store->products->get_product_titles();

        $member_groups = MemberGroup::all();

        $data['member_groups'] = array();
        foreach ($member_groups as $row) {
            // ignore banned, guests, pending
            if (!in_array($row->group_id, array(2, 3, 4))) {
                $data['member_groups'][$row->group_id] = $row->group_title;
            }
        }

        $this->ee->cp->add_js_script(array('ui' => 'datepicker'));

        return $this->ee->load->view('sales/edit', $data, true);
    }
}
