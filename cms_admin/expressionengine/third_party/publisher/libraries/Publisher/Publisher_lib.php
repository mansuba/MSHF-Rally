<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Lib Class
 *
 * @package     ExpressionEngine
 * @subpackage  Libraries
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

class Publisher_lib {

    private  $debug = FALSE;

    // Main properties used throughout the module
    public   $status;
    public   $lang_id;
    public   $default_lang_id;
    public   $lang_code;

    // Properties used in the Hi-jacking of the Api
    public   $method;          // string
    public   $parameters;      // array
    public   $field_type;      // string
    public   $field_settings;  // array
    public   $field_id;        // integer  e.g. 1
    public   $field_name;      // string   e.g. field_id_1
    public   $field_value;     // string
    public   $publisher_path;  // string
    public   $entry_id;
    public   $channel_id;

    public   $is_default_mode = FALSE;
    public   $is_default_language = FALSE;
    public   $is_diff_enabled = FALSE;

    public   $publisher_save_status = PUBLISHER_STATUS_OPEN;

    /**
     * Methods we need to hijack (aside from replace_* tags)
     *
     * @var array
     */
    private  $supported_methods = array(
        '_init',
        'pre_process',
        'pre_loop', // @todo - look into this one...
        'post_save',
        'display_publish_field'
    );

    /**
     * List of fieldtypes supported natively.
     * Must be called in this order, or any order
     * in which Matrix comes before fieldtypes that
     * can be within it.
     *
     * @var array
     */
    private $supported_fieldtypes = array(
        'Publisher_default.php',
        'Publisher_grid.php',
        'Publisher_relationship.php',
        'Publisher_matrix.php',
        'Publisher_playa.php',
        'Publisher_assets.php'
    );

    public function apply(Api_channel_fields &$api, EE_Fieldtype &$ft, $method, $parameters = array())
    {
        $is_replace_variant = (substr($method, 0, 7) == 'replace') ? TRUE : FALSE;

        // Return immediately if there are form errors or attempting to view a revision or autosaved entry
        if (
            (isset(ee()->form_validation->_error_array) && count(ee()->form_validation->_error_array) > 0) ||
            ( !ee()->input->post('publisher_save_status') && ee()->input->get('version_num')) ||
            ee()->input->get('use_autosave') == 'y'
        ){
            return $parameters;
        }

        // Do we have preprocessed data? If so return immediately.
        //  !$is_replace_variant &&
        if (isset($this->has_preprocessed_data) && $this->has_preprocessed_data === TRUE)
        {
            $this->has_preprocessed_data = FALSE;
            return $parameters;
        }

        // An ignored channel?
        if (REQ != 'PAGE' && ee()->publisher_model->is_ignored_channel(ee()->input->get_post('channel_id')))
        {
            return $parameters;
        }

        $stop = FALSE;

        // If it's not in the supported list
        if ( !in_array($method, $this->supported_methods))
        {
            $stop = TRUE;
        }

        // Chance to redeem. See if it's a replace_* tag, then stop the return
        // and set the method to replace_tag. This will force all replacements
        // through Publisher_fieldtype->replace_tag, but the modifiers will
        // still work, e.g. {field_name:text_only} as Publisher just gets the data
        // and the fieldtype still gets to process the modifier.
        if ($is_replace_variant)
        {
            $method = 'replace_tag';
            $stop = FALSE;
        }

        // Stop here and just return, we don't want to modify anything.
        if ($stop)
        {
            return $parameters;
        }

        // Get all the initial values needed for CP and template rendering.
        $this->api = $api;
        $this->method = $method;
        $this->parameters = $parameters;

        $this->field_type = strtolower(str_replace('_ft', '', get_class($ft)));
        $this->field_settings = $ft->settings;

        if ( !PUBLISHER_LITE)
        {
            // Update the fieldtype settings, set the direction. $api is passed by reference, so we can do this :)
            $api->field_types[$this->api->field_type]->settings['field_text_direction'] = ee()->publisher_model->get_language(ee()->publisher_lib->lang_id, 'direction');
        }

        // Get basic field information
        if (isset($ft->settings['field_id']))
        {
            $this->field_id = (int) $ft->settings['field_id'];
            $this->field_name = $ft->settings['field_name'];
        }
        else
        {
            $this->field_id = (int) $this->api->field_types[$this->api->field_type]->field_name;
            $this->field_name = 'field_id_'. $this->field_id;
        }

        // Something introduced in EE 2.7.0
        if ($this->field_name == 'field_id_0')
        {
            $this->field_name = 'title';
        }

        // If its an ignored field always show the default language (what is in exp_channel_data)
        if ($method == 'display_publish_field' && ee()->publisher_model->is_ignored_field($this->field_id))
        {
            return $parameters;
        }

        // Only called in the front-end templates, grab our ID here.
        if ($method == '_init' && isset($parameters[0]['row']))
        {
            $this->entry_id = $parameters[0]['row']['entry_id'];
            $this->channel_id = $parameters[0]['row']['channel_id'];

            // Then return, we don't do anything with _init(), just grabbing the IDs.
            return $parameters;
        }
        else if (ee()->input->get_post('entry_id'))
        {
            $this->entry_id = (int) ee()->input->get_post('entry_id');
            $this->channel_id = (int) ee()->input->get_post('channel_id');
        }

        // $this->publisher_save_status = ee()->input->get_post('publisher_save_status');

        if (REQ == 'CP' AND ee()->input->get_post('site_language'))
        {
            $this->entry_language = ee()->input->get_post('site_language');
        }
        elseif (REQ == 'PAGE' AND ee()->input->get_post('language'))
        {
            $this->entry_language = ee()->input->get_post('language');
        }

        // Make sure this is a custom field first or all else will fail...
        if ( !ee()->publisher_entry->is_custom_field($this->field_name))
        {
            return $parameters;
        }

        // Class is loaded up in sessions_start(), properties can't be updated
        // as it loops through the fields, so we initialize them each time and pass the vars.
        $this->field_type_obj = $this->initialize($this->field_type, array(
            'method'        => $this->method,
            'parameters'    => $this->parameters,
            'entry_id'      => $this->entry_id,
            'field_type'    => $this->field_type,
            'field_name'    => $this->field_name,
            'field_id'      => $this->field_id,
            'publisher_view_status' => $this->status,
            'publisher_lang_id' => $this->lang_id,
            'publisher_save_status' => $this->publisher_save_status
        ));

        // Make sure our column exists first and is of the correct type.
        ee()->publisher_entry->add_column($this->field_name, true);

        // Make sure it exists and its installed.
        if (is_callable(array($this->field_type_obj, $method)) &&
            $this->field_type_obj->is_installed($this->field_type)
        ){
            $this->parameters = call_user_func_array(array($this->field_type_obj, $method), $this->parameters);

            // Update our model, so on save the compiled array updates the proper tables.
            ee()->publisher_entry->data_columns[$this->field_name] = isset($this->parameters[0]) ? $this->parameters[0] : '';
        }

        // Return modified parameters, and if for some reason its a string, make sure its in an array. EE freaks out otherwise.
        return !is_array($this->parameters) ? array($this->parameters) : $this->parameters;
    }

    private function initialize($field_type, $params)
    {
        $method = 'publisher_'. $params['method'];

        if (file_exists(ee()->publisher_lib->path .'libraries/Publisher/fieldtypes/Publisher_'. $field_type .'.php'))
        {
            $class_name = 'Publisher_'. $field_type;
        }
        else
        {
            $class_name = 'Publisher_default';
        }

        $publisher_path = PATH_THIRD . 'publisher/';
        ee()->load->add_package_path($publisher_path);

        // Load base Fieldtype class to extend
        ee()->load->library('Publisher/Publisher_fieldtype');

        // Initialize the fieldtype class, and set necessary properties
        ee()->load->library('Publisher/fieldtypes/'. $class_name);
        ee()->$class_name = new $class_name();

        foreach ($params as $k => $v)
        {
            ee()->$class_name->$k = $v;

            // Make sure our models hav the same properties this is the
            // only way due to how EE loads libraries.
            ee()->publisher_model->$k = $v;
            ee()->publisher_entry->$k = $v;
        }

        ee()->load->remove_package_path($publisher_path);

        return ee()->$class_name;
    }

    /**
     * Provide a way to globally call a method from any of our custom Publisher_*
     * fieldtypes from anywhere, ideally at the end of standard EE hooks.
     *
     * @param  string $method     name of method being called
     * @param  array  $parameters
     * @return void
     */
    public function call($method, $parameters = array())
    {
        ee()->load->helper('directory');

        // Check third party fieldtype upd files, if they contain an publisher_install
        // or publisher_uninstall method call them. This will make it so it doesn't
        // matter which order add-ons are installed, Publisher first, then add-on,
        // or add-on, then Publisher
        if (in_array($method, array('install', 'uninstall')))
        {
            $addons = directory_map(PATH_THIRD);

            foreach ($addons as $name => $data)
            {
                // The fieldtype
                if (is_array($data) AND
                    array_key_exists($name, ee()->addons->get_installed()) AND
                    array_search('upd.'. $name .'.php', $data)
                ){
                    require_once PATH_THIRD. $name .'/upd.'. $name .'.php';
                    $class_name = ucwords($name).'_upd';
                    $upd = new $class_name;

                    if (is_callable(array($upd, 'publisher_'.$method)))
                    {
                        $upd->{'publisher_'.$method}();
                    }
                }
            }
        }

        $publisher_path = PATH_THIRD . 'publisher/';
        ee()->load->add_package_path($publisher_path);

        // Load base Fieldtype class to extend
        ee()->load->library('Publisher/Publisher_fieldtype');

        // Loop over the fieldtypes in their defined order and call methods if available.
        // Order of calls here is crucial, which is why the list is hardcoded above.
        foreach ($this->supported_fieldtypes as $file)
        {
            $class_name = str_replace(EXT, '', $file);

            // Initialize the fieldtype class, and set necessary properties
            ee()->load->library('Publisher/fieldtypes/'. $class_name);
            ee()->$class_name = new $class_name();

            if (is_callable(array(ee()->$class_name, $method)) AND ee()->$class_name->is_installed($class_name))
            {
                ee()->$class_name->$method($parameters);
            }
        }
    }

    /**
     * See if Publisher is currently installing
     *
     * @return boolean [description]
     */
    public function is_installing()
    {
        if ( !isset($_SESSION['installing_publisher']))
        {
            return FALSE;
        }

        if (isset($_SESSION['installing_publisher']) && $_SESSION['installing_publisher'] == TRUE)
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Set installing_publisher
     */
    public function set_installing()
    {
        $_SESSION['installing_publisher'] = TRUE;
    }
}