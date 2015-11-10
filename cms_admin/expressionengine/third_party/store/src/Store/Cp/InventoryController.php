<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

use Store\Model\Product;

class InventoryController extends AbstractController
{
    public function index()
    {
        $this->setTitle(lang('nav_inventory'));
        $this->requirePrivilege('can_access_inventory');

        $this->ee->load->library('table');
        $this->ee->table->set_base_url(STORE_CP.'&amp;sc=inventory');
        $this->ee->_mcp_reference =& $this;
        $data = $this->ee->table->datasource('index_data', array(
            'sort' => array('title' => 'asc'),
        ));

        $data['post_url'] = STORE_CP.'&amp;sc=inventory';
        $data['category_options'] = array('' => lang('store.any')) +
            $this->ee->store->products->get_categories();
        $data['per_page_select_options'] = array('10' => '10 '.lang('results'), '25' => '25 '.lang('results'), '50' => '50 '.lang('results'), '75' => '75 '.lang('results'), '100' => '100 '.lang('results'), '150' => '150 '.lang('results'));

        return $this->render('inventory/index', $data);
    }

    public function index_data($state, $data)
    {
        $search = array();
        $search['category_id'] = $this->ee->input->get_post('category_id');
        $search['keywords'] = (string) $this->ee->input->get_post('keywords');

        // find results
        $query = Product::join('channel_titles', 'channel_titles.entry_id', '=', 'store_products.entry_id')
            ->join('store_stock', 'store_stock.entry_id', '=', 'store_products.entry_id')
            ->select(array('store_products.*', 'channel_titles.title', $this->ee->store->db->raw('SUM(`stock_level`) AS `total_stock`')))
            ->groupBy('store_products.entry_id');

        if ($search['category_id']) {
            $query->join('category_posts', 'category_posts.entry_id', '=', 'channel_titles.entry_id')
                ->where('category_posts.cat_id', '=', $search['category_id']);
        }

        if ($search['keywords'] !== '') {
            $query->where(function($query) use ($search) {
                $query->where('channel_titles.title', 'like', '%'.$search['keywords'].'%')
                    ->orWhere('channel_titles.entry_id', $search['keywords']);
            });
        }

        $order_by = key($state['sort']);
        $direction = reset($state['sort']);
        switch ($order_by) {
            case 'id':
                $query->orderBy('store_products.entry_id', $direction);
                break;
            case 'price':
                $query->orderBy('price', $direction);
                break;
            default:
                $query->orderBy($order_by, $direction);
        }

        $per_page = $this->ee->input->get_post('per_page') ?: 50;
        $products = $query->take($per_page)
            ->skip($state['offset'])
            ->get();

        // table headings
        $this->ee->table->set_columns(array(
            'id'            => array('header' => array('data' => lang('store.#'), 'width' => '2%')),
            'title'         => array('header' => lang('title')),
            'total_stock'   => array('header' => lang('store.total_stock')),
            'price'         => array('header' => lang('store.price')),
            'options'       => array('sort' => false),
        ));

        // table data
        $data['rows'] = array();
        foreach ($products as $product) {
            $data['rows'][] = array(
                'id'            => $product->entry_id,
                'title'         => $product->title,
                'total_stock'   => $product->total_stock,
                'price'         => array('data' => store_currency($product->regular_price), 'class' => 'currency'),
                'options'       => '<a href="'.BASE.AMP.'C=content_publish&amp;M=entry_form&amp;channel_id='.$product->channel_id.'&amp;entry_id='.$product->entry_id.'">'.lang('edit_entry').'</a>',
            );
        }

        $data['no_results'] = '<p class="notice">'.lang('no_entries_matching_that_criteria').'</p>';
        $data['search'] = $search;
        $data['pagination'] = array(
            'per_page' => $per_page,
            'total_rows' => Product::count(),
        );

        return $data;
    }
}
