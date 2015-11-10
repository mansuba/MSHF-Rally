<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Tag;

use Store\Model\Product;

class ProductTag extends AbstractTag
{
    public function parse()
    {
        $this->ee->load->helper('form');

        $this->tmpl_secure_check(false);

        $entry_id = (int) $this->param('entry_id');
        $product = Product::with(array(
            'modifiers' => function($query) { $query->orderBy('mod_order'); },
            'modifiers.options' => function($query) { $query->orderBy('opt_order'); },
            'stock',
        ))->whereNotNull('price')
        ->find($entry_id);

        if (empty($product)) return;

        $this->ee->store->products->apply_sales($product);

        // parse tagdata variables
        $tag_vars = array($product->toTagArray());
        $tag_vars[0]['qty_in_cart'] = $this->ee->store->orders->get_cart()->countItemsById($entry_id);
        $out = $this->parse_variables($tag_vars);

        // start our form output
        if ($this->param('disable_form') != 'yes') {
            // initialize form hidden fields
            $hidden_fields = array();
            $hidden_fields['return_url'] = $this->ee->uri->uri_string;
            $hidden_fields['entry_id'] = $entry_id;

            // prevents submitting checkout when adding items
            $hidden_fields['nosubmit'] = 1;

            if ($this->param('return') !== false) {
                $hidden_fields['return_url'] = $this->param('return');
            }
            if ($this->param('empty_cart') == 'yes') {
                $hidden_fields['empty_cart'] = 1;
            }

            $out = $this->form_open('act_checkout', $hidden_fields, array(
                'class' => 'store_product_form'
            )).$out.'</form>';
        }

        if ($this->param('disable_javascript') != 'yes') {
            // include product stock javascript
            $out .= '
                <script type="text/javascript">
                window.ExpressoStore = window.ExpressoStore || {};
                ExpressoStore.products = ExpressoStore.products || {};
                ExpressoStore.products['.$entry_id.'] = '.$product->toJson().';
                '.$this->async_store_js().'
                </script>';
        }

        return $out;
    }
}
