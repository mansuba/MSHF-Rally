<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Library Class
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

class Publisher_searchable
{
    /**
     * Collection of rows for each translation of an entry
     * @var array
     */
    private $entries;

    /**
     * Current entry_id being saved
     * @var integer
     */
    private $entry_id;

    /**
     * Columns used in the query, but we don't want to concatentate
     * @var array
     */
    private $ignore_columns = array('entry_id', 'publisher_lang_id');

    /**
     * If its Grid/Matrix table, then we need the name of the columns table for it.
     * @var string
     */
    private $column_table;

    /**
     * Symbol used to contatenate the translated data
     * @var string
     */
    private $concatentor = '|';

    /**
     * The consolidated field data to be saved to the db
     * @var array
     */
    private $field_data = array();

    /**
     * Flatted version of $field_data to be entered into channel_data
     * @var string
     */
    private $field_data_flattened = '';

    /**
     * Do we update publisher_data?
     * @var boolean
     */
    private $update_publisher_tables = FALSE;

    /**
     * Current language ID
     * @var integer
     */
    public $publisher_lang_id = NULL;

    /**
     * Current status
     * @var string
     */
    public $publisher_status = NULL;

    /**
     * Constructor
     * Update our status and language
     */
    public function __construct()
    {
        if (ee()->publisher_lib->publisher_save_status)
        {
            $this->publisher_status = ee()->publisher_lib->publisher_save_status;
        }

        if (ee()->publisher_lib->lang_id)
        {
            $this->publisher_lang_id = ee()->publisher_lib->lang_id;
        }
    }

    /**
     * Initilize the data collection, compile it, and it to the db.
     *
     * @param  integer $entry_id
     * @param  integer $field_id
     * @param  string  $table
     * @return void
     */
    public function save_entry($entry_id, $field_id, $table)
    {
        $this->entry_id     = $entry_id;
        $this->field_name   = $this->create_field_name($field_id);
        $this->field_id     = $this->create_field_id($field_id);
        $this->table        = $table;
        $this->status       = PUBLISHER_STATUS_OPEN;
        $this->entries      = array();

        $this->update_publisher_tables = FALSE;

        switch ($table)
        {
            case 'publisher_data':
                 $this->get_publisher_data();
            break;

            case ('matrix_data' || strpos($table, 'channel_grid_field_') !== FALSE):
                $this->column_table = $table == 'matrix_data' ? 'matrix_cols' : 'grid_columns';
                $this->update_publisher_tables = TRUE;
                $this->get_matrix_data();
            break;
        }

        $this->compile_fields();
        $this->add_searchable_data();
    }

    /**
     * Make sure we have the full column name
     *
     * @param  string $field_id
     * @return string
     */
    private function create_field_name($field_id)
    {
        return strpos($field_id, 'field_id_') === FALSE ? 'field_id_'.$field_id : $field_id;
    }

    /**
     * Make sure we have an integer
     *
     * @param  string $field_id
     * @return string
     */
    private function create_field_id($field_id)
    {
        return str_replace('field_id_', '', $field_id);
    }

    /**
     * Add the compiled translated data to the native and publisher
     * data tables so the content is searchable.
     *
     * @return void
     */
    private function add_searchable_data()
    {
        ee()->db->where('entry_id', $this->entry_id)
            ->update('channel_data', array(
                $this->field_name => $this->field_data_flattened
            ));

        if ($this->update_publisher_tables)
        {
            foreach ($this->field_data as $lang_id => $field_data)
            {
                ee()->db->where('entry_id', $this->entry_id)
                    ->where('publisher_lang_id', $lang_id)
                    ->where('publisher_status', $this->publisher_status)
                    ->update('publisher_data', array(
                        $this->field_name => $field_data
                    ));
            }
        }
    }

    /**
     * Iterate over the found entry rows and compile it into our searchable data.
     *
     * @return void
     */
    private function compile_fields()
    {
        if (empty($this->entries))
        {
            return;
        }

        $this->field_data = array();

        // Anti-pattern. Arrows ahoy!
        foreach ($this->entries[$this->entry_id] as $language_id => $entries)
        {
            if ( !isset($field_data[$language_id]))
            {
                $this->field_data[$language_id] = '';
            }

            foreach ($entries as $k => $entry)
            {
                foreach ($entry as $column => $value)
                {
                    if ($value && !in_array($column, $this->ignore_columns))
                    {
                        // Strip all the HTML from the content, it shouldn't be searchable
                        $this->field_data[$language_id] .= $this->concatentor . strip_tags($value);
                    }
                }
            }

            $this->field_data[$language_id] = ltrim($this->field_data[$language_id], $this->concatentor);
        }

        $this->field_data_flattened = implode($this->concatentor, $this->field_data);
    }

    /**
     * Get all the translated data for an entry.
     *
     * @return void
     */
    private function get_publisher_data()
    {
        $entries = array();

        $columns = array(
            't.title',
            't.entry_id',
            't.publisher_lang_id',
            't.field_id_'.$this->field_id
        );

        $join = array(
            't.entry_id = d.entry_id',
            't.publisher_lang_id = d.publisher_lang_id',
            't.publisher_status = d.publisher_status'
        );

        $join = implode(' AND ', $join);

        $qry = ee()->db->select($columns)
                    ->from('publisher_titles AS t')
                    ->join('publisher_data AS d', $join)
                    ->where('t.entry_id', $this->entry_id)
                    ->where('t.publisher_status', $this->status)
                    ->get();

        // Create a dictionary for easy reference.
        foreach ($qry->result() as $entry)
        {
            $this->entries[$entry->entry_id][] = $entry;
        }
    }

    /**
     * Get all translated Matrix or Grid data for an entry
     *
     * @return void
     */
    private function get_matrix_data()
    {
        $entries = array();

        $columns = array_merge($this->get_columns(), array(
            'entry_id',
            'publisher_lang_id'
        ));

        $qry = ee()->db->select($columns)
                    ->from($this->table)
                    ->where('entry_id', $this->entry_id)
                    ->where('publisher_status', $this->status)
                    ->get();

        // Create a dictionary for easy reference.
        foreach ($qry->result() as $entry)
        {
            $this->entries[$entry->entry_id][$entry->publisher_lang_id][] = $entry;
        }
    }

    /**
     * Get all the columns for the Matrix or Grid field so we know what to grab
     *
     * @return array
     */
    private function get_columns()
    {
        $qry = ee()->db->select('col_id')
                       ->where('field_id', $this->field_id)
                       ->get($this->column_table);

        $columns = array();

        foreach ($qry->result() as $row)
        {
            $columns[] = 'col_id_'. $row->col_id;
        }

        return $columns;
    }
}