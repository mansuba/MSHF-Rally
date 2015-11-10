<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher CP Helper Class
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

class Publisher_helper_cp {

    /**
     * Show errors if they exist in the form validation/submission.
     *
     * @return  html string
     */
    public function validation_errors()
    {
        if (count(ee()->form_validation->_error_array) > 0)
        {
            ee()->form_validation->set_error_delimiters('<li>', '</li>');

            return '<ul class="errors">'. validation_errors() .'</ul>';
        }
        else
        {
            return '';
        }
    }

    public function get_language_vars($language_id = TRUE)
    {
        $vars = array(
            'language_id'       => $language_id,
            'language_edit_url' => $this->mod_link('language_manage', array('language_id' => $language_id)),
            'languages'         => ee()->publisher_language->get_all($language_id)
        );

        return $vars;
    }

    public function get_phrase_vars($group_id = TRUE)
    {
        $vars = array(
            'group_id'          => $group_id,
            'group_edit_url'    => $this->mod_link('phrase_manage_group', array('group_id' => $group_id)),
            'group_data'        => ee()->publisher_phrase->get_group($group_id),
            'phrases'           => ee()->publisher_phrase->get_all($group_id),
            'grouped'           => ee()->publisher_phrase->get_by_group(TRUE)
        );

        return $vars;
    }

    public function get_category_vars($group_id = TRUE)
    {
        $vars = array(
            'group_id'          => $group_id,
            'group_edit_url'    => $this->mod_link('edit_category_group', array('group_id' => $group_id), FALSE, 'admin_content'),
            'group_data'        => ee()->publisher_category->get_group($group_id),
            'categories'        => ee()->publisher_category->get_all($group_id),
            'grouped'           => ee()->publisher_category->get_by_group(TRUE)
        );

        return $vars;
    }

    /**
     * Create all the variables used for Language management.
     * Merge them with custom vars used in specific language methods.
     *
     * @param   array - Override defaults
     * @return  array
     */
    public function prep_language_vars($vars = array())
    {
        $language_id = ee()->input->get('language_id');

        $default_vars = array(
            'install_complete'    => FALSE,
            'is_publisher_lite'   => PUBLISHER_LITE,
            'language_manage_url' => $this->mod_link('language_manage'),
            'language_save_url'   => $this->mod_link('language_save'),
            'language_delete_url' => $this->mod_link('language_delete'),
        );

        if (isset($_SESSION['install_complete']) AND $_SESSION['install_complete'] === TRUE)
        {
            $default_vars['install_complete'] = lang('publisher_install_complete');
            $_SESSION['install_complete'] = FALSE;
        }

        // set them so they are usable in our JS too
        foreach ($default_vars as $var => $val)
        {
            $val = is_array($val) ? json_encode($val) : $val;
            ee()->javascript->set_global('publisher.'. $var, $val);
        }

        return array_merge($this->prep_global_vars(), $default_vars, $vars);
    }

    /**
     * Create all the variables used for Phrase management.
     * Merge them with custom vars used in specific phrase methods.
     *
     * @param   array - Override defaults
     * @return  array
     */
    public function prep_phrase_vars($vars = array())
    {
        $group_id = ee()->input->get('group_id') ? ee()->input->get('group_id') : ee()->publisher_phrase->get_group(TRUE, TRUE);
        $phrase_id = ee()->input->get('phrase_id');

        $default_vars = array(
            'group_new_url'     => $this->mod_link('phrase_manage_group'),
            'group_edit_url'    => $this->mod_link('phrase_manage_group'),
            'group_view_url'    => $this->mod_link('phrases'),
            'phrase_new_url'    => $this->mod_link('phrase_manage', array('group_id' => $group_id)),
            'phrase_manage_url' => $this->mod_link('phrase_manage'),
            'phrase_save_url'   => $this->mod_link('phrase_save'),
            'phrase_view_url'   => $this->mod_link('phrase_view'),
            'phrase_delete_url' => $this->mod_link('phrase_delete'),
            'phrase_delete_group_url' => $this->mod_link('phrase_delete_group'),
            'phrase_groups'     => ee()->publisher_phrase->get_groups(),
            'phrase_prefix'     => ee()->publisher_setting->phrase_prefix()
        );

        // set them so they are usable in our JS too
        foreach ($default_vars as $var => $val)
        {
            $val = is_array($val) ? json_encode($val) : $val;
            ee()->javascript->set_global('publisher.'. $var, $val);
        }

        return array_merge($this->prep_global_vars(), $default_vars, $vars);
    }

    /**
     * Create all the variables used for Category management.
     * Merge them with custom vars used in specific category methods.
     *
     * @param   array - Override defaults
     * @return  array
     */
    public function prep_category_vars($vars = array())
    {
        $group_id = ee()->input->get('group_id') ? ee()->input->get('group_id') : ee()->publisher_category->get_group(TRUE, TRUE);
        $cat_id = ee()->input->get('cat_id');

        $default_vars = array(
            'group_new_url'         => $this->mod_link('edit_category_group', array(), FALSE, 'admin_content'),
            'group_edit_url'        => $this->mod_link('edit_category_group', array('group_id' => $group_id), FALSE, 'admin_content'),
            'group_view_url'        => $this->mod_link('categories'),
            'category_new_url'      => $this->mod_link('category_edit', array('group_id' => $group_id), FALSE, 'admin_content'),
            'category_edit_url'     => $this->mod_link('category_edit', array(), FALSE, 'admin_content'),
            'category_delete_url'   => $this->mod_link('category_delete_conf', array(), FALSE, 'admin_content'),
            'category_delete_group_url'=> $this->mod_link('category_group_delete_conf', array('group_id' => $group_id), FALSE, 'admin_content'),
            'category_groups'       => ee()->publisher_category->get_groups()
        );

        // set them so they are usable in our JS too
        foreach ($default_vars as $var => $val)
        {
            $val = is_array($val) ? json_encode($val) : $val;
            ee()->javascript->set_global('publisher.'. $var, $val);
        }

        return array_merge($this->prep_global_vars(), $default_vars, $vars);
    }

    /**
     * Create the default global variables used for in any/all view files.
     * Merge them with custom vars used in specific methods.
     *
     * @param   array - Override defaults
     * @return  array
     */
    public function prep_global_vars()
    {
        $global_vars = array(
            'table_template'        => array('table_open' => '<table class="templateTable" border="0" cellpadding="0" cellspacing="0">'),
            'publisher_theme_path'  => PATH_THIRD .'publisher/',
            'publisher_theme_url'   => ee()->publisher_helper->get_theme_url() .'publisher/',
            'site_index'            => ee()->publisher_helper_url->get_site_index(),
            'language_text_direction' => ee()->publisher_model->get_language(ee()->publisher_lib->lang_id, 'direction')
        );

        // set them so they are usable in our JS too
        foreach ($global_vars as $var => $val)
        {
            $val = is_array($val) ? json_encode($val) : $val;
            ee()->javascript->set_global('publisher.'. $var, $val);
        }

        return $global_vars;
    }

    /**
     * Set the base_url's to be used in CP links and form actions
     */
    public function set_cp_base_url()
    {
        if (REQ == 'CP')
        {
            //just to prevent any errors
            if (isset(ee()->session) AND isset(ee()->session->sdata))
            {
                // EE 2.6+
                if (isset(ee()->session->sdata['fingerprint']))
                {
                    $session_id = ee()->session->sdata['fingerprint'];
                }
                // EE < 2.6
                else
                {
                    $session_id = ee()->session->sdata['session_id'];
                }
            }
            else
            {
                $session_id = 0;
            }

            $s = (ee()->config->item('admin_session_type') != 'c') ? $session_id : 0;
            $base = SELF.'?S='.$s.'&amp;D=cp';

            $this->base_url = $base.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=publisher';
            $this->form_base_url = 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=publisher';
        }
    }

    /**
     * Creates links to other methods within the module.
     *
     * @param   array - Method
     * @param   array - Additional GET parameters
     * @param   boolean - If used in form action, will generate different URL.
     * @return  array
     */
    public function mod_link($method, Array $parameters = array(), $form = FALSE, $class = FALSE)
    {
        $qry = '';

        if ( !empty($parameters))
        {
            $qry .= AMP;

            foreach ($parameters as $k => $v)
            {
                $qry .= $k .'='. $v;
            }
        }

        $base_url = $form ? $this->form_base_url : $this->base_url;

        if ($class)
        {
            $base_url = preg_replace('/C=(\S+)/', 'C='.$class, $base_url);
            return $base_url . AMP .'M='. $method . $qry;
        }

        return str_replace('&amp;', '&', $base_url . AMP .'method='. $method . $qry);
    }

    /**
     * Creates global variables in the EE.publisher object for CP specfic ajax methods
     *
     * @return  void
     */
    public function set_js()
    {
        $is_assets_installed = array_key_exists('assets', ee()->addons->get_installed()) ? 'y' : 'n';

        ee()->javascript->set_global('publisher.is_assets_installed', $is_assets_installed);

        ee()->javascript->set_global('publisher.ajax_get_category',
            $this->mod_link('ajax_get_category', array('type' => 'category')));

        ee()->javascript->set_global('publisher.ajax_save_category',
            $this->mod_link('ajax_save_category', array('type' => 'category')));

        ee()->javascript->set_global('publisher.ajax_get_phrase',
            $this->mod_link('ajax_get_phrase', array('type' => 'phrase')));

        ee()->javascript->set_global('publisher.ajax_save_phrase',
            $this->mod_link('ajax_save_phrase', array('type' => 'phrase')));

        ee()->javascript->set_global('publisher.ajax_deny_approval',
            $this->mod_link('ajax_deny_approval'));

        ee()->javascript->set_global('publisher.ajax_get_translation_status',
            $this->mod_link('ajax_get_translation_status'));

        ee()->javascript->set_global('publisher.ajax_get_entry_status',
            $this->mod_link('ajax_get_entry_status'));

        // ee()->javascript->set_global('publisher.ajax_get_approval_status',
        //     $this->mod_link('ajax_get_approval_status'));
    }

    /**
     * Bootstrap the Assets or EE File Manager
     *
     * @return  void
     */
    public function load_file_manager()
    {
        if ( array_key_exists('assets', ee()->addons->get_installed()) )
        {
            // Load Assets' assets
            if ( !isset(ee()->session->cache['assets']['included_sheet_resources']))
            {
                if (! class_exists('Assets_helper'))
                {
                    require PATH_THIRD.'assets/config.php';
                    require PATH_THIRD.'assets/helper.php';
                }

                $assets_helper = new Assets_helper;
                $assets_helper->include_sheet_resources();
            }

            $script = '$(".templateTable").delegate(".choose_file", "click", $.proxy(function(e){
                Publisher.assets_choose_file($(e.target), e);
            }, this));

            $(".templateTable").delegate(".remove_file", "click", $.proxy(function(e){
                Publisher.assets_remove_file($(e.target), e);
            }, this));';

            ee()->cp->add_to_foot('<script type="text/javascript">'. $script .'</script>');
        }
        else
        {
            ee()->load->library('filemanager');
            ee()->load->library('file_field');

            // Pass the config to the file_field, it is responsible for loading all JS assets.
            ee()->file_field->browser();

            ee()->cp->add_js_script(array(
                'file' => array('cp/publish')
            ));

            $date_fmt = (ee()->session->userdata('time_format') != '')
                        ? ee()->session->userdata('time_format')
                        : ee()->config->item('time_format');

            $this->_setup_file_list();

            ee()->javascript->set_global(array(
                'date.format'                       => $date_fmt,
                'upload_directories'                => $this->_file_manager['file_list'],

                // Set a few vars to empty, otherwise the JS throws errors. We don't need valid values here.
                'publish.markitup.fields'           => '',
                'user.can_edit_html_buttons'        => '',
                'publish.lang.edit_cateogry'        => ''
            ));
        }
    }

    /**
     * Setup File List Actions
     *
     * @return  void
     */
    private function _setup_file_list()
    {
        ee()->load->model('file_upload_preferences_model');

        $upload_directories = ee()->file_upload_preferences_model->get_file_upload_preferences(ee()->session->userdata('group_id'));

        $this->_file_manager = array(
            'file_list'                     => array(),
            'upload_directories'            => array(),
        );

        $fm_opts = array(
            'id', 'name', 'url', 'pre_format', 'post_format',
            'file_pre_format', 'file_post_format', 'properties',
            'file_properties'
        );

        foreach($upload_directories as $id => $data)
        {
            $this->_file_manager['upload_directories'][$id] = $data['name'];

            foreach($fm_opts as $prop)
            {
                $this->_file_manager['file_list'][$id][$prop] = $data[$prop];
            }
        }
    }
}