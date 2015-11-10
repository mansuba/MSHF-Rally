<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Helper Class
 *
 * @package     ExpressionEngine
 * @subpackage  Models
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

class Publisher_helper
{
    public function __construct()
    {
        // Create cache
        if (! isset(ee()->session->cache['publisher']))
        {
            ee()->session->cache['publisher'] = array();
        }
        $this->cache =& ee()->session->cache['publisher'];
    }

    /**
     * See if Cookie Consent module is installed and if cookies are not allowed.
     *
     * @return boolean
     */
    public function cookies_allowed()
    {
        if (array_key_exists('cookie_consent', ee()->addons->get_installed()) && !ee()->input->cookie('cookies_allowed'))
        {
            return FALSE;
        }

        return TRUE;
    }

    /*
        Convert fr_CA to fr-ca
    */
    public function lang_to_segment($lang_code)
    {
        return str_replace("_", ee()->config->item('word_separator'), strtolower($lang_code));
    }

    /*
        Convert fr-ca to fr_CA
    */
    public function segment_to_lang($segment, $uppercase = TRUE)
    {
        $segment = preg_replace("/[^a-zA-Z0-9]/", ee()->config->item('word_separator'), strtolower($segment));
        $parts = explode(ee()->config->item('word_separator'), $segment);
        $lang_code = count($parts) == 2 ? $parts[0] .'_'. ($uppercase ? strtoupper($parts[1]) : $parts[1]) : $segment;

        return $lang_code;
    }

    public function get_theme_url($get_third = TRUE)
    {
        if ($get_third)
        {
            return URL_THIRD_THEMES;
        }
        else
        {
            return ee()->config->slash_item('theme_folder_url');
        }
    }

    public function get_flag_url($lang_code)
    {
         return $this->get_theme_url() .'publisher/images/flags/'. substr($lang_code, 0, 2) .'.png';
    }

    public function get_flag($lang_code, $lang_name = FALSE)
    {
        if (ee()->publisher_setting->hide_flags())
        {
            return '';
        }
        else
        {
            return '<img src="'. $this->get_flag_url($lang_code) .'" alt="'. ($lang_name ? $lang_name : $lang_code) .'" />';
        }
    }

    public function parse_text($template, $data)
    {
        if ( !$template)
        {
            return $template;
        }
        else
        {
            if ($data)
            {
                foreach ($data as $k => $v)
                {
                    $template = str_replace(LD. $k .RD, $v, $template);
                }
            }
        }

        return $template;
    }

    /*
        Create a slug/short name out of any string.
    */
    public function create_short_name($str, $use_separator = FALSE)
    {
        $separator = ee()->config->item('word_separator') == 'dash' ? '-' : '_';

        $replace = $use_separator ? $separator : '_';

        return preg_replace('/[^a-zA-Z0-9]/', $replace, strtolower($str));
    }

    public function get_save_options()
    {
        ee()->lang->loadfile('publisher');

        return array(
            'draft' => lang('publisher_draft'),
            PUBLISHER_STATUS_OPEN  => lang('publisher_'.PUBLISHER_STATUS_OPEN)
        );
    }

    public function get_button_options($open_label = 'publisher_publish')
    {
        ee()->lang->loadfile('publisher');

        return array(
            'draft' => lang('publisher_save_as_draft'),
            PUBLISHER_STATUS_OPEN  => lang($open_label)
        );
    }

    public function get_view_options()
    {
        ee()->lang->loadfile('publisher');

        return array(
            'draft' => lang('publisher_draft'),
            PUBLISHER_STATUS_OPEN  => lang('publisher_'.PUBLISHER_STATUS_OPEN)
        );
    }

    public function get_toolbar_options($type = 'entry', Array $data = array(), $include_languages = TRUE)
    {
        if (isset($this->cache['toolbar_options']))
        {
            return $this->cache['toolbar_options'];
        }
        else
        {
            if ($type === FALSE && ee()->input->get('type'))
            {
                $type = ee()->input->get('type');
            }

            $toolbar_extra = '';

            $save_options   = $this->get_save_options();
            $view_options   = $this->get_view_options();

            $sync_drafts            = ee()->publisher_setting->sync_drafts();
            $disable_drafts         = ee()->publisher_setting->disable_drafts();
            $publisher_save_status  = ee()->publisher_setting->default_save_status();

            $default_language       = ee()->publisher_lib->default_lang_id;
            $publisher_view_status  = ee()->publisher_lib->status;
            $selected_language      = ee()->publisher_lib->lang_id;

            $publisher_save_status_enabled = TRUE;
            $has_approval = FALSE;
            $type_id = FALSE;

            // -------------------------------------------
            //  'publisher_toolbar_status' hook
            //      - Let users add to or rename the status labels at will.
            //
                if (ee()->extensions->active_hook('publisher_toolbar_status'))
                {
                    $save_options = ee()->extensions->call('publisher_toolbar_status', $save_options);
                }
            //
            // -------------------------------------------

            $requires_approval = FALSE;

            if (
                $type == 'entry' && is_array(ee()->publisher_setting->channel_approvals()) &&
                (
                    in_array( ee()->input->get('channel_id'), ee()->publisher_setting->channel_approvals() ) OR
                    count( ee()->publisher_setting->channel_approvals() ) == 0
                )
            ){
                $requires_approval = TRUE;
            }

            if ($type == 'phrase' && ee()->publisher_setting->phrase_approval())
            {
                $requires_approval = TRUE;
            }

            if ($type == 'category' && ee()->publisher_setting->category_approval())
            {
                $requires_approval = TRUE;
            }

            // Does the current require approval to publish something?
            if (ee()->publisher_role->current != ROLE_PUBLISHER && $requires_approval)
            {
                $publisher_save_status = PUBLISHER_STATUS_DRAFT;
                $publisher_save_status_enabled = FALSE;

                $toolbar_extra = '
                <div class="flag-selector">
                    <label><input type="checkbox" name="publisher_flag" value="y" /> '. ee()->publisher_setting->get('approval[label]') .'</label>
                </div>';
            }

            // -------------------------------------------
            //  'publisher_toolbar_extra' hook
            //      - Let users add additional content after Publisher's stuff, but within the Publisher toolbar.
            //
                if (ee()->extensions->active_hook('publisher_toolbar_extra'))
                {
                    $toolbar_extra_data = ee()->extensions->call('publisher_toolbar_extra');

                    if (is_array($toolbar_extra_data) && !empty($toolbar_extra_data))
                    {
                        $toolbar_extra = $toolbar_extra_data[0];

                        if (isset($toolbar_extra_data[1]))
                        {
                            $publisher_save_status = $toolbar_extra_data[1];
                        }

                        if (isset($toolbar_extra_data[2]))
                        {
                            $publisher_save_status_enabled = $toolbar_extra_data[2];
                        }
                    }
                    else
                    {
                        $toolbar_extra = $toolbar_extra_data;
                    }
                }
            //
            // -------------------------------------------

            // Make sure we have at least 1 draft and 1 open status, otherwise
            // show an error. Publisher requires both of these.
            if ( !array_key_exists(PUBLISHER_STATUS_DRAFT, $save_options))
            {
                show_error('A "draft" status does not exist in Publisher.');
            }

            if ( !array_key_exists(PUBLISHER_STATUS_OPEN, $save_options))
            {
                show_error('An "open" status does not exist in Publisher. ');
            }

            // Now make sure all keys are either open or draft, we only
            // allow devs to re-name or add the label, not the key value.
            foreach ($save_options as $key => $save_option)
            {
                if ( !in_array($key, array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT)))
                {
                    show_error('A invalid save_options key exists.');
                }
            }

            // If laguages are requested, but we only have 1 language, don't include them.
            if (
                ($include_languages == TRUE && !ee()->publisher_model->languages_active)
                /* || ee()->publisher_setting->force_default_language_cp() */
            ){
                $include_languages = FALSE;
            }

            // Can the current user even change languages? If not, hide the menu
            if ( !in_array(ee()->session->userdata['group_id'], ee()->publisher_setting->can_change_language()) &&
                 ee()->session->userdata['group_id'] != 1
            ){
                $include_languages = FALSE;
            }

            if ($type !== FALSE)
            {
                switch ($type) {
                    case 'phrase': $type_id = ee()->input->get('phrase_id'); break;
                    case 'category': $type_id = ee()->input->get('cat_id'); break;
                    case 'entry': $type_id = ee()->input->get('entry_id'); break;
                }

                ee()->load->model('publisher_approval_'.$type);

                if (ee()->{'publisher_approval_'.$type}->exists($type_id))
                {
                    $has_approval = TRUE;
                }
            }

            // Since we are editing the reference below, don't change the property
            // just need a altered listing for the drop down menu.
            $language_options = ee()->publisher_model->languages;

            // Add an identifier to the language name so we know the entry is translated to it.
            if ($type == 'entry')
            {
                foreach ($language_options as $lang_id => &$language)
                {
                    if (ee()->publisher_entry->has_translation($type_id, $lang_id))
                    {
                        $language['long_name'] = '✔ '. $language['long_name'];
                    }
                }
            }

            // -------------------------------------------
            //  'publisher_toolbar_languages' hook
            //      - Let users modify the list of languages presented to the editor
            //
                if (ee()->extensions->active_hook('publisher_toolbar_languages'))
                {
                    $language_options = ee()->extensions->call('publisher_toolbar_languages', $language_options);
                }
            //
            // -------------------------------------------

            $vars = array(
                'type'                  => $type,
                'type_id'               => $type_id,
                'role'                  => ee()->publisher_role->current,
                'languages'             => $language_options,
                'selected_language'     => $selected_language,
                'default_language'      => $default_language,
                'publisher_view_status' => $publisher_view_status,
                'publisher_save_status' => $publisher_save_status,
                'save_status_enabled'   => $publisher_save_status_enabled,
                'lang_short_name'       => ee()->publisher_model->get_language(ee()->publisher_lib->lang_id, 'short_name'),
                'save_options'          => $save_options,
                'view_options'          => $view_options,
                'toolbar_extra'         => $toolbar_extra,
                'include_languages'     => $include_languages,
                'has_approval'          => $has_approval,
                'sync_drafts'           => $sync_drafts,
                'disable_drafts'        => $disable_drafts,
                'PUBLISHER_STATUS_DRAFT'=> PUBLISHER_STATUS_DRAFT,
                'PUBLISHER_STATUS_OPEN' => PUBLISHER_STATUS_OPEN,
                'deny_approval_url'     => ee()->publisher_helper_url->get_action('deny_approval'),
                'return_url'            => ee()->publisher_helper_url->get_cp_url(array(
                                                'C'             => 'content_edit',
                                                'channel_id'    => ee()->input->get_post('channel_id')
                                            )),

                'selected_language_name'=> ee()->publisher_model->get_language(ee()->publisher_lib->lang_id, 'long_name'),
                'default_language_name' => ee()->publisher_model->get_language(ee()->publisher_lib->default_lang_id, 'long_name'),
                'upload_prefs'          => json_encode($this->get_upload_prefs())
            );

            $submit_label = 'publisher_publish';

            // Phrases and cats should always be true as they don't have the same
            // fallback message in the toolbar like entries do.
            $vars['has_default'] = TRUE;

            if ($type == 'entry')
            {
                $vars['has_default'] = ee()->publisher_entry->has_open($type_id, ee()->publisher_lib->default_lang_id);
            }

            if ($type)
            {
                $has_draft = ee()->{'publisher_'.$type}->has_draft($type_id);
                $has_translation = ee()->{'publisher_'.$type}->has_translation($type_id);

                $vars['has_draft'] = $has_draft;
                $vars['has_translation'] = $has_translation;

                $vars['action_approve_and_publish'] = FALSE;
                $vars['action_deny_approval'] = FALSE;
                $vars['action_delete_draft'] = FALSE;
                $vars['action_delete_translation'] = FALSE;

                // Set some vars that are used in the view to show/hide buttons accordingly
                if ( !$disable_drafts && !$sync_drafts && $has_draft && $has_approval && ee()->publisher_role->current == ROLE_PUBLISHER)
                {
                    $submit_label = 'publisher_approve_and_publish';
                    $vars['action_approve_and_publish'] = TRUE;
                }

                if ($sync_drafts && $has_approval && $has_draft && ee()->publisher_role->current == ROLE_PUBLISHER)
                {
                    $submit_label = 'publisher_approve_and_publish';
                }

                if ( !$disable_drafts && $has_draft && $has_approval && ee()->publisher_role->current == ROLE_PUBLISHER )
                {
                    $vars['action_deny_approval'] = TRUE;
                }

                if ($type == 'entry' && !$disable_drafts && $has_draft && ee()->publisher_lib->status == PUBLISHER_STATUS_DRAFT)
                {
                    $vars['action_delete_draft'] = TRUE;
                }

                if ($type == 'entry' && $selected_language != $default_language && $has_translation && ee()->publisher_role->current == ROLE_PUBLISHER)
                {
                    $vars['action_delete_translation'] = TRUE;
                }

                $vars['button_options'] = $this->get_button_options($submit_label);

                if (PUBLISHER_DEBUG)
                {
                    ee()->publisher_log->to_file($vars);
                }

                // Set everything as a JS object
                $this->objectify($vars);

                if ($has_approval)
                {
                    // Output JS, and remove extra white space and line breaks
                    ee()->javascript->output('$(function(){ Publisher.has_approval("'. $type .'") });');
                    ee()->javascript->compile();

                    ee()->cp->add_to_foot(ee()->load->view('dialog/has_approval', $vars, TRUE));

                    if (ee()->publisher_role->current == ROLE_PUBLISHER)
                    {
                        $vars['approval'] = ee()->{'publisher_approval_'.$type}->get(1, $type_id);
                        ee()->cp->add_to_foot(ee()->load->view('dialog/deny_approval', $vars, TRUE));
                    }
                }
            }
            // For phrases and categories
            else
            {
                $vars['button_options'] = $this->get_button_options($submit_label);

                // Set everything as a JS object
                $this->objectify($vars);
            }

            $this->cache['toolbar_options'] = $vars;

            return $this->cache['toolbar_options'];
        }
    }

    /*
        Take an array of data and transform it into a usable JS object in the EE.publisher namespace
    */
    public function objectify($array, $obj_key = 'publisher.')
    {
        foreach ($array as $key => $value)
        {
            if (is_array($value))
            {
                $this->objectify($value, $obj_key . $key .'.');
            }
            else
            {
                ee()->javascript->set_global($obj_key . $key, $value);
            }
        }
    }

   /**
    * Used for sending any Ajax responses and killing the profiler so it does not mess up the response.
    * @param  string $data Text to send back in the response
    * @return void
    */
    public function send_ajax_response($data)
    {
        ee()->output->enable_profiler(FALSE);
        @header('Content-Type: text/html; charset=UTF-8');
        exit($data);
    }

    /*
        Usage

        $mystring = $this->parse_file_path('{filedir_3}path/to/image.jpg');
        echo $mystring;

            returns: 'http://mysite.com/images/path/to/image.jpg';

        $mystring = $this->parse_file_path('{filedir_3}path/to/image.jpg', 'server_path');
        echo $mystring;

            returns: '/var/www/mysite.com/images/path/to/image.jpg';

        $mystring = $this->parse_file_path('http://mysite.com/images/path/to/image.jpg');
        echo $mystring;

            returns: '{filedir_3}path/to/image.jpg';

        $mystring = $this->parse_file_path('/var/www/mysite.com/images/path/to/image.jpg');
        echo $mystring;

            returns: '{filedir_3}path/to/image.jpg';
    */
    public function get_upload_prefs($group_id = NULL, $id = NULL)
    {
        if ( !isset(ee()->session->cache[__CLASS__]['upload_prefs']))
        {
            if (version_compare(APP_VER, '2.4', '>='))
            {
                ee()->load->model('file_upload_preferences_model');
                return ee()->file_upload_preferences_model->get_file_upload_preferences($group_id, $id);
            }

            if (version_compare(APP_VER, '2.1.5', '>='))
            {
                ee()->load->model('file_upload_preferences_model');
                $result = ee()->file_upload_preferences_model->get_upload_preferences($group_id, $id);
            }
            else
            {
                ee()->load->model('tools_model');
                $result = ee()->tools_model->get_upload_preferences($group_id, $id);
            }

            ee()->session->cache[__CLASS__]['upload_prefs'] = $result->result_array();
        }

        return ee()->session->cache[__CLASS__]['upload_prefs'];
    }

    public function parse_file_path($str, $which = 'url', $return_thumb = FALSE, $return_filename = FALSE)
    {
        if ( !$str) return;

        $prefs = $this->get_upload_prefs();
        $upload_paths = array();

        if ( !isset(ee()->session->cache[__CLASS__]['upload_paths']))
        {
            foreach ($prefs as $dir => $data)
            {
                $upload_paths[$dir] = array(
                    'server_path'   => $data['server_path'],
                    'url'           => $data['url']
                );
            }

            ee()->session->cache[__CLASS__]['upload_paths'] = $upload_paths;
        }

        $paths = ee()->session->cache[__CLASS__]['upload_paths'];

        //  Simple search for {filedir_N} tokens and replace with full url (default) or server_path
        if (preg_match('/\{filedir_(\d+)\}/', $str, $matches))
        {
            if ($matches && is_numeric($matches[1]))
            {
                $parts = explode('/', $str);
                $filename = array_pop($parts);
                $filename = preg_replace('/\{filedir_(\d+)\}/', '', $str);
                $url = preg_replace('/\{filedir_(\d+)\}/', $paths[$matches[1]][$which], $str);

                // Get the thumbnail version if it was requested.
                if ($return_thumb)
                {
                    $url = str_replace($filename, '_thumbs/'. $filename, $url);
                }

                // Return both url and filename separate.
                if ($return_filename)
                {
                    return array($url, $filename);
                }

                return $url;
            }
        }

        // No tokens found? Then search for full url or server_path and swap back to a token

        // See if its a server_path
        if (substr($str, 1) == '/')
        {
            foreach ($paths as $dir => $path)
            {
                // If the string contains a server path
                if (strstr($str, $path['server_path']))
                {
                    // Replace the path with the token.
                    return str_replace($path['server_path'], '{filedir_'. $dir .'}', $str);
                }
            }
        }

        // We have a possible url
        if (substr($str, 4) == 'http')
        {
            foreach ($paths as $dir => $path)
            {
                // If the string contains a server path
                if (strstr($str, $path['url']))
                {
                    // Replace the path with the token.
                    return str_replace($path['url'], '{filedir_'. $dir .'}', $str);
                }
            }
        }

        return $str;
    }

    /**
     * Load Pixel & Tonic's Pill field type assets
     * @return void
     */
    public function load_pill_assets()
    {
        if ( !array_key_exists('fieldpack_pill', ee()->addons->get_installed('fieldtypes')))
        {
            return FALSE;
        }

        if ( !isset(ee()->session->cache['fieldpack_pill']))
        {
            ee()->session->cache['fieldpack_pill'] = array('includes' => array());
        }

        if (! in_array('scripts/pt_pill.js', ee()->session->cache['fieldpack_pill']['includes']))
        {
            ee()->session->cache['fieldpack_pill']['includes'][] = 'scripts/pill.js';
            ee()->session->cache['fieldpack_pill']['includes'][] = 'styles/pill.css';

            ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="'. ee()->publisher_helper->get_theme_url() .'fieldpack/styles/pill.css" />');
            ee()->cp->add_to_foot('<script type="text/javascript" src="'. ee()->publisher_helper->get_theme_url() .'fieldpack/scripts/pill.js"></script>');
        }

        $script = '
            $(function(){
                Publisher.pill_is_installed = true;
                Publisher.load_pill_assets();
            });
        ';

        ee()->cp->add_to_foot('<script type="text/javascript">$(function(){'. preg_replace("/\s+/", " ", $script) .'});</script>');
    }

    /**
     * Get all info, or specific column from a channel
     * @param  integer $channel_id
     * @param  boolean $column     Column name/attribute to return
     * @return string or object
     */
    public function get_channel_data($channel_id, $column = FALSE)
    {
        if ( !$channel_id)
        {
            show_error('$channel_id is required. publisher_helper_cp.php->get_channel_title()');
        }

        $qry = ee()->db->get_where('channels', array('channel_id' => $channel_id));

        // Seriously, this should never happen
        if ( !$qry->num_rows())
        {
            return '[Unknown Channel]';
        }
        else
        {
            if ($column)
            {
                return $qry->row()->$column;
            }
            else
            {
                return $qry->result();
            }
        }
    }

    /**
     * Get all member data
     * @param  integer $member_id
     * @return object
     */
    public function get_member_data($member_id)
    {
        $member_data = array();

        // Get all CUSTOM member fields
        if ( !isset($this->cache['member_custom_fields']))
        {
            $prefix = ee()->db->dbprefix;

            $this->cache['member_custom_fields'] = ee()->db->query("SELECT m_field_id AS field_id,
                                m_field_name AS field_name,
                                m_field_label AS field_label
                                FROM {$prefix}member_fields");
        }

        $fields = $this->cache['member_custom_fields'];
        $field_ids = array();

        foreach($fields->result_array() as $row)
        {
            // Create our select of which fields to get
            $field_ids[] = 'md.m_field_id_'. $row['field_id'] .' AS "'. $row['field_name'].'"';
        }

        // Normal case, but not if used in Safecracker/Zoo Visitor registration
        // and user requires admin verification, they don't have a member_id,
        // so look up the user via email.
        if (is_numeric($member_id))
        {
            $where = array('m.member_id' => $member_id);
        }
        // If a non-integer value is sent, assume its an email address
        else
        {
            $where = array('m.email' => $member_id);
        }

        $qry = ee()->db->select(implode(',', $field_ids) .', m.*')
                ->from('members m')
                ->join('member_data md', 'md.member_id = m.member_id')
                ->where($where)
                ->get();

        if ($qry->num_rows() === 0)
        {
            return FALSE;
        }

        foreach($qry->row() as $key => $value)
        {
            $value = is_array($value) ? implode(', ', $value) : $value;

            $member_data[$key] = $value;
        }

        return (object) $member_data;
    }

    /**
     * Create a global variable to use in templates for text directions
     * @return  void
     */
    public function set_text_direction()
    {
        $direction = ee()->publisher_model->get_language(ee()->publisher_lib->lang_id, 'direction');

        ee()->config->_global_vars['global:text_direction'] = $direction;
    }

    /**
     * Simple method for getting the contents of a directory
     * @param  string $dir Path to directory
     * @return array
     */
    public function get_directory_contents($dir = '')
    {
        if ( !$dir)
        {
            return FALSE;
        }

        ee()->load->helper('directory');
        return directory_map($dir);
    }

    /**
     * Prefix a string with a defined character. Return string if it already has prefix.
     *
     * @param string $str
     * @param string $prefix
     */
    public function add_prefix($str, $prefix = '/')
    {
        if ($str != '' && $str[0] !== $prefix)
        {
            return $prefix.$str;
        }

        return $str;
    }

    /**
     * Suffix a string with a defined character. Return string if it already has suffix.
     *
     * @param string $str
     * @param string $prefix
     */
    public function add_suffix($str, $suffix = '/')
    {
        if ($str != '' && substr($str, -1, 1) !== $suffix)
        {
            return $str.$suffix;
        }

        return $str;
    }

    /**
     * Allowed Group
     *
     * Member access validation
     *
     * @param   mixed $permisisons Boolean or Array
     * @return  bool
     */
    public function allowed_group($permissions = FALSE)
    {
        if ( !$permissions || (is_array($permissions) && empty($permissions)))
        {
            return FALSE;
        }

        // Super Admins always have access
        if (ee()->session->userdata('group_id') == 1)
        {
            return TRUE;
        }

        if ( !is_array($permissions))
        {
            $permissions = array($permissions);
        }

        foreach ($permissions as $perm)
        {
            $k = ee()->session->userdata($perm);

            if ( !$k || $k !== 'y')
            {
                return FALSE;
            }
        }

        return TRUE;
    }

    public function sort_array_by_array($to_sort, $sort_by)
    {
        $ordered = array();

        foreach($sort_by as $key)
        {
            if(array_key_exists($key, $to_sort))
            {
                $ordered[$key] = $to_sort[$key];
                unset($to_sort[$key]);
            }
        }

        return $ordered + $to_sort;
    }

    /**
     * Check to see if TMPL tag param is a boolean value or not.
     *
     * @param string $param     Param name to check
     * @param boolean $check_for Are we checking for truthy or falsy values?
     *
     * @return boolean
     */
    public function is_boolean_param($param, $check_for = NULL)
    {
        if ( !ee()->TMPL->fetch_param($param))
        {
            return FALSE;
        }

        $true = array('y', 'yes');
        $false = array('n', 'no');

        if ($check_for === TRUE)
        {
            $values = $true;
        }
        else if ($check_for === FALSE)
        {
            $values = $false;
        }
        else
        {
            $values = array_merge($true, $false);
        }

        if (in_array(ee()->TMPL->tagparams[$param], $values))
        {
            return TRUE;
        }

        return FALSE;
    }
}