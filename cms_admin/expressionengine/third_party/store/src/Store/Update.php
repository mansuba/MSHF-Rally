<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

class Update
{
    public $version = STORE_VERSION;

    public static function register_hook($hook, $method = null, $priority = 10)
    {
        if (is_null($method)) {
            $method = $hook;
        }

        if (ee()->db->where('class', 'Store_ext')
            ->where('hook', $hook)
            ->count_all_results('extensions') == 0)
        {
            ee()->db->insert('extensions', array(
                'class'		=> 'Store_ext',
                'method'	=> $method,
                'hook'		=> $hook,
                'settings'	=> '',
                'priority'	=> $priority,
                'version'	=> STORE_VERSION,
                'enabled'	=> 'y'
            ));
        }
    }

    public static function register_action($method, $csrf_exempt = false)
    {
        if (ee()->db->where('class', 'Store')
            ->where('method', $method)
            ->count_all_results('actions') == 0)
        {
            ee()->db->insert('actions', array(
                'class' => 'Store',
                'method' => $method,
                'csrf_exempt' => (int) $csrf_exempt,
            ));
        }
    }

    public static function create_index($table_name, $col_names, $unique = false)
    {
        $table_name = ee()->db->protect_identifiers($table_name, true);

        if (is_array($col_names)) {
            $index_name = implode('_', $col_names);
            foreach ($col_names as $key => $col) {
                $col_names[$key] = ee()->db->protect_identifiers($col);
            }
            $col_names = implode(',', $col_names);
        } else {
            $index_name = $col_names;
            $col_names = ee()->db->protect_identifiers($col_names);
        }

        $sql = $unique ? "CREATE UNIQUE INDEX " : "CREATE INDEX ";
        $sql .= "$index_name ON $table_name ($col_names)";

        return ee()->db->query($sql);
    }

    public static function drop_index($table_name, $col_name)
    {
        $table_name = ee()->db->protect_identifiers($table_name, true);
        $col_name = ee()->db->protect_identifiers($col_name);
        $sql = "DROP INDEX $col_name ON $table_name";

        return ee()->db->query($sql);
    }

    public static function drop_column_if_exists($table_name, $col_name)
    {
        if (ee()->db->field_exists($col_name, $table_name)) {
            ee()->dbforge->drop_column($table_name, $col_name);
        }
    }

    /**
     * Empty constructor is required, otherwise PHP treats update() method as constructor
     * in PHP versions <= 5.3.2
     */
    public function __construct()
    {
    }

    public function install()
    {
        // first make sure there is no existing zombie data
        $this->uninstall();

        // register module
        ee()->db->insert('modules', array(
            'module_name' => 'Store',
            'module_version' => $this->version,
            'has_cp_backend' => 'y',
            'has_publish_fields' => 'n'));

        // register actions
        $this->register_action('act_checkout');
        $this->register_action('act_download_file');
        $this->register_action('act_payment');
        $this->register_action('act_payment_return', true);

        // register extension
        $this->register_hook('channel_entries_query_result');
        $this->register_hook('cp_menu_array');
        $this->register_hook('sessions_end');
        $this->register_hook('member_member_logout');

        // install store tables
        $schema = file_get_contents(PATH_THIRD.'store/data/schema.sql');
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) {
            ee()->db->query($sql);
        }

        // install first site (default to 1 if called from install wizard)
        $site_id = config_item('site_id') ?: 1;
        ee()->store->install->install_site($site_id);
        ee()->store->install->install_templates($site_id);

        return true;
    }

    public function update($current = '')
    {
        if ($this->version == $current) {
            return false;
        }

        ee()->load->dbforge();

        $updates = array(
            '1.1.3',
            '1.1.4',
            '1.2.2',
            '1.2.3',
            '1.2.4',
            '1.2.5',
            '1.2.6',
            '1.3.2',
            '1.5.0',
            '1.5.3',
            '1.6.0',
            '1.6.2',
            '2.0.0',
            '2.0.2',
            '2.0.4',
            '2.1.0',
            '2.2.0',
            '2.3.0',
        );

        foreach ($updates as $version) {
            if (version_compare($current, $version, '<')) {
                $this->_run_update($version);
            }
        }

        // update extension and fieldtype version numbers (doesn't happen automatically)
        ee()->db->where('class', 'Store_ext');
        ee()->db->update('extensions', array('version' => $this->version));

        ee()->db->where('name', strtolower('Store'));
        ee()->db->update('fieldtypes', array('version' => $this->version));

        return true;
    }

    protected function _run_update($version)
    {
        // run the update file
        $class_name = '\Store\Update\Update'.str_replace('.', '', $version);
        $updater = new $class_name;
        $updater->up();

        // record our progress
        ee()->db->where('module_name', 'Store')
            ->update('modules', array('module_version' => $version));
    }

    public function uninstall()
    {
        ee()->load->dbforge();

        // drop all current and past tables in case they still exist
        ee()->dbforge->drop_table('store_cache');
        ee()->dbforge->drop_table('store_carts');
        ee()->dbforge->drop_table('store_config');
        ee()->dbforge->drop_table('store_countries');
        ee()->dbforge->drop_table('store_discounts');
        ee()->dbforge->drop_table('store_email_templates');
        ee()->dbforge->drop_table('store_emails');
        ee()->dbforge->drop_table('store_order_adjustments');
        ee()->dbforge->drop_table('store_order_history');
        ee()->dbforge->drop_table('store_order_items');
        ee()->dbforge->drop_table('store_order_statuses');
        ee()->dbforge->drop_table('store_orders');
        ee()->dbforge->drop_table('store_payment_methods');
        ee()->dbforge->drop_table('store_payments');
        ee()->dbforge->drop_table('store_plugins');
        ee()->dbforge->drop_table('store_product_modifiers');
        ee()->dbforge->drop_table('store_product_options');
        ee()->dbforge->drop_table('store_products');
        ee()->dbforge->drop_table('store_promo_codes');
        ee()->dbforge->drop_table('store_regions');
        ee()->dbforge->drop_table('store_sales');
        ee()->dbforge->drop_table('store_shipping_methods');
        ee()->dbforge->drop_table('store_shipping_methods_old');
        ee()->dbforge->drop_table('store_shipping_rules');
        ee()->dbforge->drop_table('store_states');
        ee()->dbforge->drop_table('store_statuses');
        ee()->dbforge->drop_table('store_stock');
        ee()->dbforge->drop_table('store_stock_options');
        ee()->dbforge->drop_table('store_taxes_categories');
        ee()->dbforge->drop_table('store_tax_rates');
        ee()->dbforge->drop_table('store_taxes');
        ee()->dbforge->drop_table('store_transactions');

        ee()->db->where('class', 'Store');
        ee()->db->delete('actions');

        ee()->db->where('module_name', 'Store');
        ee()->db->delete('modules');

        ee()->db->where('class', 'Store_ext');
        ee()->db->delete('extensions');

        return true;
    }
}
