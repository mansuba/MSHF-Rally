<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

class Update202
{
    public function up()
    {
        // change discount total_use_count to NOT NULL
        ee()->db->where('total_use_count is null')->update('store_discounts', array('total_use_count' => 0));
        ee()->dbforge->modify_column('store_discounts', array(
            'total_use_count'   => array('name' => 'total_use_count', 'type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0),
        ));

        // change order custom fields to TEXT
        $order_custom_cols = array();
        for ($i = 1; $i < 10; $i++) {
            $order_custom_cols["order_custom$i"] = array('name' => "order_custom$i", 'type' => 'text');
        }
        ee()->dbforge->modify_column('store_orders', $order_custom_cols);
    }
}
