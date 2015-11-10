<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Tag;

class OrdersTag extends AbstractTag
{
    public function parse()
    {
        $this->tmpl_secure_check();

        $tag_vars = $this->get_orders_query()->get()->toTagArray();

        if (empty($tag_vars[0])) {
            return $this->no_results('no_orders');
        }

        $out = $this->parse_variables($tag_vars);

        return $out.$this->track_conversion($tag_vars);
    }
}
