<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

use Store\Update;

class Update126
{
    /**
     * Add indexes to frequently used columns for better performance
     */
    public function up()
    {
        Update::drop_index('store_carts', 'cart_hash');
        Update::create_index('store_carts', 'site_id');
        Update::create_index('store_carts', 'cart_hash', TRUE);
        Update::create_index('store_carts', 'date');
        Update::create_index('store_email_templates', 'site_id');
        Update::create_index('store_orders', 'site_id');
        Update::create_index('store_orders', 'order_hash', TRUE);
        Update::create_index('store_orders', 'member_id');
        Update::create_index('store_orders', 'order_date');
        Update::create_index('store_order_history', 'order_id');
        Update::create_index('store_order_items', 'order_id');
        Update::create_index('store_order_statuses', 'site_id');
        Update::create_index('store_order_statuses', 'display_order');
        Update::create_index('store_payments', 'order_id');
        Update::create_index('store_plugins', 'site_id');
        Update::create_index('store_plugins', 'plugin_type');
        Update::create_index('store_plugins', 'plugin_name');
        Update::create_index('store_plugins', 'display_order');
        Update::create_index('store_plugins', 'enabled');
        Update::create_index('store_product_modifiers', 'entry_id');
        Update::create_index('store_product_modifiers', 'mod_order');
        Update::create_index('store_product_options', 'product_mod_id');
        Update::create_index('store_product_options', 'opt_order');
        Update::create_index('store_promo_codes', 'site_id');
        Update::create_index('store_promo_codes', 'promo_code');
        Update::create_index('store_promo_codes', 'enabled');
        Update::create_index('store_shipping_rules', 'plugin_instance_id');
        Update::create_index('store_shipping_rules', 'country_code');
        Update::create_index('store_shipping_rules', 'region_code');
        Update::create_index('store_shipping_rules', 'postcode');
        Update::create_index('store_shipping_rules', 'priority');
        Update::create_index('store_shipping_rules', 'enabled');
        Update::create_index('store_stock', 'entry_id');
        Update::create_index('store_stock_options', 'entry_id');
        Update::create_index('store_stock_options', 'product_opt_id');
        Update::create_index('store_tax_rates', 'site_id');
        Update::create_index('store_tax_rates', 'enabled');
    }
}
