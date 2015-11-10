<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Tag;

class CartTag extends AbstractTag
{
    public function parse()
    {
        $this->tmpl_secure_check(false);

        $tag_vars = array($this->ee->store->orders->get_cart()->toTagArray());

        // check for empty cart
        if ($this->ee->store->orders->get_cart()->isEmpty()) {
            return $this->no_results('no_items');
        }

        return $this->parse_variables($tag_vars);
    }
}
