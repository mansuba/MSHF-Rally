<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

class Update220
{
    public function up()
    {
        // increase length of order columns
        $cols = array();
        foreach (array('billing_postcode', 'billing_phone', 'shipping_postcode', 'shipping_phone') as $field) {
            $cols[$field] = array('name' => $field, 'type' => 'varchar', 'constraint' => 255);
        }
        ee()->dbforge->modify_column('store_orders', $cols);

        // add company columns to orders
        if (!ee()->db->field_exists('billing_company', 'store_orders')) {
            ee()->dbforge->add_column('store_orders', array(
                'billing_company' => array('type' => 'varchar', 'constraint' => 255),
            ), 'billing_phone');
            ee()->dbforge->add_column('store_orders', array(
                'shipping_company' => array('type' => 'varchar', 'constraint' => 255),
            ), 'shipping_phone');
        }

        // set csrf exempt actions
        $csrf_exempt_actions = array('act_payment_return');
        ee()->db->where('class', 'Store')->where_in('method', $csrf_exempt_actions)
            ->update('actions', array('csrf_exempt' => 1));
    }
}
