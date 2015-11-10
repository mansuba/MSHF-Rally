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

class Publisher_matrix_hooks extends Publisher_hooks_base
{
    /**
     * Modify the Matrix query
     *
     * @param  Object $matrix      Current Matrix object
     * @param  array  $params      Array of params used in the where() clause
     * @param  String $sql         Current query string that Matrix wants to run
     * @param  string $select_mode
     * @return Query result object
     */
	public function matrix_data_query($matrix, $params, $sql, $select_mode)
    {
        // Publisher doesn't support Matrix inside Low Vars, so
        // return an unmodified query object.
        preg_match("/var_id = (\d+)/", $sql, $var_id_matches);

        if (isset($var_id_matches[1]))
        {
            return ee()->db->query($sql);
        }

        $publisher_status = ee()->publisher_lib->status;
        $publisher_lang_id = ee()->publisher_lib->lang_id;

        $overrides = FALSE;

        if ( !ee()->publisher_setting->enabled())
        {
            $publisher_status = PUBLISHER_STATUS_OPEN;
            $publisher_lang_id = ee()->publisher_lib->default_lang_id;
        }

        // Allow for overrides on the entries tag
        if (isset(ee()->TMPL) && ee()->TMPL->fetch_param('publisher_status'))
        {
            $overrides = TRUE;
            $publisher_status = ee()->TMPL->fetch_param('publisher_status');
        }

        if (isset(ee()->TMPL) && ee()->TMPL->fetch_param('publisher_lang_id'))
        {
            $overrides = TRUE;
            $publisher_lang_id = ee()->TMPL->fetch_param('publisher_lang_id');
        }

        // Grab the field_id so we can test to see if its ignored before continuing
        preg_match("/field_id = (\d+)/", $sql, $matches);

        if ( !$overrides && isset($matches[1]))
        {
            $field_id = $matches[1];
            $entry_id = $matrix->entry_id;

            if (ee()->publisher_entry->is_ignored($entry_id, $field_id, $publisher_lang_id))
            {
                return ee()->publisher_query->modify(
                    'AND entry_id',
                    'AND publisher_status = "'. PUBLISHER_STATUS_OPEN .'" AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND entry_id',
                    $sql
                );
            }
        }

        // Modify offset & limit
        $sql = $this->modify_limit($sql);

        $query = ee()->publisher_query->modify(
            'AND entry_id',
            'AND publisher_status = "'. $publisher_status .'" AND publisher_lang_id = '. $publisher_lang_id .' AND entry_id',
            $sql
        );

        // Publisher is disabled, but we still need to get default rows,
        // otherwise it'll show duplicate rows.
        if ( !ee()->publisher_setting->enabled())
        {
            return $query;
        }

        // If $select_mode == 'aggregate', then the rows returned will always be > 1, check value instead
        // Thanks Christian Maloney for this fix.
        if( $select_mode == 'aggregate' )
        {
            $result = $query->row_array();
            $agg_count = $result['aggregate'];
        }

        // If no rows were found, and fallback is set, then query default language instead.
        if (
            (ee()->publisher_setting->show_fallback() && $query->num_rows() == 0) ||
            ($select_mode == 'aggregate' && $agg_count == 0)
        ){
            $query = ee()->publisher_query->modify(
                'AND entry_id',
                'AND publisher_status = "'. $publisher_status .'" AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND entry_id',
                $sql
            );

            // Still no results?? Get the default language and open status as a last resort.
            // Also used if the entry is in an ignored channel.
            // If we don't have a draft then try harder to find fallback content.
            // If we do have a draft assume the draft had its rows explicitly removed if we've made it this far.
            if (
                (REQ == 'CP' && $query->num_rows() == 0 && !ee()->publisher_entry->has_draft($matrix->entry_id, $publisher_lang_id)) ||
                (REQ != 'CP' && $query->num_rows() == 0)
            ){
                $query = ee()->publisher_query->modify(
                    'AND entry_id',
                    'AND publisher_status = "'. PUBLISHER_STATUS_OPEN .'" AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND entry_id',
                    $sql
                );
            }
        }

        // If this is getting called from from Matrix->_save() we don't
        // need any further processing, return the result now.
        if (isset(ee()->session->cache['matrix']['post_data']))
        {
            return $query;
        }

        // Ugh, persistence. Why did I ever add that feature?
        $query = $this->filter_persistent_rows($sql, $query, 'matrix', 'SELECT', 'SELECT publisher_status, publisher_lang_id, ', $select_mode);

        // Slice the array to return proper offset/limit
        $query = $this->slice_results($query);

        // return a query result
        return $query;
    }

    /**
     * Called by Matrix hook for each row in the table.
     *
     * array (size=9)
     *     'col_id_1' => string '' (length=0)
     *     'col_id_2' => string '<p>open row 1</p>
     *     (length=18)
     *     'col_id_4' => string '' (length=0)
     *     'row_order' => int 1
     *     'row_id' => string '131' (length=3)
     *     'site_id' => string '1' (length=1)
     *     'field_id' => string '5' (length=1)
     *     'entry_id' => string '1' (length=1)
     *     'is_draft' => int 0
     *
     * @param  Object $matrix Current Matrix instance
     * @param  Array  $data   What is to be saved to the DB
     * @return Array
     */
    public function matrix_save_row($matrix, $data)
    {
        return $this->save_row($matrix, $data);
    }
}