<?php

/**
 * Donators config file
 *
 * @package        donators
 * @author         Kevin Chatel
 * @link           http://www.signatureweb.ca
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
 */

if ( ! defined('SW_NAME'))
{
	define('SW_NAME',    'Donators');
	define('SW_PACKAGE', 'donators');
	define('SW_AUTHOR', 'Kevin Chatel (SignatureWeb.ca)');
	define('SW_VERSION', '1.0');
	define('SW_DOCS',    'http://');
}

/**
 * < EE 2.6.0 backward compat
 */
if ( ! function_exists('ee'))
{
	function ee()
	{
		static $EE;
		if ( ! $EE) $EE = get_instance();
		return $EE;
	}
}

/**
 * NSM Addon Updater
 */
$config['name']    = SW_NAME;
$config['version'] = SW_VERSION;
$config['nsm_addon_updater']['versions_xml'] = SW_DOCS.'/feed';
