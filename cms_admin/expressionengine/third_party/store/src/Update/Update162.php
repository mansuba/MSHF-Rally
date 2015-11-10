<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

class Update162
{
    public function up()
    {
        $this->EE = get_instance();

        // allow longer SKUs
        $this->EE->dbforge->modify_column('store_order_items', array(
            'sku' => array('name' => 'sku', 'type' => 'varchar', 'constraint' => 40, 'null' => false),
        ));
        $this->EE->dbforge->modify_column('store_stock', array(
            'sku' => array('name' => 'sku', 'type' => 'varchar', 'constraint' => 40, 'null' => false),
        ));
        $this->EE->dbforge->modify_column('store_stock_options', array(
            'sku' => array('name' => 'sku', 'type' => 'varchar', 'constraint' => 40, 'null' => false),
        ));
    }
}
