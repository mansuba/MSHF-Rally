<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Phrase Model Class
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

class Publisher_setting
{
    public $settings = array();

    /**
     * Return a setting via method name
     *
     * @param  string $name Setting name
     * @param  array  $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        $site_id = (isset($args[0]) && $args === TRUE) ? TRUE : FALSE;

        return $this->get($name, $site_id);
    }

    public function save()
    {
        require PATH_THIRD.'publisher/config.php';

        foreach ($default_settings as $key => $value)
        {
            $data = array();
            $where = array('key' => $key);

            // If set, use the posted value, otherwise use default value.
            // This is mostly to handle the checkbox groups that don't post
            // anything if nothing is selected.
            $value = isset($_POST[$key]) ? $_POST[$key] : $value;

            if (is_array($value))
            {
                $value = json_encode($value);
                $data['type'] = 'json';
            }
            else if ($value === 'true' OR $value === 'false')
            {
                $value = $value == 'true' ? 'yes' : 'no';
                $data['type'] = 'boolean';
            }
            else
            {
                $data['type'] = 'string';
            }

            $where['site_id'] = ee()->config->item('site_id');
            $data['site_id'] = ee()->config->item('site_id');
            $data['key'] = $key;
            $data['val'] = $value;

            // Special cases
            switch ($key)
            {
                case 'enabled':
                    if ($value == 'yes')
                    {
                        $this->set_enabled();
                    }
                    elseif ($value == 'no')
                    {
                        $this->set_disabled();
                    }
                break;
            }

            if ($data['site_id'] != 0 && $key == 'enabled')
            {
                $data['site_id'] = 0;
                $where['site_id'] = 0;
            }

            $this->insert_or_update('publisher_settings', $data, $where);
        }
    }

    private function set_enabled()
    {
        ee()->db->where('class', PUBLISHER_EXT)
            ->update('extensions', array('enabled' => 'y'));
    }

    private function set_disabled()
    {
        // Keep these hooks enabled otherwise the incorrect data
        // is returned from Publisher.
        $keep = array(
            'sessions_start',
            'sessions_end',
            'matrix_data_query',
            'matrix_display_field',
            'matrix_save_row',
            'playa_fetch_rels_query',
            'playa_field_selections_query',
            'playa_save_rels',
            'assets_field_selections_query',
            'assets_data_query',
            'assets_save_row',
            'relationships_query',
            'relationships_display_field',
            'relationships_post_save'
        );

        ee()->db->where('class', PUBLISHER_EXT)
            ->where_not_in('hook', $keep)
            ->update('extensions', array('enabled' => 'n'));
    }

    /**
     * Getter for all settings
     *
     * @return array
     */
    public function get_settings()
    {
        return $this->settings;
    }

    /**
     * Get a specific setting
     *
     * @param  string  $key
     * @param  boolean $get_by_site_id Get by POST site_id?
     * @return mixed
     */
    public function get($key, $site_id = FALSE)
    {
        $site_id = $site_id ? $site_id : ee()->config->item('site_id');
        $settings = $this->settings[$site_id];

        // Looking for a nested array value
        if (strstr($key, '['))
        {
            // Make sure we only do this flatten non-sense once per page load. @todo - save it to $_SESSION??
            if ( !isset(ee()->session->cache['publisher']['settings_flat']))
            {
                ee()->session->cache['publisher']['settings_flat'] = $this->flatten($settings);
            }

            $return = isset(ee()->session->cache['publisher']['settings_flat'][$key]) ?
                            ee()->session->cache['publisher']['settings_flat'][$key] :
                            FALSE;
        }
        else
        {
            // Handle some special cases
            switch ($key)
            {
                case 'url_translations':
                    // Regardless of the setting, if there is only 1 language, there is nothing to translate.
                    if (count(ee()->publisher_model->languages) == 1) {
                        $return = FALSE;
                    } else {
                        $return = isset($settings[$key]) ? $settings[$key] : FALSE;
                    }
                break;

                case 'mode':
                    // If using Lite, then force it to prodction mode, no need to do language fallback lookups.
                    if (PUBLISHER_LITE) {
                        return 'production';
                    } else {
                        return $return = isset($settings[$key]) ? $settings[$key] : 'production';
                    }
                break;

                default:
                    $return = isset($settings[$key]) ? $settings[$key] : FALSE;
                break;
            }
        }

        // So we dont get: array(0 => '')
        if (is_array($return) AND isset($return[0]) AND $return[0] == '' AND count($return) == 1)
        {
            return array();
        }

        return $return;
    }

    /**
     * See if the display fallback is valid
     *
     * @return boolean
     */
    public function display_fallback()
    {
        return ($this->get('display_fallback') && REQ == 'CP') ? TRUE : FALSE;
    }

    /**
     * See if the replace fallback is valid
     *
     * @return boolean
     */
    public function replace_fallback()
    {
        return ($this->get('replace_fallback') && REQ != 'CP') ? TRUE : FALSE;
    }

    /**
     * See if either fallback setting is valid
     *
     * @return boolean
     */
    public function show_fallback()
    {
        if ($this->display_fallback() || $this->replace_fallback())
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Load the module settings.
     *
     * @return void
     */
    public function load()
    {
        $db_settings = array();

        // Need to get currently installed module status, not our PUBLISHER_VERSION
        $modules = ee()->addons->get_installed('modules');

        // site_id col added in 1.0.3, need to check here otherwise it will
        // throw an error before the user can upgrade to 1.0.3.
        if (version_compare($modules['publisher']['module_version'], '1.0.3', '<'))
        {
            $qry = ee()->db->get('publisher_settings');
        }
        else
        {
            $qry = ee()->db->get('publisher_settings');

            $enabled_qry = ee()->db->select('val')->get_where('publisher_settings', array('site_id' => 0, 'key' => 'enabled'));

            $enabled = 'yes';

            if ($enabled_qry->num_rows())
            {
                $enabled = $enabled_qry->row('val');
            }
        }

        foreach ($qry->result() as $row)
        {
            if ($row->type == 'json')
            {
                $db_settings[$row->site_id][$row->key] = (array) json_decode($row->val);
            }
            else
            {
                $db_settings[$row->site_id][$row->key] = $row->val;
            }
        }

        // Load config, and figure out our settings
        require PATH_THIRD.'publisher/config.php';

        $sites = ee()->db->get('sites');

        foreach ($sites->result() as $row)
        {
            $this->settings[$row->site_id] = !empty($db_settings[$row->site_id]) ? array_merge($default_settings, $db_settings[$row->site_id]) : $default_settings;
            $this->settings[$row->site_id]['enabled'] = $enabled;
        }

        // If anything is defined in the the site config, merge it.
        if ($override = ee()->config->item('publisher'))
        {
            $this->settings = array_merge($this->settings, $override);
        }

        // Clean it up.
        array_walk_recursive($this->settings, array($this, 'filter_settings'));

        // -------------------------------------------
        //  'publisher_load_settings' hook
        //      - Let users alter the settings. Get by reference!! (I hope I don't regret adding this)
        //
            if (ee()->extensions->active_hook('publisher_load_settings'))
            {
                ee()->extensions->call('publisher_load_settings', $this->settings);
            }
        //
        // -------------------------------------------
    }

    /**
     * Prepare/remap the settings to an array the settings page can use,
     * e.g. array('field[sub][sub]' => 'some val')
     *
     * @return array
     */
    public function prepare($site_id)
    {
        return $this->flatten($this->settings[$site_id]);
    }

    /**
     * Shenanigans to flatten the array
     *
     * @param  array  $array
     * @return array
     */
    private function flatten($array = array())
    {
        $result = array();

        foreach ($array as $k => $v)
        {
            if (is_array($v))
            {
                if ($this->is_last($v))
                {
                    foreach ($v as $nk => $nv)
                    {
                        if ($nk === 0)
                        {
                            $result["{$k}"] = $v;
                            break;
                        }
                        else
                        {
                            $result["{$k}[{$nk}]"] = $nv;
                        }
                    }
                }
                else
                {
                    foreach ($this->flatten_again($v) as $nk => $nv)
                    {
                        if (preg_match('/\[\w+\]/', $nk))
                        {
                            $result["{$k}{$nk}"] = $nv;
                        }
                        else
                        {
                            $result["{$k}[{$nk}]"] = $nv;
                        }
                    }
                }
            }
            else
            {
                $result[$k] = $v;
            }
        }
        return $result;
    }

    /**
     * Even more shenanigans
     *
     * @param  array $array
     * @return array
     */
    private function flatten_again($array)
    {
        $result = array();

        foreach ($array as $k => $v)
        {
            if (is_array($v))
            {
                foreach ($this->flatten_again($v) as $nk => $nv)
                {
                    $result["[{$k}]{$nk}"] = $nv;
                }
            }
            else
            {
                if ( !$this->is_last($array))
                {
                    $result["{$k}"] = $v;
                }
                else
                {
                    $result[""] = $array;
                }
            }
        }

        return $result;
    }

    /**
     * And more!
     *
     * @param  array
     * @return boolean
     */
    private function is_last($array)
    {
        foreach ($array as $k => $v)
        {
            if (is_array($v))
            {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Transform yes/no values into true/false
     *
     * @param array value by reference
     * @param current key
     * @return void
     */
    private function filter_settings(&$value, $key)
    {
        if ($value == 'yes' OR $value == 'no')
        {
            $value = ($value == 'yes') ? TRUE : FALSE;
        }
    }

    /*
    @param - string
    @param - array of data to be inserted, key => value pairs
    @param - array of data used to find the row to update, key => value pairs

    _insert_or_update('some_table', array('foo' => 'bar'), array('id' => 1, 'something' => 'another-thing'))

    */
    private function insert_or_update($table, $data, $where, $primary_key = 'id')
    {
        $query = ee()->db->get_where($table, $where);

        // No records were found, so insert
        if ($query->num_rows() == 0)
        {
            ee()->db->insert($table, $data);
            return ee()->db->insert_id();
        }
        // Update existing record
        elseif ($query->num_rows() == 1)
        {
            ee()->db->where($where)->update($table, $data);
            return ee()->db->select($primary_key)->from($table)->where($where)->get()->row($primary_key);
        }
    }
}