<?php

/**
 * ExpressionEngine Publisher Hook Class
 *
 * @package     ExpressionEngine
 * @subpackage  Hooks
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

class Publisher_hooks_base
{
    protected $limit = 1000;
    protected $offset = 0;

	/**
     * Generic function for P&T fieldtypes save hooks
     *
     * @param  object $obj  field object class
     * @param  array $data
     * @return array
     */
    protected function save_row($obj, $data)
    {
        // Publisher Low Vars.
        if (isset($data['parent_var_id']) || isset($data['var_id']))
        {
            return $data;
        }

        // Playa or Matrix?
        $entry_id = isset($data['parent_entry_id']) ? $data['parent_entry_id'] : $data['entry_id'];
        $field_id = isset($data['parent_field_id']) ? $data['parent_field_id'] : $data['field_id'];

        $publisher_save_status = ee()->publisher_lib->publisher_save_status;

        // If not set, usually if its saved from Low Variables
        if( !$publisher_save_status)
        {
            $publisher_save_status = PUBLISHER_STATUS_OPEN;
        }

        if (ee()->publisher_entry->is_ignored($entry_id, $field_id))
        {
            $data['publisher_status'] = $publisher_save_status;
            $data['publisher_lang_id'] = ee()->publisher_lib->default_lang_id;

            return $data;
        }

        $data['publisher_status'] = $publisher_save_status;
        $data['publisher_lang_id'] = ee()->publisher_lib->lang_id;

        return $data;
    }

    /**
     * Fix from Josh Baker - http://boldminded.com/support/ticket/594
     * If a user sets a limit parameter on the field it will end up truncating
     * the result set needed b/c we need to account for the language rows and
     * open/draft rows. Not 100% keen on this approach though :/
     *
     * @param  string $sql
     * @return string
     */
    protected function modify_limit($sql)
    {
        if (ee()->publisher_lib->is_default_language)
        {
            return $sql;
        }

        // Reset. Since this is a singleton if other add-ons
        // call this they may get an incorrect limit parameter.
        $this->offset = 0;
        $this->limit = 1000;
        $limit_matched = FALSE;

        // Match limit and offset
        if (preg_match('#LIMIT\s+?([0-9]+),\s+?([0-9]+)#i', $sql, $match))
        {
            $limit_matched = TRUE;
            $offset = $match[1];
            $limit  = $match[2];

            $this->offset = $offset;
            $this->limit = $limit;

            // Set offset to 0, we'll slice it later.
            $offset = 0;

            $limit *= count(ee()->publisher_model->languages);
            $limit *= 2; // open, draft

            $sql = preg_replace('#LIMIT\s+?[0-9]+,\s+?([0-9]+)#i', 'LIMIT '.$offset.', '.$limit, $sql, 1);
        }
        // Match just limit
        else if (preg_match('#LIMIT\s+?([0-9]+)#i', $sql, $match))
        {
            $limit_matched = TRUE;
            $limit = $match[1];

            $this->limit = $limit;

            $limit *= count(ee()->publisher_model->languages);
            $limit *= 2; // open, draft

            $sql = preg_replace('#LIMIT\s+?[0-9]+#i', 'LIMIT '.$limit, $sql, 1);
        }

        // if ($limit_matched === FALSE)
        // {
        //     $num_rows = ee()->db->query($sql)
        //                     ->num_rows();

        //     if ($num_rows > 0)
        //     {
        //         // $sql = $sql . ' LIMIT '. $this-, 1';
        //     }
        // }

        return $sql;
    }

    /**
     * Slice the array, which is bigger than a normal query result due to
     * drafts and languages modifications above, to mimic a query LIMIT n, n
     *
     * @param  object $query Current query object
     * @return object
     */
    protected function slice_results($query)
    {
        if (ee()->publisher_lib->is_default_language)
        {
            return $query;
        }

        // TODO, if offset is greater than total rows we need to return
        // an empty array, but for some reason it still shows all the rows.
        // Something to do with the row_ids data selection in Matrix?
        if ((int) $this->offset >= count($query->result_array))
        {
            $query->result_array = array();
            $query->result_object = array();
            $query->num_rows = 0;
        }
        else
        {
            $query->result_array = array_slice($query->result_array, $this->offset, $this->limit);
            $query->result_object = array_slice($query->result_object, $this->offset, $this->limit);
            $query->num_rows = count($query->result_array);
        }

        return $query;
    }

    /**
     * So Grid and Matrix field data remains persistent across languages.
     *
     * @param  string $sql       Current query being executed
     * @param  object $query     Current query result object
     * @param  string $namespace matrix or grid?
     * @param  string $find      Find what in the first query?
     * @param  string $replace   And replace the find with this.
     * @return object            Modified query result object
     */
    protected function filter_persistent_rows($sql, $query, $namespace, $find, $replace, $select_mode = NULL)
    {
        $query_result_array = array();

        // Gather some data and set the result objects which we'll be updating.
        if (
            (ee()->publisher_setting->persistent_relationships() ||
            (ee()->publisher_setting->persistent_matrix()) && !ee()->publisher_lib->is_default_language)
        ){
            // Modify the Grid query to get all the rows including the drafts and translations
            // we'll sort through them below. We need all the data so we can accurately lookup
            // fallback values in the Playa hooks.
            $rows_query = ee()->publisher_query->modify($find, $replace, $sql);

            // Set the properties so we can modify them below
            $rows_query->result_array = $rows_query->result_array();
            $rows_query->result_object = $rows_query->result();

            // This is our main query result containing rows already assigned to the entry.
            $query->result_array = $query->result_array();
            $query->result_object = $query->result();

            // Organize it into a more usable array keyed by the status
            foreach ($rows_query->result_array as $k => $row)
            {
                $query_result_array[$row['publisher_lang_id']][$row['publisher_status']][] = $row;
            }
        }

        // Array to save Matrix row_ids in to use in the playa_field_selections_query() hook.
        if ( !isset(ee()->session->cache['publisher']['ext'][$namespace.'_rows']))
        {
            ee()->session->cache['publisher']['ext'][$namespace.'_rows'] = array();
        }

        // If we have a Playa/Relationship field in a Matrix/Grid row, there is a bit more work to do
        // to get persistent relationships working.
        if (!ee()->publisher_lib->is_default_language &&
            ee()->publisher_setting->persistent_relationships() &&
            (
                $namespace == 'matrix' && array_key_exists('playa', ee()->addons->get_installed('fieldtypes')) ||
                $namespace == 'grid' && array_key_exists('relationship', ee()->addons->get_installed('fieldtypes'))
            )
        ){
            preg_match('/WHERE field_id = (\d+)/', $sql, $field_id_matches);
            preg_match('/entry_id = (\d+)/', $sql, $entry_id_matches);

            if (isset($field_id_matches[1]))
            {
                $field_id = $field_id_matches[1];
                $entry_id = $entry_id_matches[1];

                $columns = ee()->session->cache['publisher']['matrix_col_types'][$field_id];

                $playa_columns = array();

                foreach ($columns as $col_id => $type)
                {
                    if ($type == 'playa')
                    {
                        $playa_columns[] = $col_id;
                    }
                }

                // How deep can we go? 7 levels of control blocks. Lame. How do I refactor this?
                foreach ($query_result_array as $lang_id => $statuses)
                {
                    foreach ($statuses as $status => $result)
                    {
                        foreach ($result as $k => $row)
                        {
                            // We need to tell Matrix that yes there is a Playa field in a column
                            // and it has a value, otherwise the Playa->replace_tag() method will
                            // not be processed b/c $data is empty.
                            if ($select_mode == 'data')
                            {
                                foreach ($row as $column => $value)
                                {
                                    $col_id = str_replace('col_id_', '', $column);

                                    if (in_array($col_id, $playa_columns))
                                    {
                                        // Save the default language row_id for use later in the playa_field_selections_query() hook call.
                                        // We need the real Matrix row_id, not an indexed array key id. The main cached queries above
                                        // should be getting the rows from the default language, so we should have a row_id for each
                                        // since its querying the default language, but we need to update the old IDs with the new IDs
                                        // from the Playa specific data.
                                        if (isset($query->result_array[$k]['row_id']))
                                        {
                                            ee()->session->cache['publisher']['ext'][$namespace.'_rows'][$lang_id][$field_id][$status][$query->result_array[$k]['row_id']] = $row['row_id'];
                                        }

                                        // Update only the Playa field in the row with the default value
                                        if (isset($query->result_array[$k]['col_id_'. $col_id]))
                                        {
                                            $query->result_array[$k]['col_id_'. $col_id] = $value;
                                        }
                                        // If the row does not exist at all in the normal query result
                                        // just pass it the entire default row so we can get the correct
                                        // amount of rows, one of which contains a Playa field. The rest
                                        // of the columns would basically do a content fallback if the
                                        // translated entry has not be edited AFTER the default entry
                                        // had the new rows added to it.
                                        else if ( !isset($query->result_array[$k]))
                                        {
                                            $query->result_array[$k] = $row;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Make sure the Matrix/Grid rows stay in-sync with the default language
        if (ee()->publisher_setting->persistent_matrix() && !ee()->publisher_lib->is_default_language)
        {
            $lang_id = ee()->publisher_lib->lang_id;
            $default_lang_id = ee()->publisher_lib->default_lang_id;
            $status = ee()->publisher_lib->status;

            foreach ($query_result_array as $lang_id => $statuses)
            {
                foreach ($statuses as $status => $result)
                {
                    foreach ($result as $k => $row)
                    {
                        if ( !isset($query->result_array[$k]) &&
                            $lang_id == $row['publisher_lang_id'] &&
                            $status == $row['publisher_status']
                        ){
                            $query->result_array[$k] = $row;
                        }
                    }
                }
            }

            if (empty($query_result_array[$lang_id][$status]))
            {
                // Look for fallbacks
                if ( !empty($query_result_array[$default_lang_id][$status]))
                {
                    $query->result_array = $query_result_array[$default_lang_id][$status];
                }
                elseif ( !empty($query_result_array[$default_lang_id][PUBLISHER_STATUS_OPEN]))
                {
                    $query->result_array = $query_result_array[$default_lang_id][PUBLISHER_STATUS_OPEN];
                }
                else
                {
                    // Fudge the result set by passing the result_id of an empty
                    // dataset. DB_result->_fetch_assoc will then return the
                    // correct/empty data when result_array() is called, which
                    // is what we want.
                    $query->result_id = NULL;
                    $query->result_array = array();
                    $query->result_object = array();
                    $query->num_rows = 0;
                }
            }
            else
            {
                // In DB_result->result_array() if result_array property
                // is not empty, it returns it immediately and never gets
                // to call _fetch_assoc, which is why this (and the above code) works.
                foreach ($query->result_array as $k => $row)
                {
                    if ( !isset($query_result_array[$default_lang_id][$status][$k])
                        // When saving a Grid field it calls Grid_lib->save, but the save_field_data
                        // call does not actually delete the rows, then save calls get_entry_rows
                        // which gets rows that are still present in the db, thus the unset below
                        // can periodically remove the wrong rows. Everything still works fine
                        // because Grid_lib->save unsets the deleted rows from the array its about to
                        // work with THEN at the very end of the method it actually removes
                        // from the database. Talk about convoluted.
                        && ($namespace == 'grid' && !ee()->input->post('publisher_save_status'))
                    ){
                        unset($query->result_array[$k]);
                        unset($query->result_object[$k]);
                    }
                }
            }
        }

        return $query;
    }
}