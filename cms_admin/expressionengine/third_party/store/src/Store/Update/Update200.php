<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Update;

class Update200
{
    private $site_ids;

    public function up()
    {
        $this->updateActions();
        $this->updateCache();
        $this->updateConfig();
        $this->updateCountries();
        $this->updateEmails();
        $this->updateOrders();
        $this->updatePaymentMethods();
        $this->updatePromotions();
        $this->updateProducts();
        $this->updateShipping();
        $this->updateStatuses();
        $this->updateStock();
        $this->updateTax();
        $this->updateTransactions();
        $this->updateBeta3();
    }

    public function updateActions()
    {
        // remove act_field_stock and act_add_to_cart actions
        ee()->db->where('class', 'Store')
            ->where_in('method', array('act_field_stock', 'act_add_to_cart'))
            ->delete('actions');
    }

    public function updateCache()
    {
        if (!ee()->db->table_exists('store_cache')) {
            ee()->dbforge->add_field(array(
                'key'           => array('type' => 'varchar', 'constraint' => 128),
                'value'         => array('type' => 'text'),
                'expiry_date'   => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
            ));
            ee()->dbforge->add_key('key', true);
            ee()->dbforge->add_key('expiry_date');
            ee()->dbforge->create_table('store_cache');
        }
    }

    public function updateConfig()
    {
        if (!ee()->db->field_exists('id', 'store_config')) {
            $this->renameTable('store_config', 'store_config_old');

            ee()->dbforge->add_field(array(
                'id'            => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'       => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
                'preference'    => array('type' => 'varchar', 'constraint' => 255),
                'value'         => array('type' => 'text'),
            ));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->create_table('store_config');

            $insert_config = array();
            $old_configs = ee()->db->get('store_config_old')->result();
            foreach ($old_configs as $row) {
                $values = @unserialize(base64_decode($row->store_preferences));
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        // map new order field names
                        if ($key == 'order_fields' && is_array($value)) {
                            $map = array(
                                'billing_name'      => 'billing_first_name',
                                'billing_address3'  => 'billing_city',
                                'billing_region'    => 'billing_state',
                                'shipping_name'     => 'shipping_first_name',
                                'shipping_address3' => 'shipping_city',
                                'shipping_region'   => 'shipping_state',
                            );
                            foreach ($map as $old => $new) {
                                if (isset($value[$old])) {
                                    $value[$new] = $value[$old];
                                    unset($value[$old]);
                                }
                            }
                        }

                        // upgrade legacy weight units from <= 1.2.2
                        if ($key == 'weight_units' && $value = 'lbs') {
                            $value = 'lb';
                        }

                        // upgrade ridiculous 'y' and 'n' values
                        if ($value == 'y') {
                            $value = 1;
                        } elseif ($value == 'n') {
                            $value = 0;
                        }

                        // uppercase country code
                        if ($key == 'default_country') {
                            $value = strtoupper($value);
                        }
                        // rename default region
                        if ($key == 'default_region') {
                            $key = 'default_state';
                        }

                        $insert_config[] = array(
                            'site_id' => $row->site_id,
                            'preference' => 'store_'.$key,
                            'value' => json_encode($value),
                        );
                    }
                }
            }

            if (!empty($insert_config)) {
                ee()->db->insert_batch('store_config', $insert_config);
            }
        }

        // delete any really old preferences from exp_modules table
        if (ee()->db->field_exists('settings', 'modules')) {
            ee()->db->where('module_name', 'Store')->update('modules', array('settings' => null));
        }

        ee()->dbforge->drop_table('store_config_old');
    }

    public function updateCountries()
    {
        // upgrade countries
        if ( ! ee()->db->field_exists('id', 'store_countries')) {
            $this->renameTable('store_countries', 'store_countries_old');

            ee()->dbforge->add_field(array(
                'id'            => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'       => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
                'code'          => array('type' => 'char', 'constraint' => 2, 'null' => false, 'default' => ''),
                'name'          => array('type' => 'varchar', 'constraint' => 255, 'null' => false, 'default' => ''),
                'enabled'       => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0)));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->create_table('store_countries');

            // load countries list
            $countries = require(PATH_THIRD.'store/data/countries.php');

            $data = array();
            foreach ($this->getSiteIds() as $site_id) {
                foreach ($countries as $code => $name) {
                    $data[] = array(
                        'site_id' => $site_id,
                        'code' => $code,
                        'name' => $name,
                        'enabled' => 0,
                    );
                }
            }

            // insert countries
            ee()->db->insert_batch('store_countries', $data);
            ee()->db->query('UPDATE `exp_store_countries` SET `enabled` = 1 WHERE (site_id, code)
                IN (SELECT site_id, UPPER(country_code) FROM `exp_store_countries_old`)'
            );
        }

        // upgrade regions
        if ( ! ee()->db->table_exists('store_states')) {
            $this->renameTable('store_regions', 'store_regions_old');

            ee()->dbforge->add_field(array(
                'id'            => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'       => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
                'country_id'    => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
                'code'          => array('type' => 'varchar', 'constraint' => 5, 'null' => false, 'default' => ''),
                'name'          => array('type' => 'varchar', 'constraint' => 255, 'null' => false, 'default' => '')));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key('country_id');
            ee()->dbforge->create_table('store_states');

            ee()->db->query('INSERT INTO `exp_store_states` (site_id, country_id, code, name)
                SELECT r.site_id, c.id, r.region_code, r.region_name
                FROM `exp_store_regions_old` r
                JOIN `exp_store_countries` c ON (c.site_id = r.site_id AND c.code = UPPER(r.country_code))'
            );
        }

        ee()->dbforge->drop_table('store_countries_old');
        ee()->dbforge->drop_table('store_regions_old');
    }

    public function updateEmails()
    {
        // upgrade email templates
        if (ee()->db->table_exists('store_email_templates')) {
            $this->renameTable('store_email_templates', 'store_emails');

            ee()->dbforge->modify_column('store_emails', array(
                'template_id'   => array('name' => 'id', 'type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
            ));

            ee()->dbforge->add_column('store_emails', array(
                'to'            => array('type' => 'varchar', 'constraint' => 255)
            ), 'bcc');

            $this->fixBooleanColumn('store_emails', 'word_wrap');
            $this->fixBooleanColumn('store_emails', 'enabled');
            ee()->db->set('to', '{order_email}')->update('store_emails');
        }
    }

    public function updateOrders()
    {
        // remove carts table, carts are now stored as incomplete orders
        ee()->dbforge->drop_table('store_carts');

        // upgrade orders
        if (ee()->db->field_exists('order_id', 'store_orders')) {
            ee()->dbforge->modify_column('store_orders', array(
                'order_id'                  => array('name' => 'id', 'type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'order_status'              => array('name' => 'order_status_name', 'type' => 'varchar', 'constraint' => 20),
                'order_status_member'       => array('name' => 'order_status_member_id', 'type' => 'int', 'constraint' => 10),
                'shipping_method'           => array('name' => 'shipping_method_name', 'type' => 'varchar', 'constraint' => 255),
                'billing_name'              => array('name' => 'billing_name_old', 'type' => 'varchar', 'constraint' => 255),
                'billing_address3'          => array('name' => 'billing_city', 'type' => 'varchar', 'constraint' => 255),
                'billing_region'            => array('name' => 'billing_state', 'type' => 'varchar', 'constraint' => 255),
                'shipping_name'             => array('name' => 'shipping_name_old', 'type' => 'varchar', 'constraint' => 255),
                'shipping_address3'         => array('name' => 'shipping_city', 'type' => 'varchar', 'constraint' => 255),
                'shipping_region'           => array('name' => 'shipping_state', 'type' => 'varchar', 'constraint' => 255),
                'promo_code_id'             => array('name' => 'discount_id', 'type' => 'int', 'constraint' => 10, 'unsigned' => true),
                'promo_code'                => array('name' => 'promo_code', 'type' => 'varchar', 'constraint' => 255),
                'order_qty'                 => array('name' => 'order_qty', 'type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0),
                'order_subtotal'            => array('name' => 'order_subtotal', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_subtotal_tax'        => array('name' => 'order_subtotal_tax', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_discount'            => array('name' => 'order_discount', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_discount_tax'        => array('name' => 'order_discount_tax', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_shipping'            => array('name' => 'order_shipping', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_shipping_tax'        => array('name' => 'order_shipping_tax', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_handling'            => array('name' => 'order_handling', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_handling_tax'        => array('name' => 'order_handling_tax', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_tax'                 => array('name' => 'order_tax', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_total'               => array('name' => 'order_total', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'order_paid'                => array('name' => 'order_paid', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'tax_rate'                  => array('name' => 'tax_rate', 'type' => 'decimal', 'constraint' => '8,5', 'null' => false, 'default' => 0),
                'order_length'              => array('name' => 'order_length', 'type' => 'double', 'null' => false, 'default' => 0),
                'order_width'               => array('name' => 'order_width', 'type' => 'double', 'null' => false, 'default' => 0),
                'order_height'              => array('name' => 'order_height', 'type' => 'double', 'null' => false, 'default' => 0),
                'dimension_units'           => array('name' => 'dimension_units', 'type' => 'varchar', 'constraint' => 5, 'null' => false, 'default' => ''),
                'order_weight'              => array('name' => 'order_weight', 'type' => 'double', 'null' => false, 'default' => 0),
                'weight_units'              => array('name' => 'weight_units', 'type' => 'varchar', 'constraint' => 5, 'null' => false, 'default' => ''),
            ));
            ee()->dbforge->add_column('store_orders', array(
                'order_status_message'      => array('type' => 'text'),
            ), 'order_status_member_id');

            ee()->dbforge->add_column('store_orders', array(
                'billing_last_name'          => array('type' => 'varchar', 'constraint' => 255),
                'billing_first_name'         => array('type' => 'varchar', 'constraint' => 255),
            ), 'billing_name_old');

            ee()->dbforge->add_column('store_orders', array(
                'shipping_last_name'         => array('type' => 'varchar', 'constraint' => 255),
                'shipping_first_name'         => array('type' => 'varchar', 'constraint' => 255),
            ), 'shipping_name_old');

            ee()->dbforge->add_column('store_orders', array(
                'ip_country'                => array('type' => 'char', 'constraint' => 2),
                'member_data_loaded'        => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'tax_shipping'              => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'accept_terms'              => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'register_member'           => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'username'                  => array('type' => 'varchar', 'constraint' => 255),
                'screen_name'               => array('type' => 'varchar', 'constraint' => 255),
                'password_hash'             => array('type' => 'varchar', 'constraint' => 255),
                'password_salt'             => array('type' => 'varchar', 'constraint' => 255),
            ));

            // split names
            ee()->db->query("UPDATE `exp_store_orders`
                SET `billing_first_name` = IF(LOCATE(' ', `billing_name_old`) > 0, SUBSTRING(`billing_name_old`, 1, LOCATE(' ', `billing_name_old`) - 1), `billing_name_old`),
                `billing_last_name` = IF(LOCATE(' ', `billing_name_old`) > 0, SUBSTRING(`billing_name_old`, LOCATE(' ', `billing_name_old`) + 1), NULL),
                `shipping_first_name` = IF(LOCATE(' ', `shipping_name_old`) > 0, SUBSTRING(`shipping_name_old`, 1, LOCATE(' ', `shipping_name_old`) - 1), `shipping_name_old`),
                `shipping_last_name` = IF(LOCATE(' ', `shipping_name_old`) > 0, SUBSTRING(`shipping_name_old`, LOCATE(' ', `shipping_name_old`) + 1), NULL)"
            );

            ee()->dbforge->drop_column('store_orders', 'billing_name_old');
            ee()->dbforge->drop_column('store_orders', 'shipping_name_old');
        }

        ee()->db->set('billing_country', 'UPPER(billing_country)', false)
            ->set('shipping_country', 'UPPER(shipping_country)', false)
            ->update('store_orders');

        ee()->db->set('billing_country', 'GB')
            ->where('billing_country', 'UK')->update('store_orders');
        ee()->db->set('shipping_country', 'GB')
            ->where('shipping_country', 'UK')->update('store_orders');

        $this->fixBooleanColumn('store_orders', 'billing_same_as_shipping');
        $this->fixBooleanColumn('store_orders', 'shipping_same_as_billing');

        // upgrade order items
        if ( ! ee()->db->field_exists('id', 'store_order_items')) {
            $this->fixBooleanColumn('store_order_items', 'on_sale');
            $this->fixBooleanColumn('store_order_items', 'free_shipping');
            $this->fixBooleanColumn('store_order_items', 'tax_exempt');

            ee()->dbforge->modify_column('store_order_items', array(
                'order_item_id'         => array('name' => 'id', 'type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'price'                 => array('name' => 'price', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'price_inc_tax'         => array('name' => 'price_inc_tax_old', 'type' => 'decimal', 'constraint' => '19,4', 'null' => true),
                'regular_price'         => array('name' => 'regular_price', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'regular_price_inc_tax' => array('name' => 'regular_price_inc_tax_old', 'type' => 'decimal', 'constraint' => '19,4', 'null' => true),
                'handling'              => array('name' => 'handling', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'handling_tax'          => array('name' => 'handling_tax', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'item_qty'              => array('name' => 'item_qty', 'type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0),
                'item_subtotal'         => array('name' => 'item_subtotal', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'item_tax'              => array('name' => 'item_tax', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'item_total'            => array('name' => 'item_total', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'sku'                   => array('name' => 'sku', 'type' => 'varchar', 'constraint' => 255, 'null' => true),
            ));

            // cache URL title, channel ID, and category IDs
            ee()->dbforge->add_column('store_order_items', array(
                'category_ids'              => array('type' => 'varchar', 'constraint' => 255),
                'channel_id'                => array('type' => 'int', 'constraint' => 10, 'null' => false, 'default' => 0),
                'url_title'                 => array('type' => 'varchar', 'constraint' => 255, 'null' => false, 'default' => ''),
            ), 'title');
            ee()->db->query('UPDATE `exp_store_order_items` item, `exp_channel_titles` entry SET item.url_title = entry.url_title, item.channel_id = entry.channel_id WHERE item.entry_id = entry.entry_id');

            // add site_id
            ee()->dbforge->add_column('store_order_items', array(
                'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
            ), 'id');
            ee()->db->query('UPDATE `exp_store_order_items` i, `exp_store_orders` o SET i.site_id = o.site_id WHERE i.order_id = o.id');

            // add stock_id
            ee()->dbforge->add_column('store_order_items', array(
                'stock_id' => array('type' => 'int', 'constraint' => 10, 'null' => false, 'default' => 0),
            ), 'entry_id');
        }

        // add order adjustments
        if ( ! ee()->db->table_exists('store_order_adjustments')) {
            ee()->dbforge->add_field(array(
                'id'            => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'       => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
                'order_id'      => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
                'name'          => array('type' => 'varchar', 'constraint' => 255, 'null' => false, 'default' => ''),
                'type'          => array('type' => 'varchar', 'constraint' => 32, 'null' => false, 'default' => ''),
                'rate'          => array('type' => 'decimal', 'constraint' => '8,5', 'null' => false, 'default' => 0),
                'amount'        => array('type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'taxable'       => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'included'      => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'sort'          => array('type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0)));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key('order_id');
            ee()->dbforge->create_table('store_order_adjustments');
        }

        // upgrade order history
        if (!ee()->db->field_exists('id', 'store_order_history')) {
            ee()->dbforge->modify_column('store_order_history', array(
                'order_history_id'      => array('name' => 'id', 'type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'order_status'          => array('name' => 'order_status_name', 'type' => 'varchar', 'constraint' => 20, 'null' => false, 'default' => ''),
                'order_status_member'   => array('name' => 'order_status_member_id', 'type' => 'int', 'constraint' => 10, 'null' => false, 'default' => 0),
                'message'               => array('name' => 'order_status_message', 'type' => 'text'),
            ));
        }
    }

    public function updatePaymentMethods()
    {
        // upgrade payment methods
        if (ee()->db->field_exists('payment_method_id', 'store_payment_methods')) {
            $this->renameTable('store_payment_methods', 'store_payment_methods_old');

            ee()->dbforge->add_field(array(
                'id'            => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'       => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
                'class'         => array('type' => 'varchar', 'constraint' => 255),
                'title'         => array('type' => 'varchar', 'constraint' => 255),
                'settings'      => array('type' => 'text'),
                'enabled'       => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
            ));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->create_table('store_payment_methods');

            // migrate payment methods
            ee()->db->query('INSERT INTO `exp_store_payment_methods` (id, site_id, class,
                    title, settings, enabled)
                SELECT payment_method_id, site_id, '.$this->getPaymentMethodMapSql('name').',
                    title, settings, enabled
                FROM `exp_store_payment_methods_old`');

            // convert settings to JSON
            $payment_methods = ee()->db->get('store_payment_methods')->result();
            foreach ($payment_methods as $method) {
                if (!empty($method->settings) && !in_array(substr($method->settings, 0, 1), array('{', '['))) {
                    ee()->db->where('id', $method->id)
                        ->set('settings', @json_encode(unserialize(base64_decode($method->settings))))
                        ->update('store_payment_methods');
                }
            }
        }

        ee()->dbforge->drop_table('store_payment_methods_old');
    }

    public function updatePromotions()
    {
        if (ee()->db->table_exists('store_promo_codes')) {
            $this->fixBooleanColumn('store_promo_codes', 'enabled');
            $this->fixBooleanColumn('store_promo_codes', 'free_shipping');
            $this->renameTable('store_promo_codes', 'store_promo_codes_old');

            // create new discounts table
            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'           => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
                'name'              => array('type' => 'varchar', 'constraint' => 255),
                'code'              => array('type' => 'varchar', 'constraint' => 255, 'null' => false, 'default' => ''),
                'start_date'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => true),
                'end_date'          => array('type' => 'int', 'constraint' => 10, 'unsigned' => true),
                'member_group_ids'  => array('type' => 'varchar', 'constraint' => 255),
                'entry_ids'         => array('type' => 'varchar', 'constraint' => 255),
                'category_ids'      => array('type' => 'varchar', 'constraint' => 255),
                'exclude_on_sale'   => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'type'              => array('type' => 'varchar', 'constraint' => 10, 'null' => false, 'default' => ''),
                'purchase_qty'      => array('type' => 'int', 'constraint' => 4, 'unsigned' => true),
                'purchase_total'    => array('type' => 'decimal', 'constraint' => '19,4'),
                'step_qty'          => array('type' => 'int', 'constraint' => 4, 'unsigned' => true),
                'discount_qty'      => array('type' => 'int', 'constraint' => 4, 'unsigned' => true),
                'base_discount'     => array('type' => 'decimal', 'constraint' => '19,4'),
                'per_item_discount' => array('type' => 'decimal', 'constraint' => '19,4'),
                'percent_discount'  => array('type' => 'decimal', 'constraint' => '8,5'),
                'free_shipping'     => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'per_user_limit'    => array('type' => 'int', 'constraint' => 4, 'unsigned' => true),
                'total_use_limit'   => array('type' => 'int', 'constraint' => 4, 'unsigned' => true),
                'total_use_count'   => array('type' => 'int', 'constraint' => 4, 'unsigned' => true),
                'break'             => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'notes'             => array('type' => 'text'),
                'enabled'           => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'sort'              => array('type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0),
            ));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key('site_id');
            ee()->dbforge->create_table('store_discounts');

            // create new sales table
            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'           => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
                'name'              => array('type' => 'varchar', 'constraint' => 255),
                'start_date'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => true),
                'end_date'          => array('type' => 'int', 'constraint' => 10, 'unsigned' => true),
                'member_group_ids'  => array('type' => 'varchar', 'constraint' => 255),
                'entry_ids'         => array('type' => 'varchar', 'constraint' => 255),
                'category_ids'      => array('type' => 'varchar', 'constraint' => 255),
                'per_item_discount' => array('type' => 'decimal', 'constraint' => '19,4'),
                'percent_discount'  => array('type' => 'decimal', 'constraint' => '8,5'),
                'notes'             => array('type' => 'text'),
                'enabled'           => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'sort'              => array('type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0),
            ));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key('site_id');
            ee()->dbforge->create_table('store_sales');

            // import old data
            ee()->db->query('INSERT INTO `exp_store_discounts` (id, site_id, name, code,
                    start_date, end_date, member_group_ids, type, base_discount, percent_discount,
                    free_shipping, per_user_limit, total_use_limit, total_use_count, break, notes,
                    enabled)
                SELECT promo_code_id, site_id, '.ee()->db->escape(lang('promo_code')).',
                    promo_code, start_date, end_date, member_group_id, "items",
                    CASE type WHEN "p" THEN 0 ELSE value END,
                    CASE type WHEN "p" THEN value ELSE 0 END,
                    free_shipping, per_user_limit, use_limit, use_count, 1, description, enabled
                FROM `exp_store_promo_codes_old`');
        }

        ee()->dbforge->drop_table('store_promo_codes_old');
    }

    public function updateProducts()
    {
        // upgrade products
        if (ee()->db->field_exists('dimension_l', 'store_products')) {
            $this->fixBooleanColumn('store_products', 'sale_price_enabled');
            $this->fixBooleanColumn('store_products', 'free_shipping');
            $this->fixBooleanColumn('store_products', 'tax_exempt');

            ee()->dbforge->modify_column('store_products', array(
                'regular_price'         => array('name' => 'price', 'type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'dimension_l'           => array('name' => 'length', 'type' => 'double', 'null' => true),
                'dimension_w'           => array('name' => 'width', 'type' => 'double', 'null' => true),
                'dimension_h'           => array('name' => 'height', 'type' => 'double', 'null' => true),
                'weight'                => array('name' => 'weight', 'type' => 'double', 'null' => true),
                'handling'              => array('name' => 'handling', 'type' => 'decimal', 'constraint' => '19,4', 'null' => true),
            ));

            foreach (array('length', 'width', 'height', 'weight', 'handling') as $key) {
                ee()->db->where($key, 0)->set($key, null)->update('store_products');
            }

            // migrate existing sales
            ee()->db->query('INSERT INTO `exp_store_sales` (site_id, name,
                    start_date, end_date, entry_ids, per_item_discount, enabled)
                SELECT t.site_id, t.title, p.sale_start_date, p.sale_end_date, t.entry_id,
                    p.price - p.sale_price, p.sale_price_enabled
                FROM `exp_store_products` p
                JOIN `exp_channel_titles` t ON t.entry_id = p.entry_id
                WHERE p.sale_price > 0 OR p.sale_price_enabled = 1');

            ee()->dbforge->drop_column('store_products', 'sale_price');
            ee()->dbforge->drop_column('store_products', 'sale_price_enabled');
            ee()->dbforge->drop_column('store_products', 'sale_start_date');
            ee()->dbforge->drop_column('store_products', 'sale_end_date');
        }
    }

    public function updateShipping()
    {
        // upgrade shipping methods
        if (ee()->db->field_exists('shipping_method_id', 'store_shipping_methods')) {
            $this->renameTable('store_shipping_methods', 'store_shipping_methods_old');

            // create new shipping methods table
            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'           => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
                'name'              => array('type' => 'varchar', 'constraint' => 255),
                'enabled'           => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'sort'              => array('type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0),
            ));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key('site_id');
            ee()->dbforge->create_table('store_shipping_methods');

            // migrate shipping plugins
            $old_methods = ee()->db->order_by('display_order')->get('store_shipping_methods_old')->result();
            $insert_plugins = array();
            $insert_methods = array();
            foreach ($old_methods as $method) {
                // copy default shipping methods to new shipping methods table
                if ($method->class == 'Store_shipping_default') {
                    $insert_methods[] = array(
                        'id'        => $method->shipping_method_id,
                        'site_id'   => $method->site_id,
                        'name'      => $method->title,
                        'enabled'   => $method->enabled,
                        'sort'      => $method->display_order,
                    );
                }
            }

            if (!empty($insert_methods)) {
                ee()->db->insert_batch('store_shipping_methods', $insert_methods);
            }
        }

        // upgrade shipping rules
        if (ee()->db->field_exists('shipping_rule_id', 'store_shipping_rules')) {
            ee()->dbforge->modify_column('store_shipping_rules', array(
                'shipping_rule_id'  => array('name' => 'id', 'type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'title'             => array('name' => 'name', 'type' => 'varchar', 'constraint' => 255, 'null' => true),
                'country_code'      => array('name' => 'country_code', 'type' => 'char', 'constraint' => 2, 'null' => true),
                'region_code'       => array('name' => 'state_code', 'type' => 'varchar', 'constraint' => 5, 'null' => true),
                'base_rate'         => array('name' => 'base_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => true),
                'per_item_rate'     => array('name' => 'per_item_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => true),
                'per_weight_rate'   => array('name' => 'per_weight_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => true),
                'percent_rate'      => array('name' => 'percent_rate', 'type' => 'double', 'null' => true),
                'min_rate'          => array('name' => 'min_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => true),
                'max_rate'          => array('name' => 'max_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => true),
            ));
            ee()->dbforge->add_column('store_shipping_rules', array(
                'sort'              => array('type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0),
            ));

            // add nulls to make CP pretty
            foreach (array('base_rate', 'per_item_rate', 'per_weight_rate', 'percent_rate',
                    'min_rate', 'max_rate') as $key) {
                ee()->db->where($key, 0)->set($key, null)->update('store_shipping_rules');
            }

            // update columns based on old hard coded priority order
            $rules = ee()->db->select('*,
                    IF (`country_code` = "", 1, 0) AS `country_code_order`,
                    IF (`state_code` = "", 1, 0) AS `state_code_order`,
                    IF (`postcode` = "", 1, 0) AS `postcode_order`', false)
                ->order_by('priority DESC, country_code_order ASC, country_code ASC,
                    state_code_order ASC, state_code ASC, postcode_order ASC, postcode ASC,
                    min_order_qty ASC, max_order_qty ASC, min_order_total ASC, max_order_qty ASC,
                    min_weight ASC, max_weight ASC')
                ->get('store_shipping_rules')->result();

            $sort = 0;
            foreach ($rules as $rule) {
                ee()->db->where('id', $rule->id)
                    ->set('sort', $sort++)
                    ->set('country_code', strtoupper($rule->country_code) == 'UK' ? 'GB' : strtoupper($rule->country_code))
                    ->update('store_shipping_rules');
            }

            ee()->dbforge->drop_column('store_shipping_rules', 'priority');
        }
    }

    public function updateStatuses()
    {
        if (ee()->db->table_exists('store_order_statuses')) {
            $this->fixBooleanColumn('store_order_statuses', 'is_default');
            $this->renameTable('store_order_statuses', 'store_order_statuses_old');

            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'           => array('type' => 'int', 'constraint' => 5, 'null' => false, 'default' => 0),
                'name'              => array('type' => 'varchar', 'constraint' => 255),
                'color'             => array('type' => 'varchar', 'constraint' => 255),
                'email_ids'         => array('type' => 'varchar', 'constraint' => 255),
                'sort'              => array('type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0),
                'is_default'        => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
            ));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key('site_id');
            ee()->dbforge->create_table('store_statuses');

            ee()->db->query('INSERT INTO `exp_store_statuses` (id, site_id, name, color,
                    email_ids, sort, is_default)
                SELECT order_status_id, site_id, name,
                    CASE highlight WHEN "" THEN NULL ELSE CONCAT("#", highlight) END,
                    email_template, display_order, is_default
                FROM `exp_store_order_statuses_old`');
        }

        ee()->dbforge->drop_table('store_order_statuses_old');
    }

    public function updateStock()
    {
        // upgrade stock
        if ( ! ee()->db->field_exists('id', 'store_stock')) {
            $this->fixBooleanColumn('store_stock', 'track_stock');

            $this->renameTable('store_stock', 'store_stock_old');

            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'entry_id'          => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false),
                'sku'               => array('type' => 'varchar', 'constraint' => 255),
                'stock_level'       => array('type' => 'int', 'constraint' => 4),
                'min_order_qty'     => array('type' => 'int', 'constraint' => 4),
                'track_stock'       => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0)));

            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key('entry_id');
            ee()->dbforge->create_table('store_stock');

            ee()->db->query('INSERT INTO `exp_store_stock` (entry_id, sku, stock_level, min_order_qty, track_stock) SELECT entry_id, sku, stock_level, min_order_qty, track_stock FROM `exp_store_stock_old`');
        }

        // upgrade stock options (insert pun here)
        if ( ! ee()->db->field_exists('id', 'store_stock_options')) {
            $this->renameTable('store_stock_options', 'store_stock_options_old');

            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'stock_id'          => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false),
                'entry_id'          => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false),
                'sku'               => array('type' => 'varchar', 'constraint' => 255),
                'product_mod_id'    => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false),
                'product_opt_id'    => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false)));

            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key('stock_id');
            ee()->dbforge->add_key('product_mod_id');
            ee()->dbforge->create_table('store_stock_options');

            ee()->db->query('INSERT INTO `exp_store_stock_options` (stock_id, entry_id, sku, product_mod_id, product_opt_id) SELECT 0, entry_id, sku, product_mod_id, product_opt_id FROM `exp_store_stock_options_old`');

            // update new stock_id relationship
            ee()->db->query('UPDATE `exp_store_stock_options` o, `exp_store_stock` s SET o.stock_id = s.id WHERE o.sku = s.sku');
        }

        ee()->dbforge->drop_table('store_stock_old');
        ee()->dbforge->drop_table('store_stock_options_old');
    }

    public function updateTax()
    {
        // upgrade tax rates
        if (ee()->db->table_exists('store_tax_rates')) {
            $this->renameTable('store_tax_rates', 'store_tax_rates_old');

            // create taxes table
            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'           => array('type' => 'int', 'constraint' => 5, 'null' => false),
                'name'              => array('type' => 'varchar', 'constraint' => 255, 'null' => false),
                'rate'              => array('type' => 'decimal', 'constraint' => '8,5', 'null' => false),
                'country_code'      => array('type' => 'char', 'constraint' => 2),
                'state_code'        => array('type' => 'varchar', 'constraint' => 5),
                'apply_to_shipping' => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'included'          => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'enabled'           => array('type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
                'sort'              => array('type' => 'int', 'constraint' => 4, 'unsigned' => true, 'null' => false, 'default' => 0),
            ));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->create_table('store_taxes');

            // create taxes_categories table
            ee()->dbforge->add_field(array(
                'tax_id'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => true),
                'category_id'   => array('type' => 'int', 'constraint' => 10, 'unsigned' => true),
            ));
            ee()->dbforge->add_key('tax_id', true);
            ee()->dbforge->add_key('category_id', true);
            ee()->dbforge->create_table('store_taxes_categories');

            ee()->db->query('INSERT INTO `exp_store_taxes` (id, site_id, name, rate, country_code, state_code, apply_to_shipping, enabled)
                SELECT tax_id, site_id, tax_name, tax_rate,
                    CASE country_code WHEN "*" THEN NULL WHEN "uk" THEN "GB" ELSE UPPER(country_code) END,
                    CASE region_code WHEN "*" THEN NULL ELSE region_code END,
                    tax_shipping, enabled FROM `exp_store_tax_rates_old`');
        }

        ee()->dbforge->drop_table('store_tax_rates_old');
    }

    public function updateTransactions()
    {
        if (ee()->db->table_exists('store_payments')) {
            $this->renameTable('store_payments', 'store_payments_old');

            // create transactions table
            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true),
                'site_id'           => array('type' => 'int', 'constraint' => 5, 'null' => false),
                'order_id'          => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
                'member_id'         => array('type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
                'parent_id'         => array('type' => 'int', 'constraint' => 10, 'unsigned' => true),
                'hash'              => array('type' => 'varchar', 'constraint' => 32, 'null' => false, 'default' => ''),
                'date'              => array('type' => 'int', 'constraint' => 10, 'unsigned' => true),
                'payment_method'    => array('type' => 'varchar', 'constraint' => 255),
                'type'              => array('type' => 'varchar', 'constraint' => 10),
                'amount'            => array('type' => 'decimal', 'constraint' => '19,4', 'null' => false, 'default' => 0),
                'status'            => array('type' => 'varchar', 'constraint' => 10),
                'reference'         => array('type' => 'varchar', 'constraint' => 255),
                'message'           => array('type' => 'text'),
                'response_data'     => array('type' => 'text'),
            ));
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key('hash');
            ee()->dbforge->create_table('store_transactions');

            // insert old payment data
            ee()->db->query('INSERT INTO `exp_store_transactions` (id, site_id, order_id,
                    member_id, hash, `date`, payment_method, type, amount, status, reference,
                    message)
                SELECT payment_id, o.site_id, p.order_id, p.member_id, p.payment_hash,
                    p.payment_date, '.$this->getPaymentMethodMapSql('p.payment_method').',
                    CASE p.payment_status WHEN "authorized" THEN "authorize" ELSE "purchase" END,
                    p.amount,
                    CASE p.payment_status WHEN "authorized" THEN "success"
                        WHEN "complete" THEN "success" ELSE p.payment_status END,
                    p.reference, p.message
                FROM `exp_store_payments_old` p
                JOIN `exp_store_orders` o ON o.id = p.order_id');
        }

        ee()->dbforge->drop_table('store_payments_old');
    }

    public function updateBeta3()
    {
        // change shipping_method on orders table to string
        if (ee()->db->field_exists('shipping_method_id', 'store_orders')) {
            ee()->dbforge->modify_column('store_orders', array(
                'shipping_method_id'        => array('name' => 'shipping_method', 'type' => 'varchar', 'constraint' => 255, 'null' => true),
                'shipping_method_plugin'    => array('name' => 'shipping_method_class', 'type' => 'varchar', 'constraint' => 255, 'null' => true),
            ));
        }
    }

    protected function getSiteIds()
    {
        if (null === $this->site_ids) {
            $this->site_ids = array_map(
                function($row) {
                    return $row->site_id;
                },
                ee()->db->select('site_id')->get('sites')->result()
            );
        }

        return $this->site_ids;
    }

    /**
     * Convert columns from silly y/n format to TINYINT(1)
     */
    protected function fixBooleanColumn($table, $column)
    {
        // migrate existing data
        ee()->db->where("CAST($column AS CHAR) = 'y'")->update($table, array($column => 1));
        ee()->db->where("CAST($column AS CHAR) != '1'")->update($table, array($column => 0));

        // fix column definition
        ee()->dbforge->modify_column($table, array(
            $column => array('name' => $column, 'type' => 'tinyint', 'constraint' => 1, 'null' => false, 'default' => 0),
        ));
    }

    protected function getPaymentMethodMapSql($column)
    {
        $map = array(
            '2checkout' => 'TwoCheckout',
            'authorize_net' => 'AuthorizeNet_AIM',
            'authorize_net_sim' => 'AuthorizeNet_SIM',
            'buckaroo' => 'Buckaroo',
            'cardsave' => 'CardSave',
            'dps_pxpay' => 'PaymentExpress_PxPay',
            'dps_pxpost' => 'PaymentExpress_PxPost',
            'dummy' => 'Dummy',
            'eway' => 'Eway',
            'gocardless' => 'GoCardless',
            'ideal' => 'Ideal',
            'manual' => 'Manual',
            'mollie' => 'Mollie',
            'netaxept' => 'Netaxept',
            'ogone_directlink' => 'Ogone_DirectLink',
            'payflow_pro' => 'Payflow_Pro',
            'paymate' => 'Paymate',
            'paypal_express' => 'PayPal_Express',
            'paypal_pro' => 'PayPal_Pro',
            'rabo_omnikassa' => 'Rabo_Omnikassa',
            'sagepay_direct' => 'SagePay_Direct',
            'sagepay_server' => 'SagePay_Server',
            'stripe' => 'Stripe',
            'webteh_direct' => 'Webteh',
            'worldpay' => 'Worldpay',
        );

        $sql = "CASE $column";
        foreach ($map as $old => $new) {
            $sql .= ' WHEN '.ee()->db->escape($old).' THEN '.ee()->db->escape($new);
        }
        $sql .= " ELSE $column END";

        return $sql;
    }

    /**
     * EE versions aren't consistent whether they add the table prefix or not
     */
    protected function renameTable($old, $new)
    {
        $old = ee()->db->protect_identifiers($old, true);
        $new = ee()->db->protect_identifiers($new, true);
        ee()->db->query("ALTER TABLE $old RENAME TO $new");
    }
}
