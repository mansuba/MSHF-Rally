<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

class Update210
{
    public function up()
    {
        if (!ee()->db->field_exists('item_discount', 'store_order_items')) {
            ee()->dbforge->add_column('store_order_items', array(
                'item_discount' => array('type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
            ), 'item_subtotal');
        }

        if (!ee()->db->field_exists('order_shipping_discount', 'store_orders')) {
            ee()->dbforge->add_column('store_orders', array(
                'order_shipping_discount' => array('type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
            ), 'order_shipping');
        }

        if (!ee()->db->field_exists('order_shipping_total', 'store_orders')) {
            ee()->dbforge->add_column('store_orders', array(
                'order_shipping_total' => array('type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
            ), 'order_shipping_tax');
            ee()->db->set('order_shipping_total', 'order_shipping - order_shipping_discount + order_shipping_tax', false)
                ->update('store_orders');
        }

        if (!ee()->db->field_exists('order_handling_total', 'store_orders')) {
            ee()->dbforge->add_column('store_orders', array(
                'order_handling_total' => array('type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
            ), 'order_handling_tax');
            ee()->db->set('order_handling_total', 'order_handling + order_handling_tax', false)
                ->update('store_orders');
        }

        foreach (array('price_inc_tax_old', 'regular_price_inc_tax_old') as $field) {
            if (ee()->db->field_exists($field, 'store_order_items')) {
                ee()->dbforge->drop_column('store_order_items', $field);
            }
        }
    }
}
