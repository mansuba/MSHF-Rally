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

class Publisher_assets_hooks extends Publisher_hooks_base
{
	public function assets_field_selections_query($assets, $sql)
    {
        return $this->assets_data_query($assets, $sql);
    }

    public function assets_save_row($assets, $data)
    {
        return $this->save_row($assets, $data);
    }

    /**
     * Hook for replace_tag call
     *
     * @param  array $assets
     * @param  string $sql
     * @return object
     */
    public function assets_data_query($assets, $sql)
    {
        // Publisher doesn't support Assets inside Low Vars, so
        // return an unmodified query object.
        preg_match("/ae.var_id = (\d+)/", $sql, $var_id_matches);

        if (isset($var_id_matches[1]))
        {
            return ee()->db->query($sql);
        }

        $publisher_status = ee()->publisher_lib->status;
        $publisher_lang_id = ee()->publisher_lib->lang_id;

        $overrides = FALSE;
        $ignored_data = array();

        // Grab the field_ids so we can test to see if its ignored.
        // If we have ignored fields, unfortunuately we're going to
        // be doing some extra queries :(
        preg_match("/ae.field_id IN \((\S+)\)/", $sql, $field_id_matches);
        preg_match("/ae.entry_id IN \((\d+)/", $sql, $entry_id_matches);

        if (isset($field_id_matches[1]) && isset($entry_id_matches[1]))
        {
            // Split into array, and make sure there is no whitespace.
            $field_ids = explode(',', str_replace(' ', '', $field_id_matches[1]));
            $entry_id = $entry_id_matches[1];

            foreach ($field_ids as $field_id)
            {
                if (ee()->publisher_entry->is_ignored($entry_id, $field_id, $publisher_lang_id))
                {
                    $publisher_status = PUBLISHER_STATUS_OPEN;
                    $publisher_lang_id = ee()->publisher_lib->default_lang_id;

                    // Doing a single field lookup now, not an IN()
                    $sql = preg_replace("/ae.field_id IN \((\S+)\)/", "ae.field_id = {$field_id}", $sql);

                    $query = ee()->publisher_query->modify(
                        'ORDER BY',
                        ' AND ae.publisher_lang_id = '. $publisher_lang_id .' AND ae.publisher_status = "'. $publisher_status .'" ORDER BY',
                        $sql
                    );

                    $ignored_data[$field_id] = $query->result_array();
                }
            }
        }

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

        // Modify offset & limit
        $sql = $this->modify_limit($sql);

        $query = ee()->publisher_query->modify(
            'ORDER BY',
            ' AND ae.publisher_lang_id = '. $publisher_lang_id .' AND ae.publisher_status = "'. $publisher_status .'" ORDER BY',
            $sql
        );

        // Publisher is disabled, but we still need to get default rows,
        // otherwise it'll show duplicate rows.
        if ( !ee()->publisher_setting->enabled())
        {
            return $query;
        }

        // If no rows were found, and fallback is set, then query default language instead.
        if(ee()->publisher_setting->show_fallback() && $query->num_rows() == 0)
        {
            $query = ee()->publisher_query->modify(
                'ORDER BY',
                ' AND ae.publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND ae.publisher_status = "'. $publisher_status .'" ORDER BY',
                $sql
            );

            // Still no results?? Get the default language and open status as a last resort.
            // Also used if the entry is in an ignored channel.
            // If we don't have a draft then try harder to find fallback content.
            // If we do have a draft assume the draft had its rows explicitly removed if we've made it this far.
            if (
                (REQ == 'CP' && $query->num_rows() == 0 && !ee()->publisher_entry->has_draft(ee()->publisher_lib->entry_id, $publisher_lang_id)) ||
                (REQ != 'CP' && $query->num_rows() == 0)
            ){
                $query = ee()->publisher_query->modify(
                    'ORDER BY',
                    ' AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND publisher_status = "'. PUBLISHER_STATUS_OPEN .'" ORDER BY',
                    $sql
                );
            }
        }

        // We have ignored data, so loop through the result set before returning
        // and replace the rows with the ignored/default data. This way all entries
        // will display the open/default_lang_id version of the data.
        if ( !empty($ignored_data) && !$overrides)
        {
            foreach ($query->result_array() as $k => $row)
            {
                // field_id should always exist, but test just incase
                if (isset($row['field_id']) && isset($ignored_data[$row['field_id']][$k]))
                {
                    $query->result_array[$k] = $ignored_data[$row['field_id']][$k];
                }
            }
        }

        // Slice the array to return proper offset/limit
        $query = $this->slice_results($query);

        return $query;
    }
}