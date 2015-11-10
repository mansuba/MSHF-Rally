<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Default config
 *
 * @package		Default module
 * @category	Modules
 * @author		Rein de Vries <info@reinos.nl>
 * @link		http://dmlogic.com/blog/protecting-expression-engine-entries-from-accidental-deletion/
 * @link		http://reinos.nl/add-ons/protected-entries
 * @copyright 	Copyright (c) 2013 Reinos.nl Internet Media
 */

//contants
if ( ! defined('PROTECTED_ENTRIES_NAME'))
{
	define('PROTECTED_ENTRIES_NAME', 'Protected Entries');
	define('PROTECTED_ENTRIES_CLASS', 'Protected_entries');
	define('PROTECTED_ENTRIES_MAP', 'protected_entries');
	define('PROTECTED_ENTRIES_VERSION', '1.2');
	define('PROTECTED_ENTRIES_DESCRIPTION', 'Protect selected entries for deletion');
	define('PROTECTED_ENTRIES_DOCS', 'http://reinos.nl/add-ons/protected-entries');
	define('PROTECTED_ENTRIES_DEBUG', false);
}

//configs
$config['name'] = PROTECTED_ENTRIES_NAME;
$config['version'] = PROTECTED_ENTRIES_VERSION;

//load compat file
require_once(PATH_THIRD.PROTECTED_ENTRIES_MAP.'/compat.php');

/* End of file config.php */
/* Location: /system/expressionengine/third_party/protected_entries/config.php */