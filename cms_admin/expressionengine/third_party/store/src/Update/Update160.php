<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

use Store\Update;

class Update160
{
    public function up()
    {
        Update::register_action('act_payment');
        Update::register_action('act_checkout');
    }
}
