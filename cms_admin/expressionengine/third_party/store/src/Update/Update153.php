<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

class Update153
{
    public function up()
    {
        $this->EE = get_instance();

        for ($i = 6; $i <= 9; $i++) {
            $field_name = 'order_custom'.$i;
            $after_field = 'order_custom'.($i-1);

            if ( ! $this->EE->db->field_exists($field_name, 'store_orders')) {
                $this->EE->dbforge->add_column('store_orders', array(
                    $field_name => array('type' => 'varchar', 'constraint' => 255)
                ), $after_field);
            }
        }
    }
}
