<?php

$config['name'] = 'Publisher';
$config['version'] = '1.4.0';
$config['description'] = 'Content workflow and translations. <a href="http://boldminded.com/add-ons/publisher">Documentation</a>';
$config['docs_url'] = 'http://boldminded.com/add-ons/publisher';
$config['nsm_addon_updater']['versions_xml'] = 'http://boldminded.com/versions/publisher';

if (! defined('PUBLISHER_VERSION'))
{
    define('PUBLISHER_VERSION', $config['version']);
    define('PUBLISHER_NAME', $config['name']);
    define('PUBLISHER_EXT', $config['name'].'_ext');
    define('PUBLISHER_DESC', $config['description']);
    define('PUBLISHER_DOCS', $config['docs_url']);

    define('ROLE_PUBLISHER', 'publisher');
    define('ROLE_EDITOR', 'editor');

    define('ROLE_PUBLISHER_LABEL', 'Publisher');
    define('ROLE_EDITOR_LABEL', 'Editor');

    define('PUBLISHER_STATUS_DRAFT', 'draft');
    define('PUBLISHER_STATUS_OPEN', 'open');
    define('STATUS_PENDING', 'pending');

    if (isset($_GET['publisher_debug']) AND $_GET['publisher_debug'] == 'y')
    {
        define('PUBLISHER_DEBUG', TRUE);
    }
    else
    {
        // Set this to TRUE to always enable debugging.
        define('PUBLISHER_DEBUG', FALSE);
    }

    define('PUBLISHER_DEBUG_ROLE', FALSE);
}

$default_settings = array(
    'enabled' => 'yes',
    'mode' => 'production',
    'sync_drafts' => 'yes',
    'force_default_language' => 'no',
    'force_default_language_cp' => 'no',
    'force_prefix' => 'no',
    'hide_prefix_on_default_language' => 'no',
    'url_translations' => 'no',
    'get_language_from_browser' => 'yes',
    'draft_previews' => 'yes',
    'channel_approvals' => array(),
    'phrase_approval' => 'yes',
    'category_approval' => 'yes',
    'ignored_channels' => array(),
    'ignored_fields' => array(),
    'ignored_field_types' => array(),

    'disable_drafts' => 'no',
    'draft_status_color' => '#ff9228',
    'default_view_status' => PUBLISHER_STATUS_OPEN,
    'default_save_status' => 'draft',

    'detailed_translation_status' => 'yes',

    'diff_driver' => 'publisher.diff',
    'diff_style' => 'full', // full, text
    'diff_enabled' => 'no',
    'diff_enabled_cp' => 'no',

    'delete_publisher_data' => 'yes',
    'url_prefix' => 'no', // Set to no on installation, must be turned on if other languages are added.
    'force_translation' => 'no',
    'geocode_lang_long_name' => 'no',
    'phrase_prefix' => 'phrase:',
    'current_phrases_variable_name' => 'current_phrases', // used as global var and set to json obj

    'persistent_relationships' => 'yes',
    'persistent_matrix' => 'yes',

    // experimental - Does not work when require_entry="yes"
    'persistent_entries' => 'yes',
    'persistent_entries_show_404' => 'yes',

    'approval' => array(
        'label' => 'Send for approval?', // Hidden for now
        'template' => '{member_name} ({member_email}) has submitted the entry "{title}" for Publishing on {date}.'."\n\n".'<a href="{link}">View Entry</a>',
        'subject' => '{approval_type} submitted for approval',
        'to' => '',
        'reply_to' => '',
        'reply_name' => '',
        'deny_template' => '<b>{screen_name} ({email})</b> will be notified that this draft approval has been denied. Please provide a reason and requested changes.'
    ),

    'hide_flags' => 'yes',

    'enable_roles' => 'yes',
    'roles' => array(
        'editor' => array(),
        'publisher' => array(),
    ),
    'can_change_language' => array(),
    'can_admin_publisher' => array(),
    'show_publisher_menu' => array(),

    'cookie_name' => 'site_language',
    'cookie_lifetime' => 2592000,
    'rename_open' => 'Open',
    'rename_draft' => 'Draft',
    'display_fallback' => 'yes',
    'replace_fallback' => 'yes',
    'cache_time' => 10080, // 1 week
    'cache_type' => 'File',
    'cache_enabled' => 'no',
    'redirect_type' => 301,

    // https://boldminded.com/support/ticket/838
    'host_lookup_type' => 'SERVER_NAME'
);

// EE 2.5.5 or less not officially supported anymore,
// but keeping this for backwards compatibility.
if (version_compare(APP_VER, '2.6', '<') && !function_exists('ee'))
{
    function ee()
    {
        static $EE;
        if ( !$EE) $EE = get_instance();
        return $EE;
    }
}

/*

Hidden config values to be added to your main EE config.php file (NOT this file)

    $config['publisher_lang_override'] = 'en';
    $config['publisher_default_language_id'] = 1;

To use Publisher with subdomains or different TLDs add this to your EE config.php file.

    if (isset($_SERVER['SERVER_NAME'])) {
        switch($_SERVER['SERVER_NAME']) {
            case 'de.sitename.com':
                $config['publisher_lang_override'] = 'de';
            break;
            case 'fr.sitename.com':
                $config['publisher_lang_override'] = 'fr';
            break;
            case 'nl.sitename.com':
                $config['publisher_lang_override'] = 'nl';
            break;
        }
    }

 */
