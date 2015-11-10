<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

use Store\Update;

class Update124
{
    /**
     * Register new add to cart action
     */
    public function up()
    {
        Update::register_action('act_add_to_cart');
    }
}
