<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

use Store\FormBuilder;
use Store\Model\MemberGroup;
use Store\Model\Discount;

class DiscountsController extends AbstractController
{
    public function __construct($ee)
    {
        parent::__construct($ee);

        $this->addBreadcrumb(store_cp_url('sales'), lang('nav_promotions'));
    }

    public function index()
    {
        $this->setTitle(lang('nav_discounts'));

        // handle form submit
        if ( ! empty($_POST['submit'])) {
            $selected = Discount::where('site_id', config_item('site_id'))->whereIn('id', (array) $this->ee->input->post('selected'));

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
            $this->ee->functions->redirect(store_cp_url('discounts'));
        }

        // sortable ajax post
        if (!empty($_POST['sortable_ajax'])) {
            return $this->sortableAjax('\Store\Model\Discount');
        }

        $data = array();
        $data['post_url'] = STORE_CP.'&amp;sc=discounts';
        $data['edit_url'] = store_cp_url('discounts', 'edit').'&amp;id=';
        $data['discounts'] = Discount::where('site_id', config_item('site_id'))->orderBy('sort')->get();

        return $this->render('discounts/index', $data);
    }

    public function edit()
    {
        $this->addBreadcrumb(store_cp_url('discounts'), lang('nav_discounts'));

        $discount_id = $this->ee->input->get('id');
        if ($discount_id == 'new') {
            $discount = new Discount;
            $discount->site_id = config_item('site_id');
            $discount->enabled = 1;
            $discount->break = 1;

            $this->setTitle(lang('store.discount_new'));
        } else {
            $discount = Discount::where('site_id', config_item('site_id'))->find($discount_id);

            if (empty($discount)) {
                return $this->show404();
            }

            $this->setTitle(lang('store.discount_edit'));
        }

        // handle form submit
        $discount->fill((array) $this->ee->input->post('discount'));
        $this->ee->form_validation->set_rules('discount[name]', 'lang:name', 'required');
        if ($this->ee->form_validation->run() === true) {
            $discount->save();
            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(store_cp_url('discounts'));
        }

        $data = array();
        $data['post_url'] = STORE_CP.AMP.'sc=discounts&amp;sm=edit&amp;id='.$discount_id;
        $data['discount'] = $discount;
        $data['form'] = new FormBuilder($discount);
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

        return $this->render('discounts/edit', $data);
    }
}
