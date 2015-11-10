<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

use DateTimeZone;

class Update240
{
    public function up()
    {
        // add last_four and brand to transactions table
        if (!ee()->db->field_exists('brand', 'store_transactions')) {
            ee()->dbforge->add_column('store_transactions', array(
                'brand'             => array('type' => 'varchar', 'constraint' => 32),
                'last_four'         => array('type' => 'char', 'constraint' => 4)
            ));
        }

        // set default reporting timezone
        $timezone = config_item('default_site_timezone');
        if (!in_array($timezone, DateTimeZone::listIdentifiers())) {
            // timezone stored in old EE format...
            $timezone = ee()->localize->get_php_timezone($timezone);
        }
        ee()->store->config->update(array(
            'store_reporting_timezone' => $timezone,
        ));

        // let's clean up any pre-2.0 serialized modifiers
        do {
            $items = ee()->db->select(array('id', 'modifiers'))
                ->where('`modifiers` not like "[%"', null, false)
                ->limit(100)
                ->get('store_order_items')->result_array();

            foreach ($items as $item) {
                $modifiers = @unserialize(base64_decode($item['modifiers'])) ?: array(array());
                ee()->db->where('id', $item['id'])
                    ->update('store_order_items', array('modifiers' => json_encode($modifiers)));
            }
        } while (count($items) > 0);
    }
}
