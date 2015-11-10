<?php

error_reporting(-1);
ini_set('memory_limit','1024M');

use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Connectors\ConnectionFactory;
use Store\Model\AbstractModel;
use Store\Service\ConfigService;

// fake EE constants
define('DEBUG', 1);
define('BASE', 'admin.php?');
define('PATH_THIRD', realpath(__DIR__.'/../..').'/');
define('URL_THIRD_THEMES', 'https://www.example.com/themes/third_party/');
define('CSRF_TOKEN', uniqid());

// autoload classes
require PATH_THIRD.'store/autoload.php';
require __DIR__.'/../tests/store/Factory.php';
require __DIR__.'/../tests/store/TestCase.php';

// hook up test database
$config = require __DIR__.'/config.php';
$factory = new ConnectionFactory(new Container);
$db = $factory->make($config);
$resolver = new ConnectionResolver(array('default' => $db));
$resolver->setDefaultConnection('default');
AbstractModel::setConnectionResolver($resolver);

// load default config
define('TEST_SITE_ID', 1);
$GLOBALS['mock_config'] = array('site_id' => TEST_SITE_ID);
$config = new ConfigService(null);
foreach ($config->settings as $key => $default) {
    $GLOBALS['mock_config'][$key] = store_setting_default($default);
}

function ee()
{
    return isset($GLOBALS['mock_ee']) ? $GLOBALS['mock_ee'] : null;
}

function get_instance()
{
    return ee();
}

function lang($key)
{
    if (!isset(ee()->lang->language['nav_store'])) {
        require PATH_THIRD.'store/language/english/store_lang.php';
        ee()->lang->language = array_merge(ee()->lang->language, $lang);
    }

    if (isset(ee()->lang->language[$key])) {
        return ee()->lang->language[$key];
    }

    throw new Exception("Missing language string: $key");
}

function config_item($key)
{
    return isset($GLOBALS['mock_config'][$key]) ? $GLOBALS['mock_config'][$key] : null;
}

class EE_Form_validation { public function __construct() {} }
class EE_Output { public function __construct() {} }
class Member_register { public function __construct() {} }

function form_dropdown() {}
function form_input() {}

/**
 * Simple implementation of cp_url() to allow testing links
 */
function cp_url($path, $qs)
{
    return $path.'?'.$qs;
}
