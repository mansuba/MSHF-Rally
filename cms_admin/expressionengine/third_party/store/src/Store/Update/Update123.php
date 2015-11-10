<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

use Store\Update;

class Update123
{
    /**
     * Add missing promo codes to orders table
     */
    public function up()
    {
        $this->EE = get_instance();

        $sql = 'UPDATE '.$this->EE->db->protect_identifiers('store_orders', TRUE).' o
            JOIN '.$this->EE->db->protect_identifiers('store_promo_codes', TRUE).' p
            ON p.promo_code_id = o.promo_code_id
            SET o.promo_code = p.promo_code
            WHERE o.promo_code IS NULL';
        $this->EE->db->query($sql);
    }
}
