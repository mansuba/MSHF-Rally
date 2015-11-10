<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Tag;

class ProductFormTag extends AbstractTag
{
    public function parse()
    {
        // initialize form hidden fields
        $hidden_fields = array();
        $hidden_fields['return_url'] = $this->ee->uri->uri_string;

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
        )).$this->tagdata.'</form>';

        return $out;
    }
}
