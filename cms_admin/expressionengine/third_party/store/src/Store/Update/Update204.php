<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

class Update204
{
    public function up()
    {
        ee()->dbforge->modify_column('store_products', array(
            'price' => array('name' => 'price', 'type' => 'decimal', 'constraint' => '19,4', 'null' => true),
        ));
    }
}
