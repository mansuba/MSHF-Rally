<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    show_error('Store requires PHP version 5.3+, you have '.PHP_VERSION);
}

// don't check version inside installer, seems to break on some installs
if (defined('APP_VER') && !defined('EE_APPPATH') && version_compare(APP_VER, '2.8.0', '<')) {
    show_error('Expresso Store requires ExpressionEngine version 2.8+, you have '.APP_VER);
}

if (!extension_loaded('curl')) {
    show_error('Expresso Store requires the PHP cURL extension to be installed on your server.');
}

// force PHP to use period as decimal point when formatting numbers
// (otherwise it causes SQL errors)
setlocale(LC_NUMERIC, 'C');

$composer = require __DIR__.'/vendor/autoload.php';

if (!defined('STORE_VERSION')) {
    define('STORE_VERSION', '2.4.1');

    /**
     * @deprecated Deprecated since v2.4.0, Please use store_cp_url() instead
     */
    define('STORE_CP', 'C=addons_modules&amp;M=show_module_cp&amp;module=store');

    // autoload EE classes we might need
    if (defined('PATH_MOD')) {
        $composer->addClassMap(array(
            'CI_Model' => BASEPATH.'core/Model.php',
            'Channel' => PATH_MOD.'channel/mod.channel.php',
            'Ip_to_nation_data' => PATH_MOD.'ip_to_nation/models/ip_to_nation_data.php',
            'Member' => PATH_MOD.'member/mod.member.php',
            'Member_register' => PATH_MOD.'member/mod.member_register.php',
        ));
    }

    // only initialize Store if called from EE or install wizard
    if (defined('APPPATH')) {
        $ee = ee();

        // load language file
        $ee->lang->loadfile('store');

        // support servers with PDO disabled
        if (!class_exists('PDO')) {
            class_alias('Illuminate\CodeIgniter\FakePDO', 'PDO');
        }

        // avoids PHP < 5.3 parse errors
        $container = 'Store\Container';
        $ee->store = new $container($ee);
        $ee->store->initialize();
    }
}

return $composer;
