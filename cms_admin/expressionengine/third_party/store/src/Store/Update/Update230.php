<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

class Update230
{
    public function up()
    {
        // remove old cache table
        ee()->dbforge->drop_table('store_cache');
    }
}
