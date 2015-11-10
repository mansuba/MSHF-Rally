<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

use Store\Update;

class Update132
{
    /**
     * Update old email templates to plain text
     * Add order_confirmation email to default order status
     */
    public function up()
    {
        $this->EE = get_instance();

        // Add stock ajax action for Safecracker compatibility
        Update::register_action('act_field_stock');

        // set emails with no html elements to plain text format
        $sql = "UPDATE ".$this->EE->db->protect_identifiers('store_email_templates', TRUE)."
            SET mail_format = 'text'
            WHERE contents NOT LIKE '%<p%'
                AND contents NOT LIKE '%<br%'
                AND contents NOT LIKE '%<table%'
                AND contents NOT LIKE '%<div%'";
        $this->EE->db->query($sql);

        // find order_confirmation email template id for each site
        $query = $this->EE->db->where('name', 'order_confirmation')
            ->get('store_email_templates')->result_array();
        $emails = array();
        foreach ($query as $row) {
            $emails[$row['site_id']] = $row['template_id'];
        }

        foreach ($emails as $site_id => $template_id) {
            // if no email template is set for the default status,
            // update it to the order_confirmation template
            $this->EE->db->where('site_id', $site_id)
                ->where('is_default', 'y')
                ->where('(email_template = 0 OR email_template IS NULL)')
                ->update('store_order_statuses', array('email_template' => $template_id));
        }
    }
}
