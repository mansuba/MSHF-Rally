<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Base Model Class
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

/**
 * This should really be broken out in to 2 separate models languages and fields.
 * Perhaps revisit this at a later date, but erring on the side of if it ain't broke
 * don't fix it.
 */

require PATH_THIRD.'publisher/config.php';

class Publisher_model
{
    public $data_table  = 'publisher_data';
    public $title_table = 'publisher_titles';

    // When sync'ing fields, what table to copy from?
    public $data_table_source = FALSE;

    public $default_language_id = 1;
    public $current_language = array();
    public $languages   = array();
    public $language_codes = array();
    public $languages_active = FALSE;

    public $session;

    protected $zend_cache = array();

    private $default_language = array(
        1 => array(
          'id' => 1,
          'short_name' => 'en',
          'long_name' => 'English',
          'language_pack' => 'english',
          'cat_url_indicator' => 'category',
          'country' => null,
          'is_default' => 'y',
          'is_enabled' => 'y',
          'direction' => 'ltr',
          'latitude' => null,
          'longitude' => null,
          'sites' => '["1"]',
          'short_name_segment' => 'en'
        )
    );

    public function __construct()
    {
        $this->separator = ee()->config->item('word_separator') == 'dash' ? '-' : '_';

        // Load languages so they're in cache.
        $this->languages            = $this->get_languages();
        $this->language_codes       = $this->get_language_codes(TRUE);
        $this->default_language_id  = $this->get_default_language_id();

        // If we only have 1 language, so we're operating in open/draft mode only
        $this->languages_active = count($this->languages) > 1 ? TRUE : FALSE;

        // Create cache
        if (! isset(ee()->session->cache['publisher']))
        {
            ee()->session->cache['publisher'] = array();
        }
        $this->cache =& ee()->session->cache['publisher'];
    }

    public function get_sites()
    {
        if ( !isset(ee()->session->cache['publisher']['sites']))
        {
            $qry = ee()->db->get('sites');

            $sites = array();

            foreach ($qry->result() as $row)
            {
                $sites[$row->site_id] = $row;
            }

            ee()->session->cache['publisher']['sites'] = $sites;
        }

        return ee()->session->cache['publisher']['sites'];
    }

    /**
     * Get all the languages, and optionally copy them to other sites
     * @param  string $index_by The field to set as the array index
     * @return array            All languages organized neatly
     */
    public function get_languages($index_by = 'id', $refresh_cache = FALSE)
    {
        if (defined('PUBLISHER_LITE') AND PUBLISHER_LITE)
        {
            return $this->default_language;
        }

        if (ee()->publisher_lib->is_installing())
        {
            return $this->default_language;
        }

        // If set, its the fastest return possible.
        if (isset(ee()->session->cache['publisher']['languages']) && !$refresh_cache)
        {
            return ee()->session->cache['publisher']['languages'];
        }
        // See if languages are cached
        elseif (($languages = ee()->publisher_cache->driver->get('languages')) === FALSE || $refresh_cache)
        {
            $query = ee()->db->order_by('is_default desc, long_name asc')
                         ->get('publisher_languages');

            $languages = array();
            $copied = FALSE;

            // Organize into a cleaner array with the lang_id as the key for easiser searching
            foreach ($query->result_array() as $row)
            {
                if ($default_language_id = ee()->config->item('publisher_default_language_id'))
                {
                    if ($default_language_id == $row['id'])
                    {
                        $row['is_default'] = 'y';
                    }
                    else
                    {
                        $row['is_default'] = 'n';
                    }
                }

                // Only return languages that are assigned to the current site.
                // If the upgrade process in 0.98.3 failed, probably b/c the user
                // didn't click the update button, make sure this still works.
                if (substr($row['sites'], 0, 2) == 'a:')
                {
                    $sites = unserialize($row['sites']);
                }
                else
                {
                    $sites = json_decode($row['sites']);
                }

                if (in_array(ee()->publisher_lib->site_id, $sites) || in_array('all', $sites))
                {
                    $row['short_name_segment'] = strtolower(str_replace('_', '-', $row['short_name']));
                    $languages[$row[$index_by]] = $row;
                }
                else if (in_array(1, $sites) || in_array('all', $sites))
                {
                    $row['short_name_segment'] = strtolower(str_replace('_', '-', $row['short_name']));
                    $languages[$row[$index_by]] = $row;
                }
            }

            ee()->publisher_cache->driver->save('languages', $languages);
            ee()->session->cache['publisher']['languages'] = $languages;
        }

        return $languages;
    }

    public function get_languages_by($by)
    {
        $return = array();

        foreach ($this->languages as $key => $language)
        {
            if ( !isset($language[$by]))
            {
                show_error("language[$by] does not exist in the language array.");
            }

            $return[$language[$by]] = $language;
        }

        return !empty($return) ? $return : FALSE;
    }

    public function get_enabled_languages()
    {
        $return = array();

        foreach ($this->languages as $key => $language)
        {
            if ($language['is_enabled'])
            {
                $return[$language['id']] = $language;
            }
        }

        return !empty($return) ? $return : FALSE;
    }

    public function get_language_codes($as_url_title = FALSE)
    {
        $return = array();

        foreach ($this->languages as $key => $language)
        {
            if ( !isset($language['short_name']))
            {
                show_error("language[short_name] does not exist in the language array.");
            }

            if ($as_url_title)
            {
                if (isset($language['short_name_segment']))
                {
                    $return[$language['id']] = $language['short_name_segment'];
                }
                else
                {
                    $return[$language['id']] = preg_replace('/[^a-zA-Z0-9]/', $this->separator, strtolower($language['short_name']));
                }
            }
            else
            {
                $return[$language['id']] = $language['short_name'];
            }
        }

        return !empty($return) ? $return : array();
    }

    public function get_language($lang_id, $return = 'short_name')
    {
        if ( !isset($this->languages[$lang_id]))
        {
            show_error("The requested language ({$lang_id}) does not exist in the language array.");
        }

        if ( !isset($this->languages[$lang_id][$return]))
        {
            show_error("The requested attribute ({$return}) does not exist in the language array.");
        }

        return $this->languages[$lang_id][$return];
    }

    public function get_default_language($return = FALSE)
    {
        if ($return)
        {
            return $this->languages[$this->default_language_id][$return];
        }
        else
        {
            return $this->languages[$this->default_language_id];
        }
    }

    public function get_default_language_id()
    {
        if ((defined('PUBLISHER_LITE') AND PUBLISHER_LITE) || ee()->publisher_lib->is_installing())
        {
            return $this->default_language_id;
        }

        if ( !isset(ee()->session->cache['publisher']['default_language_id']))
        {
            if ($default_language_id = ee()->config->item('publisher_default_language_id'))
            {
                ee()->session->cache['publisher']['default_language_id'] = $default_language_id;
            }
            elseif( !array_key_exists('publisher', ee()->addons->get_installed('modules')))
            {
                return $this->default_language_id;
            }
            else
            {
                $query = ee()->db->select('id')
                                  ->where('is_default', 'y')
                                  ->get('publisher_languages');

                ee()->session->cache['publisher']['default_language_id'] = $query->row('id');
            }
        }

        return ee()->session->cache['publisher']['default_language_id'];
    }

    public function get_translated_languages()
    {
        $phrases = ee()->publisher_phrase->get_current(2);

        return $phrases;
    }

    public function fetch_action_id($method = '')
    {
        $qry = ee()->db->select('action_id')
                        ->where('class', PUBLISHER_NAME)
                        ->where('method', $method)
                        ->get('actions');

        if ($qry->num_rows() == 0)
        {
            return NULL;
        }

        return $qry->row('action_id');
    }

    public function get_fields_by_type($type = FALSE)
    {
        if ( !$type)
        {
            show_error("Can't find fields unless a type is defined ;)");
        }
        else
        {
            if ( !isset(ee()->session->cache['publisher'][$type.'_fields']))
            {
                $qry = ee()->db->select('field_id, field_name')
                                    ->where('field_type', $type)
                                    ->get('channel_fields');

                $field_ids = array();

                foreach ($qry->result_array() as $row)
                {
                    $field_ids['field_id_'. $row['field_id']] = $row['field_name'];
                }

                ee()->session->cache['publisher'][$type.'_fields'] = $field_ids;
            }

            return ee()->session->cache['publisher'][$type.'_fields'];
        }
    }

    /**
     * See if a channel is set to ignored
     * @param  int  $channel_id
     * @return boolean
     */
    public function is_ignored_channel($channel_id)
    {
        // On entry save check against site ID, facilitates cross site Channel Form posts
        $site_id = ee()->input->post('site_id') ?: FALSE;

        $ignored_channels = ee()->publisher_setting->ignored_channels($site_id);

        if (is_array($ignored_channels) AND in_array($channel_id, $ignored_channels))
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * See if the requested field is set to ignored
     * @param  string  $field Can be the field_id_N value, or the field type name, e.g. 'matrix'
     * @return boolean
     */
    public function is_ignored_field($field)
    {
        // On entry save check against site ID, facilitates cross site Channel Form posts
        $site_id = ee()->input->post('site_id') ?: FALSE;

        $ignored_fields = ee()->publisher_setting->ignored_fields($site_id);
        $ignored_field_types = ee()->publisher_setting->ignored_field_types($site_id);

        // If settings are empty its obviously not ignored.
        if (empty($ignored_fields) && empty($ignored_field_types))
        {
            return FALSE;
        }

        // If its a full column name, just get the ID
        // Can be field_id_N or field_name
        $field = str_replace('field_id_', '', $field);

        if (
            ($ignored_fields AND in_array($field, $ignored_fields))
            OR
            ($ignored_field_types AND in_array($this->get_field_type($field), $ignored_field_types))
        ){
            return TRUE;
        }

        return FALSE;
    }

    public function get_field_type($field_id)
    {
        $id = substr($field_id, 0, 9);

        $fields = $this->get_fields();

        foreach ($fields as $field)
        {
            if ($field['field_id'] == $id)
            {
                return $field['field_type'];
            }
        }

        return FALSE;
    }

    public function get_fields($with_channel_names = FALSE)
    {
        if ( !isset($this->session->cache['publisher']['fields'][ee()->publisher_lib->site_id]))
        {
            $columns = array(
                'cf.field_id',
                'cf.field_type',
                'cf.field_fmt',
                'cf.field_name',
                'cf.field_label',
                'cf.site_id',
                'cf.field_content_type',
                'cf.field_settings'
            );

            if ($with_channel_names)
            {
                $columns[] = 'fg.group_name';

                $columns = implode(', ', $columns);

                $qry = ee()->db->select($columns)
                           ->from('channel_fields as cf')
                           ->join('field_groups as fg', 'fg.group_id = cf.group_id')
                           ->get();
            }
            else
            {
                $columns = implode(', ', $columns);

                $qry = ee()->db->select($columns)
                           ->get('channel_fields as cf');
            }

            $this->session->cache['publisher']['fields'][ee()->publisher_lib->site_id] = array();

            foreach ($qry->result_array() as $row)
            {
                if (! isset($this->session->cache['publisher']['fields'][$row['site_id']]))
                {
                    $this->session->cache['publisher']['fields'][$row['site_id']] = array();
                }

                $this->session->cache['publisher']['fields'][$row['site_id']][] = $row;
            }
        }

        return $this->session->cache['publisher']['fields'][ee()->publisher_lib->site_id];
    }

    public function get_fields_as_options($with_channel_names = FALSE)
    {
        $fields = $this->get_fields($with_channel_names);
        $options = array();

        foreach ($fields as $key => $row)
        {
            $label = $row['field_label'];

            if (isset($row['group_name']))
            {
                $label = $row['group_name'] .' &raquo; '. $label;
            }

            $options[$row['field_id']] = $label;
        }

        return $options;
    }

    public function get_custom_field_names()
    {
        if ( !isset($this->session->cache['publisher']['custom_field_names'][ee()->publisher_lib->site_id]))
        {
            $fields = $this->get_fields();

            $this->session->cache['publisher']['custom_field_names'][ee()->publisher_lib->site_id] = array();

            foreach ($fields as $field)
            {
                $this->session->cache['publisher']['custom_field_names'][ee()->publisher_lib->site_id]['field_id_'.$field['field_id']] = $field['field_name'];
            }
        }

        return $this->session->cache['publisher']['custom_field_names'][ee()->publisher_lib->site_id];
    }

    /*
    @param - string
    @param - array of data to be inserted, key => value pairs
    @param - array of data used to find the row to update, key => value pairs

    _insert_or_update('some_table', array('foo' => 'bar'), array('id' => 1, 'something' => 'another-thing'))

    */
    public function insert_or_update($table, $data, $where, $primary_key = 'id')
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

    /*
        Generic function to get a field value by language and/or status.
        No fallback option available for a reason.
    */
    public function get_field_value($entry_id, $field, $status = PUBLISHER_STATUS_OPEN, $language = FALSE)
    {
        $alias = $field == 'title' ? 't.' : 'd.';
        $language = $language ?: $this->default_language_id;

        $where = array(
            't.publisher_status'    => $status,
            'd.publisher_status'    => $status,
            't.publisher_lang_id'   => $language,
            'd.publisher_lang_id'   => $language,
            't.entry_id'            => $entry_id,
            't.site_id'             => ee()->publisher_lib->site_id
        );

        $qry = ee()->db->select($alias.$field .' AS '. $field)
                ->from($this->title_table .' AS t')
                ->join($this->data_table .' AS d', 'd.entry_id = t.entry_id')
                ->where($where)
                ->get();

        return $qry->row($field);
    }

    /**
     * Take an array of field data with custom field names as the keys
     * and transpose it into field_id_x key names
     *
     * @param  array $data
     * @param  string $direction Transpose to field_id_X, or field_id_X to field_short_name?
     * @return array
     */
    public function transpose_column_names($data, $direction = 'id')
    {
        if ($direction == 'id')
        {
            foreach ($data as $name => $value)
            {
                if ($field_id = $this->get_custom_field($name, 'field_id', TRUE))
                {
                    unset($data[$name]);
                    $data['field_id_'.$field_id] = $value;
                }
            }
        }
        else if ($direction == 'name')
        {
            foreach ($data as $id => $value)
            {
                if ($name = $this->get_custom_field($id, 'field_name', TRUE))
                {
                    unset($data[$id]);
                    $data[$name] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * Take a column name and make sure its in the field_id_X format.
     *
     * @param  string $name
     * @return string
     */
    public function transpose_column_name($name)
    {
        if ($field_id = $this->get_custom_field($name, 'field_id', TRUE))
        {
            return 'field_id_'. $field_id;
        }

        return $name;
    }

    /**
     * Freakin ee()->db->field_exists() fails horribly too often.
     * Also, cache the query b/c this gets called multiple times
     * per page load.
     *
     * @param  string $column
     * @param  string $table
     * @return boolean
     */
    public function column_exists($column, $table = NULL)
    {
        $table = $table ? ee()->db->dbprefix.$table : ee()->db->dbprefix.$this->data_table;

        if (! isset($this->session->cache['publisher']['columns'][$table]))
        {
            $columns = array();
            $qry = ee()->db->query("SHOW COLUMNS FROM `$table`");

            foreach($qry->result() as $row)
            {
                $columns[] = $row->Field;
            }

            $this->session->cache['publisher']['columns'][$table] = $columns;
        }

        if (in_array($column, $this->session->cache['publisher']['columns'][$table]))
        {
            return TRUE;
        }

        return FALSE;
    }

    public function is_custom_field($field_name)
    {
        // Extra fields to allow and consider 'custom'
        $extra_fields = array('title');

        // Date fields are not translatable, so we let EE handle those
        // @todo - Should I just remove this? Let dates be saved as draft/open even if they are not translatable?
        $is_date_field = FALSE; // $this->is_date_field($field_name);

        // Simple check first
        if ((strstr($field_name, 'field_id_') OR in_array($field_name, $extra_fields)) AND !$is_date_field)
        {
            return TRUE;
        }
        // Was a named parameter value sent? E.g. {page_body} not {field_id_1}
        else if ($field_name === $this->get_custom_field($field_name, 'field_name', TRUE))
        {
            return TRUE;
        }

        return FALSE;
    }

    public function get_custom_field($name, $return = 'field_label', $flipped = FALSE)
    {
        if (! isset($this->session->cache['publisher']['cfields'][ee()->publisher_lib->site_id]))
        {
            $query = ee()->db->select('field_id, field_type, field_fmt, field_name, field_label, site_id, field_content_type, field_settings')
                                  ->get('channel_fields');

            foreach ($query->result_array() as $row)
            {
                if (! isset($this->session->cache['publisher']['cfields'][$row['site_id']]))
                {
                    $this->session->cache['publisher']['cfields'][$row['site_id']] = array();
                }

                if (! isset($this->session->cache['publisher']['cfields_flipped'][$row['site_id']]))
                {
                    $this->session->cache['publisher']['cfields_flipped'][$row['site_id']] = array();
                }

                $this->session->cache['publisher']['cfields'][$row['site_id']]['field_id_'.$row['field_id']] = $row;

                // Create an array to search by friendly field_name instead of field_id_X
                $this->session->cache['publisher']['cfields_flipped'][$row['site_id']][$row['field_name']] = $row;
            }
        }

        if ($flipped AND isset($this->session->cache['publisher']['cfields_flipped'][ee()->publisher_lib->site_id][$name][$return]))
        {
            return $this->session->cache['publisher']['cfields_flipped'][ee()->publisher_lib->site_id][$name][$return];
        }
        // This should be the most common return use case.
        else if (isset($this->session->cache['publisher']['cfields'][ee()->publisher_lib->site_id][$name][$return]))
        {
            return $this->session->cache['publisher']['cfields'][ee()->publisher_lib->site_id][$name][$return];
        }
        else
        {
            return FALSE;
        }
    }

    public function is_date_field($field_name)
    {
        if (! isset($this->session->cache['publisher']['dfields'][ee()->publisher_lib->site_id]))
        {
            $query = ee()->db->select('field_id, field_type, field_fmt, field_name, site_id, field_content_type, field_settings')
                                  ->get('channel_fields');

            $this->session->cache['publisher']['dfields'][ee()->publisher_lib->site_id] = array();

            foreach ($query->result_array() as $row)
            {
                if ($row['field_type'] == 'date')
                {
                    if (! isset($this->session->cache['publisher']['dfields'][$row['site_id']]))
                    {
                        $this->session->cache['publisher']['dfields'][$row['site_id']] = array();
                    }

                    $this->session->cache['publisher']['dfields'][$row['site_id']][] = 'field_id_'.$row['field_id'];
                }
            }
        }

        if (in_array($field_name, $this->session->cache['publisher']['dfields'][ee()->publisher_lib->site_id]))
        {
            return TRUE;
        }

        return FALSE;
    }


    /*
        Add custom column to publisher_data, and keep the col type in sync too
    */
    public function add_column($column_name, $on_save_only = FALSE)
    {
        if ( !strstr($column_name, 'field_id_'))
        {
            return;
        }

        // Before the field is saved, make sure we have a column in publisher_data and it is of the correct type
        if ( !$on_save_only OR (isset($this->method) AND $this->method == 'post_save'))
        {
            $table_name = ee()->db->dbprefix.$this->data_table;

            // Load Forge
            ee()->load->dbforge();

            // Find out what type of column the original is
            $default_field_data = ee()->db->query("SHOW COLUMNS FROM ". ee()->db->dbprefix . $this->data_table_source ." WHERE field = '". $column_name ."'");
            $default_field_type = isset($default_field_data->row()->Type) ? $default_field_data->row()->Type : 'text';

            // If the column does not exists for the field, create it
            if ( !$this->column_exists($column_name))
            {
                $field = array($column_name => array('type' => $default_field_type));
                ee()->dbforge->add_column($this->data_table, $field);
            }
            // Update the column type if it has changed
            else
            {
                // @todo - is this working?
                $publisher_field_data = ee()->db->query("SHOW COLUMNS FROM $table_name WHERE field = '". $column_name ."'");
                $publisher_field_type = $publisher_field_data->row()->Type;

                if($publisher_field_type !== $default_field_type)
                {
                    ee()->db->query("ALTER TABLE $table_name CHANGE ". $column_name ." ". $column_name ." ". $default_field_type);
                }
            }
        }
    }

    /*
        Remove a custom field column from publisher_data
    */
    public function delete_column($type = FALSE, $column_name = FALSE)
    {
        ee()->load->dbforge();

        if ($column_name)
        {
            ee()->dbforge->drop_column($this->data_table, $column_name);
        }
        elseif (
            $type == 'entry' &&
            ee()->publisher_router->class_is('admin_content') &&
            ee()->publisher_router->method_is('field_delete')
        ){
            ee()->dbforge->drop_column($this->data_table, 'field_id_'.ee()->input->post('field_id'));
        }
    }

    /*
        Get all custom columns
    */
    public function get_columns()
    {
        if ( !isset($this->session->cache['publisher']['columns_'.$this->field_table]))
        {
            $this->session->cache['publisher']['columns_'.$this->field_table] = ee()->db->select('field_id')->get($this->field_table);
        }

        $fields = array();

        foreach ($this->session->cache['publisher']['columns_'.$this->field_table]->result() as $row)
        {
            $fields[$row->field_id] = 'field_id_'.$row->field_id;
        }

        return $fields;
    }

    public function get_table_columns($table = FALSE, $append = array())
    {
        if ( !$table)
        {
            show_error('$table is required. publisher_model.php->get_table_columns()');
        }

        $columns = array();

        $qry = ee()->db->query("SHOW COLUMNS FROM ". ee()->db->dbprefix . $table);

        foreach ($qry->result() as $row)
        {
            $columns[] = $row->Field;
        }

        if ( !empty($append))
        {
            $columns = array_merge($columns, $append);
        }

        return $columns;
    }

    /*
        Keep the publisher custom columns in sync with real columns when users add them.
        Called from ext.publisher.php -> sessions_end()
    */
    public function sync_columns($type = FALSE, $session = FALSE)
    {
        // If a session object was sent, use it instead, most likely b/c the ee()->session hasn't been created yet.
        $this->session = $session ?: ee()->session;

        if (REQ == 'CP' AND $this->session->userdata['group_id'] == 1 AND $this->field_table !== FALSE)
        {
            $columns = $this->get_columns();

            foreach ($columns as $field_id => $column_name)
            {
                if ( !$this->column_exists($column_name))
                {
                    $this->add_column($column_name);
                }
            }

            // Will only remove a column after someone deletes from the CP
            $this->delete_column($type);
        }
    }
}