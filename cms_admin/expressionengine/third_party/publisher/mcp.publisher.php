<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Module CP Class
 *
 * @package     ExpressionEngine
 * @subpackage  Modules
 * @category    Publisher
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2012, 2013 - Brian Litzinger
 * @link        http://boldminded.com/add-ons/publisher
 * @license
 *
 * Copyright (c) 2012, 2013. BoldMinded, LLC
 * All rights reserved.
 *
 * This source is commercial software. Use of this software requires a
 * site license for each domain it is used on. Use of this software or any
 * of its source code without express written permission in the form of
 * a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 * As part of the license agreement for this software, all modifications
 * to this source must be submitted to the original author for review and
 * possible inclusion in future releases. No compensation will be provided
 * for patches, although where possible we will attribute each contribution
 * in file revision notes. Submitting such modifications constitutes
 * assignment of copyright to the original author (Brian Litzinger and
 * BoldMinded, LLC) for such modifications. If you do not wish to assign
 * copyright to the original author, your license to  use and modify this
 * source is null and void. Use of this software constitutes your agreement
 * to this clause.
 */

require PATH_THIRD.'publisher/config.php';

class Publisher_mcp {

    public $return_data;

    private $_base_url;


    /**
     * Constructor
     */
    public function __construct()
    {
        ee()->load->helper('form');
        ee()->load->library('table');

        // Incase its an Ajax request, stop here.
        if (REQ != 'CP') return;

        $right_nav = array(
            'module_home'       => ee()->publisher_helper_cp->base_url,
            'publisher_manage_phrases'    => ee()->publisher_helper_cp->mod_link('phrases'),
            'publisher_manage_categories' => ee()->publisher_helper_cp->mod_link('categories')
        );

        // Only Super Admins can edit the settings
        if (ee()->session->userdata['group_id'] == 1)
        {
            $right_nav['publisher_manage_settings'] = ee()->publisher_helper_cp->mod_link('settings');
            if (PUBLISHER_LITE === FALSE) $right_nav['publisher_manage_templates'] = ee()->publisher_helper_cp->mod_link('templates');
            $right_nav['publisher_manage_previews'] = ee()->publisher_helper_cp->mod_link('previews');
            if (PUBLISHER_LITE === FALSE) $right_nav['publisher_manage_languages'] = ee()->publisher_helper_cp->mod_link('languages');
            $right_nav['publisher_manage_support'] = ee()->publisher_helper_cp->mod_link('support');
        }

        // If the module is disabled, remove some of the nav options
        if ( !ee()->publisher_setting->enabled())
        {
            unset($right_nav['publisher_manage_templates']);
            unset($right_nav['publisher_manage_languages']);
            unset($right_nav['publisher_manage_phrases']);
            unset($right_nav['publisher_manage_categories']);
        }

        ee()->cp->set_right_nav($right_nav);
        ee()->view->cp_page_title = ee()->lang->line('publisher_module_name') .' &raquo; '. ee()->lang->line('publisher_manage_'. ee()->input->get('method'));

        // Do the same for the toolbar options. This is required here, otherwise the EE.publisher object is not created in time.
        ee()->publisher_helper->get_toolbar_options(FALSE, array(), FALSE);

        ee()->cp->add_to_head('
            <link href="'. ee()->publisher_helper->get_theme_url() .'publisher/scripts/select2/select2.css" rel="stylesheet" />
            <link href="'. ee()->publisher_helper->get_theme_url() .'publisher/styles/module.css" rel="stylesheet" />
            <link href="'. ee()->publisher_helper->get_theme_url() .'publisher/styles/toolbar.css" rel="stylesheet" />
        ');

        ee()->cp->add_to_foot('
            <script type="text/javascript" src="'. ee()->publisher_helper->get_theme_url() .'publisher/scripts/purl.js"></script>
            <script type="text/javascript" src="'. ee()->publisher_helper->get_theme_url() .'publisher/scripts/select2/select2.min.js"></script>
            <script type="text/javascript" src="'. ee()->publisher_helper->get_theme_url() .'publisher/scripts/jquery.autosize-min.js"></script>
            <script type="text/javascript" src="'. ee()->publisher_helper->get_theme_url() .'publisher/scripts/publisher.js"></script>
            <div id="publisher-category-dialog"><div id="publisher-category-dialog-contents"></div></div>
        ');

        // If P&T Pill is installed its JS and CSS will be loaded.
        ee()->publisher_helper->load_pill_assets();

        if (ee()->session->flashdata('message_success'))
        {
            $this->destroy_notice();
        }
    }

    // ----------------------------------------------------------------

    /**
     * MCP home page
     * @return  void
     */
    public function index()
    {
        ee()->view->cp_page_title = lang('publisher_publisher_module_name');

        $vars['heading'] = 'Pending Approvals';

        ee()->load->model('publisher_approval_entry');
        ee()->load->model('publisher_approval_phrase');
        ee()->load->model('publisher_approval_category');

        $entries = ee()->publisher_approval_entry->get();
        $phrases = ee()->publisher_approval_phrase->get();
        $categories = ee()->publisher_approval_category->get();

        $vars['total_approvals'] = ee()->publisher_approval->count();

        $vars['sections'][] = ee()->load->view('accessory-section', array(
            'rows'  => $entries,
            'type'  => 'Entries'
        ), TRUE);

        $vars['sections'][] = ee()->load->view('accessory-section', array(
            'rows'  => $phrases,
            'type'  => 'Phrases'
        ), TRUE);

        $vars['sections'][] = ee()->load->view('accessory-section', array(
            'rows'  => $categories,
            'type'  => 'Categories'
        ), TRUE);

        $vars['debug'] = '';

        return ee()->load->view('accessory', $vars, TRUE);
    }

    /**
     * Transform yes/no values into true/false
     * @param array value by reference
     * @param current key
     * @return void
     */
    private function filter_settings(&$value, $key)
    {
        if ($value === TRUE OR $value === FALSE)
        {
            $value = ($value === TRUE) ? 'true' : 'false';
        }
    }

    /**
     * Add a key/value pair to the beginning of an array
     * @param  array $arr
     * @param  string $key
     * @param  string $val
     * @return array
     */
    private function array_unshift_assoc(&$arr, $key, $val)
    {
        $arr = array_reverse($arr, true);
        $arr[$key] = $val;
        return array_reverse($arr, true);
    }

    public function support()
    {
        $vars = array(
            'hidden' => '',
            'save_url' => ee()->publisher_helper_cp->mod_link('support', array(), TRUE),
            'button_label' => lang('submit'),
            'settings' => $this->format_array(ee()->publisher_setting->get_settings()),
            'languages' => $this->format_array(ee()->publisher_model->get_languages()),
            'templates' => $this->format_array(ee()->publisher_template->get_translations()),
            'previews' => $this->format_array(ee()->publisher_template->get_all_previews()),
            'site_pages' => $this->format_array(ee()->publisher_site_pages->get_all()),
            'config' => $this->format_array(ee()->config),
            'email_address' => ee()->input->post('email_address'),
            'ticket_number' => ee()->input->post('ticket_number')
        );

        $vars = array_merge(ee()->publisher_helper_cp->prep_global_vars(), $vars);

        ee()->load->helper('form');
        ee()->load->library('form_validation');

        ee()->form_validation->set_rules('email_address', 'Email Address', 'required');
        ee()->form_validation->set_rules('ticket_number', 'Ticket Number', 'required|is_natural_no_zero');

        if (ee()->form_validation->run() == FALSE)
        {
            $vars['validation_errors'] = ee()->publisher_helper_cp->validation_errors();

            return ee()->load->view('support', $vars, TRUE);
        }
        else
        {
            $message = '<p>http://boldminded.com/support/ticket/'. ee()->input->post('ticket_number') .'</p>'.ee()->input->post('settings');

            ee()->publisher_email->send(array(
                'to' => 'support@boldminded.com',
                'subject' => 'Publisher Support Details (ticket #'. ee()->input->post('ticket_number') .')',
                'reply_to' => ee()->input->post('email_address'),
                'message' => $message,
                'template' => '{message}'
            ));

            ee()->session->set_flashdata('message_success', lang('publisher_support_sent'));
            ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->base_url);
        }
    }

    public function format_array($array)
    {
        return '<pre>'. print_r($array, TRUE) .'</pre>';
    }

    /**
     * Module settings
     * @return view
     */
    public function settings()
    {
        $this->authorize();

        // @TODO - Move all this somewhere else, its getting out of hand.

        // Ugh, turn our settings into string values so it works with the Interface Builder class
        $settings = ee()->publisher_setting->prepare(ee()->publisher_lib->site_id);
        array_walk_recursive($settings, array($this, 'filter_settings'));

        ee()->load->model('publisher_member');

        $members = ee()->publisher_member->get_members(array(1));
        $all_members = ee()->publisher_member->get_members();
        $member_groups = ee()->publisher_member->get_member_groups();

        ee()->load->model('publisher_channel');
        $channels = ee()->publisher_channel->get_all_as_options();
        $fields = ee()->publisher_model->get_fields_as_options(TRUE);

        $field_types = array();
        foreach (array_keys(ee()->addons->get_installed('fieldtypes')) as $field_type)
        {
            $field_types[$field_type] = ucwords(str_replace('_', ' ', $field_type));
        }

        $boolean = array(
            'options' => array(
                'true'  => 'Yes',
                'false' => 'No'
            )
        );

        $statuses = array(
            'options' => array(
                PUBLISHER_STATUS_OPEN  => lang('publisher_open'),
                PUBLISHER_STATUS_DRAFT => lang('publisher_draft')
            )
        );

        $modes = array(
            'options' => array(
                'production' => 'Production',
                'development' => 'Development'
            )
        );

        ee()->load->library('Publisher/Publisher_drivers');
        $diff_drivers = ee()->publisher_drivers->get_drivers('diff', TRUE);

        ee()->load->library('Channel_data/Channel_data');
        ee()->load->library('Interface_builder/Interface_builder');
        ee()->interface_builder->data = $settings;

        $fields = array(
            'settings' => array(
                'title' => '',
                'attributes'  => array(
                    'class' => 'mainTable padTable',
                    'border' => 0,
                    'cellpadding' => 0,
                    'cellspacing' => 0
                ),
                'wrapper' => 'div',
                'fields'  => array(
                    'enabled' => array(
                        'label'         => 'Enable Publisher',
                        'description'   => 'Set to <code>No</code> to disable Publisher. Some parts of Publisher will still process, but all translations, drafts, phrases, and hooks will be disabled and access to some control panel pages will be blocked. Setting this to <code>No</code> is the closest you can get to completely uninstalling Publisher without losing your data.',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),
                    'force_default_language' => array(
                        'label'         => 'Force default language',
                        'description'   => 'If your site is not ready to display translated content, set to Yes. Attempts at changing the site\'s language by altering the URL will not work. It will also ignore any language preferences in the user\'s browser.',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),
                    'force_default_language_cp' => array(
                        'label'         => 'Force default language in the Control Panel',
                        'description'   => 'By default Publisher sets a cookie each time the language is changed in the Publish page of the Control Panel. The next time the Publish page is loaded it will request data for that language. Set this to no if you want the default language content to be loaded on every Publish page request. To edit entries in other languages you will have to switch to that language for each entry.',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),
                    'get_language_from_browser' => array(
                        'label'         => 'Get language from browser',
                        'description'   => 'Determine the default language based on the user\'s browser language code?',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),



                    'url_translations' => array(
                        'label'         => 'Enable URL translations',
                        'description'   => 'All entry URL Titles will be saved as the default language. If you would like URLs to be translated, set this to yes. If you only have 1 language enabled this will effectively always be set to no.',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),
                    'url_prefix' => array(
                        'label'       => 'Add URL prefix',
                        'description' => 'Adds an additional segment to the URL for the current language. This segment is <b>not</b> included in ExpressionEngine\'s <code>{segment}</code> variables. In this example, <code>mysite.com/es/some/page</code>, <code>some</code> would be <code>{segment_1}</code>',
                        'type'        => 'select',
                        'settings'    => $boolean
                    ),
                    'force_prefix'=> array(
                        'label'         => 'Force URL prefix',
                        'description'   => 'Force the current language prefix to the requested URL. This prevents accessing a page at a non-prefixed URL.',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),
                    'hide_prefix_on_default_language'=> array(
                        'label'         => 'Hide the prefix for default language?',
                        'description'   => 'When viewing the site in the default language, and using the language prefix, you can disable the prefix only for the default language. The Force URL prefix setting above will override this option.',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),



                    'disable_drafts' => array(
                        'label'         => 'Disable Drafts',
                        'description'   => 'Disable content drafts and use Publisher as a multilingual module only?',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),
                    'draft_status_color' => array(
                        'label'       => 'Draft status color',
                        'description' => 'Set the color of the <code>Draft</code> status option.',
                        'type'        => 'input'
                    ),
                    'default_view_status' => array(
                        'label'       => 'Default view state',
                        'description' => 'Which state of the entry should editors see when first viewing an entry on the Publish page?',
                        'type'        => 'select',
                        'settings'    => $statuses
                    ),
                    'default_save_status' => array(
                        'label'       => 'Default save state',
                        'description' => 'Which state should an entry be saved as if not explicitly changed in the toolbar?',
                        'type'        => 'select',
                        'settings'    => $statuses
                    ),
                    'draft_previews' => array(
                        'label'         => 'Publish Previews',
                        'description'   => 'After saving an entry you can preview the full the entry on the front-end of your site. This may require configuring the <a href="'.ee()->publisher_helper_cp->mod_link('previews').'">preview templates</a> if you are not using the Sturcture or Pages module.',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),



                    'diff_driver' => array(
                        'label'       => 'Diff Driver',
                        'description' => 'Select which Diff driver to use. Diff is included in the Publisher package. You can also <a href="http://boldminded.com/assets/publisher/htmldiff.zip">download HTML Diff</a>, which is more robust. (HTML Diff <a href="http://boldminded.com/add-ons/publisher/diffs">installation instructions</a>)',
                        'type'        => 'select',
                        'settings'    => array(
                            'options' => $diff_drivers
                        )
                    ),
                    'diff_enabled' => array(
                        'label'       => 'Diff Enabled (front-end)',
                        'description' => 'Show content diffs on the front-end of your site when viewing a draft? Requires <code>?status=draft</code> in the URL.',
                        'type'        => 'select',
                        'settings'    => $boolean
                    ),
                    'diff_enabled_cp' => array(
                        'label'       => 'Diff Enabled (CP)',
                        'description' => 'Show content diffs next to each field on the publish page in the CP.',
                        'type'        => 'select',
                        'settings'    => $boolean
                    ),
                    'diff_style' => array(
                        'label'       => 'Diff Style',
                        'description' => 'A Full diff will display all HTML. Full diffs are not perfect due to the opening and closing of HTML tags, thus the diff may not perfectly represent the layout of your content. Text Only will strip all HTML tags, including images, which displays the differences in a more basic and straight forward manor.',
                        'type'        => 'select',
                        'settings' => array(
                            'options' => array('full' => 'Full', 'text' => 'Text Only')
                        )
                    ),



                    'sync_drafts' => array(
                        'label'         => 'Sync drafts',
                        'description'   => 'If set to yes, saving an entry as <code>Published</code> will also save the same content as <code>Draft</code>. This assumes that once content is saved as <code>Published</code> the existing <code>Draft</code> version is void, and will ensure the next person who edits the <code>Draft</code> will be editing the most up-to-date content.',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),
                    'detailed_translation_status' => array(
                        'label'         => 'Detailed Translation Statuses',
                        'description'   => 'If set to yes, the listing views of entries, phrases, and categories will have a marker next to each row indicating which languages it has been translated too. Green markers indicate complete, and grey markers indicate incomplete.
                                            Be sure the accessory is installed, and all member groups have access to it.',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),


                    'persistent_matrix' => array(
                        'label'         => 'Persistent Matrix/Grid',
                        'description'   => 'Force Matrix and Grid fields to have the same number of rows in all languages. <a href="http://boldminded.com/add-ons/publisher/persistence">Read more about persistence.</a>',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),
                    'persistent_relationships' => array(
                        'label'         => 'Persistent Relationships',
                        'description'   => 'Force Playa and Relationships fields to have the same relationship assignments in all languages. <a href="http://boldminded.com/add-ons/publisher/persistence">Read more about persistence.</a>',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),


                    'persistent_entries' => array(
                        'label'         => 'Persistent Entries',
                        'description'   => 'By default Publisher expects a translation for all languages. Disabling this setting allows you to bypass this reqiurement and create entries that may not have a translation for all languages. This allows more flexibility and uses cases such as news articles specific to each language.
                                            <b>This option is currently experiemental and support issues related to this will be lower priority.</b>',
                        'type'          => 'select',
                        'settings'      => $boolean
                    ),
                    // 'persistent_entries_show_404' => array(
                    //     'label'         => 'Persistent Entries Show 404?',
                    //     'description'   => '',
                    //     'type'          => 'select',
                    //     'settings'      => $boolean
                    // ),




                    'hide_flags' => array(
                        'label'       => 'Hide country flags',
                        'description' => 'Optionally show the small country flags next to the language dropdown menu and in the phrase and category listing. Remember, flags are not necessarily representative of a language. If the flag does not correspond to your language codes you may not be able to use this feature.',
                        'type'        => 'select',
                        'settings'    => $boolean
                    ),



                    'phrase_prefix' => array(
                        'label'       => 'Phrase prefix',
                        'description' => 'All phrases will be prefixed to avoid naming collisions. For example, <code>{phrase:welcome}</code> will display the <code>welcome</code> phrase in a template. Phrases are created as early parsed global variables, so they can be used in template conditionals.',
                        'type'        => 'input'
                    ),
                    'current_phrases_variable_name' => array(
                        'label'       => 'Phrase JSON name',
                        'description' => 'All phrases are added to a single JSON object that can be added to your template for reference in JavaScript files. For example:<p><code>&lt;script&gt;<br />var phrases = "{current_phrases}";<br />&lt;/script&gt;</code>',
                        'type'        => 'input'
                    ),



                    'channel_approvals' => array(
                        'label'       => 'Channel entry approvals',
                        'description' => 'Select which channels require Editor\'s to flag a draft entry as needing approval. Editor\'s will not be able to Publish an entry in the selected channels and must go through the approval process.',
                        'type'        => 'checkbox',
                        'settings' => array(
                            'options' => $channels
                        )
                    ),
                    'phrase_approval' => array(
                        'label'       => 'Phrase approval',
                        'description' => 'Are Editor\'s required to submit phrases drafts for approval? They will only be able to save phrases as drafts.' ,
                        'type'        => 'select',
                        'settings'    => $boolean
                    ),
                    'category_approval' => array(
                        'label'       => 'Category approval',
                        'description' => 'Are Editor\'s required to submit category drafts for approval? They will only be able to save categories as drafts.' ,
                        'type'        => 'select',
                        'settings'    => $boolean
                    ),
                    'roles[editor]' => array(
                        'label'       => 'Editors',
                        'description' => 'Editors do not have the ability to save an entry, phrase, or category as <code>Published</code>. They must save as a <code>Draft</code>, then submit it for approval by a Publisher. If no Editors are defined, all users will be considered an Editor unless they are a Super Admin.',
                        'type'        => 'checkbox',
                        'settings' => array(
                            'options' => $member_groups
                        )
                    ),
                    'roles[publisher]' => array(
                        'label'       => 'Publishers',
                        'description' => 'Publishers can save an entry, phrase, or category as <code>Published</code> at any time and responsible for approving <code>Drafts</code>',
                        'type'        => 'checkbox',
                        'settings' => array(
                            'options' => $member_groups
                        )
                    ),
                    'can_change_language' => array(
                        'label'       => 'Can change languages',
                        'description' => 'Select which member groups can change languages in the Publish page. Groups not selected will only be allowed to edit content in the default language. Super Admin users will always be able to change languages.',
                        'type'        => 'checkbox',
                        'settings' => array(
                            'options' => $member_groups
                        )
                    ),
                    'can_admin_publisher' => array(
                        'label'       => 'Can administor Publisher',
                        'description' => 'Select which member groups can add new languages, phrases, and can access all Publisher settings pages.',
                        'type'        => 'checkbox',
                        'settings' => array(
                            'options' => $member_groups
                        )
                    ),
                    'show_publisher_menu' => array(
                        'label'       => 'Show Publisher Menu',
                        'description' => 'Select which member groups can see the Publisher menu in the main menu bar.',
                        'type'        => 'checkbox',
                        'settings' => array(
                            'options' => $member_groups
                        )
                    ),


                    'approval[label]'  => array(
                        'label'       => 'Approval checkbox',
                        'description' => 'Change the text that appears next to the send for approval checkbox.',
                        'type'        => 'input'
                    ),
                    'approval[subject]'  => array(
                        'label'       => 'Approval email subject',
                        'description' => 'Change the email subject line.',
                        'type'        => 'input'
                    ),
                    'approval[template]'  => array(
                        'label'       => 'Approval email text',
                        'description' => 'Change the text used in the approval request email sent to a Publisher when an Editor requests approval.',
                        'type'        => 'textarea',
                        'settings'    => array(
                            'attributes' => array(
                                'rows="10"'
                            )
                        )
                    ),
                    'approval[deny_template]'  => array(
                        'label'       => 'Deny approval text',
                        'description' => 'Change the text that displays in the deny approval modal window when a Publisher denys an approval requested by an Editor.',
                        'type'        => 'textarea',
                        'settings'    => array(
                            'attributes' => array(
                                'rows="10"'
                            )
                        )
                    ),
                    'approval[to]'  => array(
                        'label'       => 'Approval email list',
                        'description' => 'Select which members will be notified when an approval is requested.',
                        'type'        => 'checkbox',
                        'settings'    => array(
                            'options' => $all_members
                        )
                    ),
                    'approval[reply_to]'  => array(
                        'label'       => 'Approval email reply-to',
                        'description' => 'Enter the email address you would like to use for the approval email\'s Reply-To address. If left blank, the configured webmaster email address will be used.',
                        'type'        => 'input'
                    ),
                    'approval[reply_name]'  => array(
                        'label'       => 'Approval email reply-to name',
                        'description' => 'Enter the display name that will be used for the approval email\'s Reply-To Name.',
                        'type'        => 'input'
                    ),




                    'ignored_channels' => array(
                        'label'       => 'Ignored channels',
                        'description' => 'Select which channel(s) you would like Publisher to ignore. When editing entries within that channel it will appear as if Publisher is not installed.',
                        'type'        => 'checkbox',
                        'settings' => array(
                            'options' => $channels
                        )
                    ),
                    'ignored_fields' => array(
                        'label'       => 'Ignored fields',
                        'description' => 'Select which fields you would like Publisher to ignore. This is ideal for any field that a 3rd party fieldtype might rely on having its data remain inside of the exp_channel_data table.',
                        'type'        => 'checkbox',
                        'settings' => array(
                            'options' => $fields
                        )
                    ),
                    'ignored_field_types' => array(
                        'label'       => 'Ignored fieldtypes',
                        'description' => 'Select which field types you would like Publisher to ignore. This setting will trump the Ignored Fields setting as it will ignore <b>all</b> fields of the given type across all channels.',
                        'type'        => 'checkbox',
                        'settings' => array(
                            'options' => $field_types
                        )
                    ),



                    'display_fallback' => array(
                        'label'       => 'Show content fallback (CP)',
                        'description' => 'If translated content is not present for a custom field, display the default language value instead on the publish page?' ,
                        'type'        => 'select',
                        'settings'    => $boolean
                    ),
                    'replace_fallback' => array(
                        'label'       => 'Show content fallback (FE)',
                        'description' => 'If translated content is not present for a custom field, display the default language value instead on the front-end in your templates?' ,
                        'type'        => 'select',
                        'settings'    => $boolean
                    ),



                    'cache_enabled' => array(
                        'label'       => 'Enable Cache',
                        'description' => 'Publisher can cache translated entry results, phrase, languages and other result sets to reduce queries. channel:entries tags require adding publisher_cache="yes" to the tag.' ,
                        'type'        => 'select',
                        'settings'    => $boolean
                    ),
                    'cache_time'  => array(
                        'label'       => 'Cache Lifetime',
                        'description' => 'How long should the cache live? It will be cleared when an entry is saved. Time is in seconds, default is 1 week.',
                        'type'        => 'input'
                    ),
                    'cookie_lifetime'  => array(
                        'label'       => 'Cookie Lifetime',
                        'description' => 'How long should the cookie that saves the user\'s selected language be valid? Time is in seconds, default is 30 days.',
                        'type'        => 'input'
                    ),

                    'host_lookup_type'  => array(
                        'label'       => 'Host Lookup Type',
                        'description' => 'How should Publisher determine your site\'s host name? In most cases, SERVER_NAME is preferred. If you have trouble with MSM configurations, try HTTP_HOST.',
                        'type'        => 'select',
                        'settings'    => array(
                            'options' => array(
                                'SERVER_NAME' => 'SERVER_NAME',
                                'HTTP_HOST' => 'HTTP_HOST'
                            )
                        )
                    ),

                    'redirect_type'  => array(
                        'label'       => 'Redirect Type',
                        'description' => 'When changing languages, which redirect should Publisher use?',
                        'type'        => 'select',
                        'settings'    => array(
                            'options' => array(
                                '301' => '301 (default)',
                                '302' => '302'
                            )
                        )
                    )
                )
            )
        );

        // If Publisher Lite, don't show these settings.
        $no_lite = array(
            'mode',
            'force_default_language', 'force_default_language_cp',
            'get_language_from_browser', 'url_translations',
            'url_prefix', 'force_prefix', 'hide_flags',
            'cache_enabled', 'cache_time', 'cache_type');

        if (PUBLISHER_LITE === TRUE)
        {
            foreach ($fields['settings']['fields'] as $key => $data)
            {
                if (in_array($key, $no_lite))
                {
                    unset($fields['settings']['fields'][$key]);
                }
            }
        }

        ee()->interface_builder->add_fieldsets($fields);

        ee()->cp->add_to_foot('
            <script type="text/javascript" src="'. ee()->publisher_helper->get_theme_url() .'publisher/scripts/tablednd.js"></script>
            <script type="text/javascript" src="'. ee()->publisher_helper->get_theme_url() .'publisher/scripts/InterfaceBuilder.js"></script>
            <script type="text/javascript">$(function(){ new InterfaceBuilder(); });</script>
        ');

        $vars = array(
            'hidden'    => array(),
            'save_url'  => ee()->publisher_helper_cp->mod_link('settings_save', array(), TRUE),
            'settings'  => ee()->interface_builder->fieldsets(),
            'install_pt_url' => ee()->publisher_helper_cp->mod_link('install_pt_fieldtypes'),
            'templates_url'  => ee()->publisher_helper_cp->mod_link('templates'),
        );

        return ee()->load->view('settings', $vars, TRUE);
    }

    public function settings_save()
    {
        $this->authorize();

        ee()->publisher_setting->save();

        ee()->session->set_flashdata('message_success', lang('publisher_settings_saved'));
        ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('settings'));
    }

    public function languages()
    {
        $this->authorize();

        if (PUBLISHER_LITE === TRUE)
        {
            ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('index'));
        }

        ee()->load->model('publisher_language');

        $language_id = ee()->input->get('language_id') ? ee()->input->get('language_id') : 1;

        $vars = ee()->publisher_helper_cp->get_language_vars($language_id);
        $vars = ee()->publisher_helper_cp->prep_language_vars($vars);

        return ee()->load->view('language/index', $vars, TRUE);
    }

    /**
     * See if the current user can access a Publisher settings pages
     *
     * @return mixed
     */
    private function authorize()
    {
        $authorized = FALSE;

        if ( in_array(ee()->session->userdata['group_id'], ee()->publisher_setting->can_admin_publisher()))
        {
            $authorized = TRUE;
        }

        if (ee()->session->userdata['group_id'] == 1)
        {
            $authorized = TRUE;
        }

        if ( !$authorized)
        {
            ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('view_unauthorized'));
        }
    }

    /**
     * Render the unauthorized message
     *
     * @return string
     */
    public function view_unauthorized()
    {
        return ee()->load->view('unauthorized', array(), TRUE);
    }

    public function language_manage()
    {
        $this->authorize();

        ee()->load->model('publisher_language');

        ee()->load->helper('form');
        ee()->load->library('form_validation');

        ee()->form_validation->set_rules('short_name', 'Short Name', 'required');
        ee()->form_validation->set_rules('long_name', 'Long Name', 'required');
        ee()->form_validation->set_rules('sites', 'Sites', 'required');

        $lang_id = ee()->input->get_post('language_id');

        $language_data = FALSE;

        if ($lang_id)
        {
            $language_data = ee()->publisher_language->get($lang_id);
        }

        $language_packs = ee()->publisher_helper->get_directory_contents(APPPATH.'language');

        $vars['language_packs'] = array();

        if ($language_packs)
        {
            foreach ($language_packs as $key => $child)
            {
                if (is_string($key) AND $key != 'english')
                {
                    $vars['language_packs'][$key] = ucwords($key);
                }
            }

            // Always want English first, since its the system default
            asort($vars['language_packs']);
            $vars['language_packs'] = array('english' => 'English') + $vars['language_packs'];
        }
        // If for some reason get_directory_contents() fails, default to English.
        else
        {
            $vars['language_packs'] = array('english' => 'English');
        }

        $sites = ee()->publisher_model->get_sites();
        $site_options = array();

        foreach ($sites as $site_id => $site)
        {
            $site_options[$site_id] = $site->site_label;
        }

        $vars['cat_url_indicator'] = ee()->config->item('reserved_category_word');
        $vars['sites']       = $site_options;
        $vars['button_label']= $lang_id ? lang('publisher_update') : lang('publisher_save');
        $vars['hidden']      = array('language_id' => $lang_id);
        $vars['save_url']    = ee()->publisher_helper_cp->mod_link('language_manage', array(), TRUE);

        if ($language_data)
        {
            $vars['language'] = $language_data;
        }

        if (ee()->form_validation->run() == FALSE)
        {
            $vars['validation_errors']  = ee()->publisher_helper_cp->validation_errors();

            return ee()->load->view('language/manage', $vars, TRUE);
        }
        else
        {
            ee()->publisher_cache->driver->delete('languages');
            ee()->publisher_language->save();
            ee()->session->set_flashdata('message_success', lang('publisher_languages_saved'));
            ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('languages'));
        }
    }

    /**
     * Let the user first confirm that they want to delete the language
     * @return view
     */
    public function language_delete()
    {
        $this->authorize();

        ee()->load->model('publisher_language');

        $language_id = ee()->input->get('language_id', TRUE);
        $language = ee()->publisher_language->get($language_id);

        if ($language->is_default == 'y')
        {
            show_error(sprintf( lang('publisher_cant_delete_default_language'), ee()->session->userdata['screen_name'], ee()->publisher_helper->get_theme_url().'publisher/images/HAL-9000.jpg'));
        }
        else
        {
            $vars = array(
                'long_name' => $language->long_name,
                'hidden' => array('language_id' => $language_id),
                'delete_url' => ee()->publisher_helper_cp->mod_link('language_delete_execute', array(), TRUE),
            );

            return ee()->load->view('language/delete', $vars, TRUE);
        }
    }

    /**
     * Confirmed! Blow it away.
     * @return void
     */
    public function language_delete_execute()
    {
        $this->authorize();

        ee()->load->model('publisher_language');

        ee()->publisher_language->delete(ee()->input->post('language_id', TRUE));

        ee()->session->set_flashdata('message_success', lang('publisher_language_deleted'));
        ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('languages'));
    }

    /**
     * Phrases management landing page
     * @return view
     */
    public function phrases()
    {
        $group_id = ee()->input->get('group_id') ? ee()->input->get('group_id') : ee()->publisher_phrase->get_first_group();

        $vars = ee()->publisher_helper_cp->get_phrase_vars($group_id);

        $vars = ee()->publisher_helper_cp->prep_phrase_vars($vars);

        return ee()->load->view('phrase/index', $vars, TRUE);
    }

    /**
     * Categories management landing page
     * @return view
     */
    public function categories()
    {
        $group_id = ee()->input->get('group_id') ? ee()->input->get('group_id') : ee()->publisher_category->get_first_group();

        $vars = ee()->publisher_helper_cp->get_category_vars($group_id);

        $vars = ee()->publisher_helper_cp->prep_category_vars($vars);

        // Load the file manager for the category image.
        ee()->publisher_helper_cp->load_file_manager();

        // Bail if there are no category groups defined.
        if (empty($vars['category_groups']))
        {
            show_error('No category groups found, please <a href="'. BASE.AMP .'D=cp&C=admin_content&M=edit_category_group">create one</a>.');
        }

        return ee()->load->view('category/index', $vars, TRUE);
    }

    /**
     * Create new and edit exising phrase groups.
     *
     * @return  void
     */
    public function phrase_manage_group()
    {
        $this->authorize();

        ee()->load->helper('form');
        ee()->load->library('form_validation');

        ee()->form_validation->set_rules('group_label', 'Phrase Group Label', 'required');

        $group_id   = ee()->input->get('group_id');
        $group_data = FALSE;

        if ($group_id)
        {
            $group_data = ee()->publisher_phrase->get_group($group_id);
        }

        $vars['group_label']        = set_value('group_label');
        $vars['group_name']         = set_value('group_name');
        $vars['hidden']             = array('group_id' => $group_id);
        $vars['save_url']           = ee()->publisher_helper_cp->mod_link('phrase_manage_group', array(), TRUE);

        if ($group_data)
        {
            $vars['group_label']    = $group_data->group_label;
            $vars['group_name']     = $group_data->group_name;
        }

        if (ee()->form_validation->run() == FALSE)
        {
            $vars['validation_errors']  = ee()->publisher_helper_cp->validation_errors();

            return ee()->load->view('phrase/manage_group', $vars, TRUE);
        }
        else
        {
            ee()->publisher_phrase->save_group();
            ee()->session->set_flashdata('message_success', lang('publisher_phrase_group_saved'));
            ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('phrases'));
        }
    }

    /**
     * Create new and edit existing phrase names.
     * @return  void
     */
    public function phrase_manage()
    {
        $this->authorize();

        ee()->load->helper('form');
        ee()->load->library('form_validation');

        ee()->form_validation->set_rules('phrase_name', 'Phrase Name', 'required');

        $group_id    = ee()->input->get_post('group_id');
        $phrase_id   = ee()->input->get_post('phrase_id');

        $phrase_data = FALSE;

        if ($phrase_id)
        {
            $phrase_data = ee()->publisher_phrase->get($phrase_id);
        }

        foreach (ee()->publisher_phrase->get_groups() as $id => $group)
        {
            $groups[$id] = $group->group_label;
        }

        $vars['groups']      = $groups;
        $vars['phrase_name'] = set_value('phrase_name');
        $vars['phrase_desc'] = set_value('phrase_desc');
        $vars['button_label']= $phrase_id ? lang('publisher_update') : lang('publisher_save');
        $vars['hidden']      = array('phrase_id' => $phrase_id, 'group_id' => $group_id);
        $vars['save_url']    = ee()->publisher_helper_cp->mod_link('phrase_manage', array(), TRUE);

        if ($phrase_data)
        {
            $vars['phrase_name']  = $phrase_data->phrase_name;
            $vars['phrase_desc']  = $phrase_data->phrase_desc;
            $vars['hidden']['old_phrase_name'] = $phrase_data->phrase_name;
        }

        if (ee()->form_validation->run() == FALSE)
        {
            $vars['validation_errors']  = ee()->publisher_helper_cp->validation_errors();

            return ee()->load->view('phrase/manage', $vars, TRUE);
        }
        else
        {
            ee()->publisher_phrase->save();
            ee()->session->set_flashdata('message_success', lang('publisher_phrase_saved'));
            ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('phrases', array('group_id' => $group_id)));
        }
    }

    /**
     * Let the user first confirm that they want to delete the phrase
     * @return view
     */
    public function phrase_delete()
    {
        $phrase_id = ee()->input->get('phrase_id', TRUE);

        $vars = array(
            'phrase_name' => ee()->publisher_phrase->get($phrase_id)->phrase_name,
            'hidden' => array('phrase_id' => $phrase_id),
            'delete_url' => ee()->publisher_helper_cp->mod_link('phrase_delete_execute', array(), TRUE),
        );

        return ee()->load->view('phrase/delete', $vars, TRUE);
    }

    /**
     * Confirmed! Blow it away.
     * @return void
     */
    public function phrase_delete_execute()
    {
        ee()->publisher_phrase->delete(ee()->input->post('phrase_id', TRUE));

        ee()->session->set_flashdata('message_success', lang('publisher_phrase_deleted'));
        ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('phrases'));
    }

    /**
     * Let the user first confirm they want to delete the group, and reassign phrases to a new group
     * @return view
     */
    public function phrase_delete_group()
    {
        $group_id = ee()->input->get('phrase_group_id', TRUE);
        $groups = array('' => '- Select -');

        foreach (ee()->publisher_phrase->get_groups() as $id => $group)
        {
            if ($group_id != $id)
            {
                $groups[$id] = $group->group_label;
            }
        }

        $vars = array(
            'group_name' => ee()->publisher_phrase->get_group($group_id)->group_label,
            'groups' => $groups,
            'hidden' => array('group_id' => $group_id),
            'delete_url' => ee()->publisher_helper_cp->mod_link('phrase_delete_group_execute', array(), TRUE),
        );

        return ee()->load->view('phrase/delete_group', $vars, TRUE);
    }

    /**
     * Boom!
     * @return void
     */
    public function phrase_delete_group_execute()
    {
        $this->authorize();

        ee()->publisher_phrase->delete_group(ee()->input->post('group_id', TRUE), ee()->input->post('new_group', TRUE));

        ee()->session->set_flashdata('message_success', lang('publisher_phrase_group_deleted'));
        ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('phrases'));
    }

    /**
     * List out all the templates and fields for translating them
     * @return string rendered view
     */
    public function templates()
    {
        $this->authorize();

        ee()->load->helper('form');
        ee()->load->library('form_validation');
        ee()->load->model('publisher_template');

        if (empty($_POST))
        {
            $vars = array(
                'hidden'    => array(),
                'save_url'  => ee()->publisher_helper_cp->mod_link('templates', array(), TRUE),
                'templates' => ee()->publisher_template->get_by_group()
            );

            return ee()->load->view('templates', $vars, TRUE);
        }
        else
        {
            ee()->publisher_template->save_translations();
            ee()->session->set_flashdata('message_success', lang('publisher_templates_saved'));
            ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('templates'));
        }
    }

    /**
     * List all the channels with a select menu of templates to choose
     * which template will be used as a preview template in draft previews
     * @return string rendered view
     */
    public function previews()
    {
        $this->authorize();

        ee()->load->helper('form');
        ee()->load->library('form_validation');
        ee()->load->model('publisher_channel');
        ee()->load->model('publisher_template');

        if (empty($_POST))
        {
            $vars = array(
                'hidden'    => array(),
                'save_url'  => ee()->publisher_helper_cp->mod_link('previews', array(), TRUE),
                'templates' => ee()->publisher_template->get_all(),
                'channels'  => ee()->publisher_channel->get_all(),
                'data'      => ee()->publisher_template->get_all_previews()
            );

            return ee()->load->view('previews', $vars, TRUE);
        }
        else
        {
            ee()->publisher_template->save_previews();
            ee()->session->set_flashdata('message_success', lang('publisher_previews_saved'));
            ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('previews'));
        }
    }

    /**
     * Get phrase data via ajax request in the CP
     * @return  void
     */
    public function ajax_get_phrase()
    {
        $phrase_id = ee()->input->get('phrase_id');
        $status = ee()->input->get('publisher_view_status') ? ee()->input->get('publisher_view_status') : PUBLISHER_STATUS_OPEN;

        $data = array(
            'phrase_id' => $phrase_id,
            'status'    => $status
        );

        $vars = ee()->publisher_helper->get_toolbar_options('phrase', $data, FALSE);

        $vars['phrase_id'] = $phrase_id;
        $vars['data'] = ee()->publisher_phrase->get_translations($phrase_id, $status);
        $vars['save_url'] = ee()->publisher_helper_cp->mod_link('ajax_save_phrase', array(), TRUE);

        if (ee()->input->get('publisher_view_status'))
        {
            $data = ee()->load->view('phrase/edit_form', $vars, TRUE);
        }
        else
        {
            $data = ee()->load->view('phrase/edit', $vars, TRUE);
        }

        ee()->publisher_helper->send_ajax_response($data);
    }

    /**
     * Save a phrase with all its translations
     * @return void
     */
    public function ajax_save_phrase()
    {
        $translation = ee()->input->post('translation');
        $status = ee()->input->post('publisher_save_status');

        // Stop here if false or if the array is empty
        if ( !$translation || empty($translation))
        {
            ee()->publisher_helper->send_ajax_response('failure');
        }

        $result = ee()->publisher_phrase->save_translation($translation, $status);

        if ($result)
        {
            ee()->publisher_helper->send_ajax_response('success');
        }
        else
        {
            ee()->publisher_helper->send_ajax_response($result);
        }
    }


    /**
     * Get category data via ajax request in the CP
     * @return  void
     */
    public function ajax_get_category()
    {
        $cat_id = ee()->input->get('cat_id');
        $group_id = ee()->input->get('group_id');
        $status = ee()->input->get('publisher_view_status') ? ee()->input->get('publisher_view_status') : PUBLISHER_STATUS_OPEN;

        $data = array(
            'cat_id'    => $cat_id,
            'status'    => $status
        );

        $vars = ee()->publisher_helper->get_toolbar_options('category', $data, FALSE);

        $vars['cat_id'] = $cat_id;
        $vars['group_id'] = $group_id;
        $vars['data'] = ee()->publisher_category->get_translations($cat_id, $group_id, $status);
        $vars['save_url'] = ee()->publisher_helper_cp->mod_link('ajax_save_category', array(), TRUE);
        $vars['custom_fields'] = ee()->publisher_category->get_custom_fields($group_id);

        // Load core lang file so views are translated
        ee()->lang->loadfile('content');

        if (ee()->input->get('publisher_view_status'))
        {
            $data = ee()->load->view('category/edit_form', $vars, TRUE);
        }
        else
        {
            $data = ee()->load->view('category/edit', $vars, TRUE);
        }

        ee()->publisher_helper->send_ajax_response($data);
    }

    /**
     * Save a category with all its translations
     * @return void
     */
    public function ajax_save_category()
    {
        $translation = ee()->input->post('translation');
        $status = ee()->input->post('publisher_save_status');

        // Stop here if false or if the array is empty
        if ( !$translation || empty($translation))
        {
            ee()->publisher_helper->send_ajax_response('failure');
        }

        $result = ee()->publisher_category->save_translation($translation, $status);

        if ($result)
        {
            ee()->publisher_helper->send_ajax_response('success');
        }
        else
        {
            ee()->publisher_helper->send_ajax_response($result);
        }
    }

    /**
     * Action - Submit a denial form from the CP
     * @return string
     */
    public function ajax_deny_approval()
    {
        if (empty($_POST)) return;

        ee()->load->model('publisher_approval');
        $msg = ee()->publisher_approval->deny();
        ee()->publisher_helper->send_ajax_response($msg);
    }

    /**
     * Action - Get the translation status of an entry, phrase or category via Ajax
     * @return string
     */
    public function ajax_get_translation_status()
    {
        $type = ee()->input->get('type', TRUE);
        $id   = ee()->input->get('id', TRUE);

        $status = FALSE;

        $detailed = ee()->publisher_setting->detailed_translation_status();

        switch ($type)
        {
            case 'phrase':
                $status = ee()->publisher_phrase->is_translated_formatted($id, $detailed);
            break;
            case 'category':
                $status = ee()->publisher_category->is_translated_formatted($id, $detailed);
            break;
            case 'entry':
                if (strstr($id, ','))
                {
                    $id = explode(',', $id);
                }

                if (is_array($id))
                {
                    $status = FALSE;
                    $translated_entries = array();

                    foreach ($id as $entry_id)
                    {
                        if ($status = ee()->publisher_entry->is_translated_formatted($entry_id, $detailed))
                        {
                            $translated_entries[$entry_id] = $status;
                        }
                    }

                    return ee()->publisher_helper->send_ajax_response(json_encode($translated_entries));
                }
                else
                {
                    $status = ee()->publisher_entry->is_translated_formatted($id, $detailed);
                }
            break;
        }

        ee()->publisher_helper->send_ajax_response($status);
    }

    /**
     * Action - Get the draft status of an entry, phrase or category via ajax
     * @return string
     */
    public function ajax_get_entry_status()
    {
        $type = ee()->input->get('type', TRUE);
        $id   = ee()->input->get('id', TRUE);

        $status = FALSE;

        switch ($type)
        {
            case 'phrase':
                // $status = ee()->publisher_phrase->is_translated($id);
            break;
            case 'category':
                // $status = ee()->publisher_category->is_translated($id);
            break;
            case 'entry':
                if (strstr($id, ','))
                {
                    $id = explode(',', $id);
                }

                if (is_array($id))
                {
                    $status = FALSE;
                    $draft_entries = array();

                    foreach ($id as $entry_id)
                    {
                        if (ee()->publisher_entry->has_draft($entry_id))
                        {
                            $draft_entries[$entry_id] = 'y';
                        }
                        else
                        {
                            $draft_entries[$entry_id] = 'n';
                        }
                    }

                    return ee()->publisher_helper->send_ajax_response(json_encode($draft_entries));
                }
                else
                {
                    $status = ee()->publisher_entry->has_draft($id);
                }
            break;
        }

        $return = $status ? TRUE : FALSE;

        ee()->publisher_helper->send_ajax_response($return);
    }

    /**
     * If Publisher is installed after P&T fieldtypes, they need the columns
     * @return void
     */
    public function install_pt_fieldtypes()
    {
        // Run all the time, make sure these tables are updated.
        $field_types = array('matrix', 'playa', 'assets');

        require_once PATH_THIRD .'publisher/libraries/Publisher/Publisher_fieldtype.php';

        foreach ($field_types as $field_type)
        {
            $class_name = 'Publisher_'. $field_type;

            // Initialize the fieldtype class, and set necessary properties
            require_once PATH_THIRD .'publisher/libraries/Publisher/fieldtypes/'. $class_name .'.php';
            ee()->$class_name = new $class_name();
            ee()->$class_name->install();
        }

        ee()->session->set_flashdata('message_success', lang('publisher_install_pt_success'));
        ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link('settings'));
    }

    /**
    * Show EE notification and hide it after a few seconds
    */
    private function destroy_notice()
    {
        ee()->javascript->output(array(
            'window.setTimeout(function(){$.ee_notice.destroy()}, 4000);'
        ));
    }
}
/* End of file mcp.publisher.php */
/* Location: /system/expressionengine/third_party/publisher/mcp.publisher.php */