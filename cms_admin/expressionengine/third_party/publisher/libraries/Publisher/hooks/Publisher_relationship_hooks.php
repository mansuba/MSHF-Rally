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

class Publisher_relationship_hooks extends Publisher_hooks_base
{
    /**
     * Relationships Query
     *
     * @param  [type]   $type
     * @param  array    $entry_ids
     * @param  array    $depths
     * @param  string   $sql
     * @return array
     */
    public function relationships_query($type, $entry_ids, $depths, $sql)
    {
        if ( !ee()->publisher_setting->enabled())
        {
            $publisher_status = PUBLISHER_STATUS_OPEN;
            $publisher_lang_id = ee()->publisher_lib->default_lang_id;
        }
        else
        {
            $publisher_status = ee()->publisher_lib->status;
            $publisher_lang_id = ee()->publisher_lib->lang_id;
        }

        // Reset the compiled AR object or the query will be malformed
        ee()->db->_reset_select();

        // Make sure we're hitting our table, otherwise $sql result will always be empty
        $sql = str_replace(
            ee()->db->dbprefix.'relationships',
            ee()->db->dbprefix.'publisher_relationships',
            $sql
        );

        // Run the default query so we can grab the field_id. Unfortunately this
        // is the only place we can get it because its not passed as a parameter,
        // nor is it even included in $sql :(
        $field_query = ee()->db->query($sql);

        if ($field_query->num_rows())
        {
            $result = $field_query->result();

            // If this is set, we just need the first row, b/c all rows in the
            // result are from the same field.
            if (isset($result[0]->L0_field) && isset($result[0]->L0_parent))
            {
                $field_id = $result[0]->L0_field;
                $entry_id = $result[0]->L0_parent;

                if (ee()->publisher_entry->is_ignored($entry_id, $field_id, ee()->publisher_lib->lang_id))
                {
                    $query = ee()->publisher_query->modify(
                        array( 'ORDER BY'),
                        array(' AND L0.publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND L0.publisher_status = "'. PUBLISHER_STATUS_OPEN .'" ORDER BY'),
                        $sql
                    );

                    return $query->result_array();
                }
            }
        }

        // Modify offset & limit
        $sql = $this->modify_limit($sql);

        $query = ee()->publisher_query->modify(
            'ORDER BY',
            'AND L0.publisher_lang_id = '. $publisher_lang_id .' AND L0.publisher_status = "'. $publisher_status .'" ORDER BY',
            $sql
        );

        // Publisher is disabled, but we still need to get default rows,
        // otherwise it'll show duplicate rows.
        if ( !ee()->publisher_setting->enabled())
        {
            return $query->result_array();
        }

        // If no rows were found, and fallback is set, then query default language instead.
        // If persistent relationships, then this should never trigger.
        if(ee()->publisher_setting->show_fallback() AND $query->num_rows() == 0)
        {
            $query = ee()->publisher_query->modify(
                'ORDER BY',
                ' AND L0.publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND L0.publisher_status = "'. $publisher_status .'" ORDER BY',
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
                    ' ORDER BY',
                    ' AND L0.publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND L0.publisher_status = "'. PUBLISHER_STATUS_OPEN .'" ORDER BY',
                    $sql
                );
            }
        }

        return $query->result_array();
    }

    /**
     * Get the relationships for display in the CP
     *
     * @param  integer  $entry_id
     * @param  integer  $field_id
     * @param  string   $sql
     * @return array
     */
    public function relationships_display_field($entry_id, $field_id, $sql)
    {
        // Reset the compiled AR object or the query will be malformed
        ee()->db->_reset_select();

        $publisher_status = ee()->publisher_lib->status;
        $publisher_lang_id = ee()->publisher_lib->lang_id;

        $overrides = FALSE;

        // Allow for overrides on the entries tag
        if (isset(ee()->TMPL) AND ee()->TMPL->fetch_param('publisher_status'))
        {
            $overrides = TRUE;
            $publisher_status = ee()->TMPL->fetch_param('publisher_status');
        }

        if (isset(ee()->TMPL) AND ee()->TMPL->fetch_param('publisher_lang_id'))
        {
            $overrides = TRUE;
            $publisher_lang_id = ee()->TMPL->fetch_param('publisher_lang_id');
        }

        // Order has to be escaped otherwise the query fails. Its a reserved word.
        // Also swap our table names in one shot.
        $sql = str_replace(
            array('SELECT child_id, order', ee()->db->dbprefix.'relationships'),
            array('SELECT `child_id`, `order`', ee()->db->dbprefix.'publisher_relationships'),
            $sql
        );

        // If its an ignored field, return immediately.
        if ( !$overrides && ee()->publisher_entry->is_ignored($entry_id, $field_id))
        {
            $query = ee()->publisher_query->modify(
                'WHERE',
                'WHERE publisher_status = "'. PUBLISHER_STATUS_OPEN .'" AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND',
                $sql
            );

            return ee()->db->query($sql)->result_array();
        }

        // Order has to be escaped otherwise the query fails. Its a reserved word.
        // Also swap our table names in one shot.
        $sql = str_replace(
            array('SELECT child_id, order', ee()->db->dbprefix.'relationships'),
            array('SELECT `child_id`, `order`', ee()->db->dbprefix.'publisher_relationships'),
            $sql
        );

        $query = ee()->publisher_query->modify(
            'WHERE',
            'WHERE publisher_status = "'. $publisher_status .'" AND publisher_lang_id = '. $publisher_lang_id .' AND',
            $sql
        );

        if(ee()->publisher_setting->show_fallback() && $query->num_rows() == 0)
        {
            $query = ee()->publisher_query->modify(
                'WHERE',
                'WHERE publisher_status = "'. $publisher_status .'" AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND',
                $sql
            );

            if($query->num_rows() == 0)
            {
                $query = ee()->publisher_query->modify(
                    'WHERE',
                    'WHERE publisher_status = "'. PUBLISHER_STATUS_OPEN .'" AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND',
                    $sql
                );
            }
        }

        return $query->result_array();
    }

    /**
     * Save Relationships
     *
     * @param  array    $relationships
     * @param  integer  $entry_id
     * @param  integer  $field_id     Will be field_id or grid_field_id
     * @return mixed
     */
    public function relationships_post_save($relationships, $entry_id, $field_id)
    {
        // grid_col_id, grid_field_id, grid_row_id need to insert 0s
        // when its not a grid field.

        $unmodified = $relationships;
        $save_status = ee()->publisher_lib->publisher_save_status;
        $view_status = ee()->publisher_lib->status;
        $sync_drafts = ee()->publisher_setting->sync_drafts();

        $grid_field_id = FALSE;

        if (isset($relationships[0]) && isset($relationships[0]['grid_field_id']))
        {
            $grid_field_id = $relationships[0]['grid_field_id'];
        }

        $column = $grid_field_id ? 'grid_field_id' : 'field_id';
        $q_field_id = $grid_field_id ? $grid_field_id : $field_id;

        $publisher_lang_id = ee()->publisher_lib->lang_id;
        $publisher_status = ee()->publisher_lib->status;

        if (ee()->publisher_entry->is_ignored($entry_id, $q_field_id))
        {
            $publisher_status = PUBLISHER_STATUS_OPEN;
            $publisher_lang_id = ee()->publisher_lib->default_lang_id;
        }

        if ($save_status == PUBLISHER_STATUS_OPEN && $sync_drafts)
        {
            ee()->db->where('parent_id', $entry_id)
                ->where($column, $q_field_id)
                ->where('publisher_lang_id', $publisher_lang_id)
                ->delete('publisher_relationships');
        }
        else
        {
            ee()->db->where('parent_id', $entry_id)
                ->where($column, $q_field_id)
                ->where('publisher_status', $save_status)
                ->where('publisher_lang_id', $publisher_lang_id)
                ->delete('publisher_relationships');
        }

        // Grid relationships are captured in the Publisher_grid_hooks class
        if ( !empty($relationships) && $grid_field_id === FALSE)
        {
            foreach ($relationships as $k => $data)
            {
                $relationships[$k]['publisher_lang_id'] = $publisher_lang_id;
                $relationships[$k]['publisher_status']  = $save_status;
            }

            if ( !isset(ee()->session->cache['publisher']['field_relationships'][$field_id]))
            {
                ee()->session->cache['publisher']['field_relationships'][$field_id] = array();
            }

            // In fieldtypes/Publisher_relationship.php->entry_submission_absolute_end() we save this data.
            ee()->session->cache['publisher']['field_relationships'][$field_id] = $relationships;
        }

        // Return nothing we handle all insertions, not EE
        return array();
    }

    /**
     * Modify the row array before the parser gets ahold of it.
     *
     * @param  array  $rows array(entry_id => array(data))
     * @param  object $node
     * @return array
     */
    public function relationships_modify_rows($rows, $node)
    {
        foreach ($rows as $entry_id => $row)
        {
            $entry = ee()->publisher_entry->get($entry_id, ee()->publisher_lib->status, ee()->publisher_lib->lang_id, TRUE);

            if ($entry)
            {
                $entry = (array) $entry;
                $rows[$entry_id] = array_merge($row, $entry);
            }

            // var_dump($rows[$entry_id]['field_id_2']);
            // if ($entry_id == '24') $rows[$entry_id]['field_id_2'] = 'xx';
            // var_dump($rows[$entry_id]);

            $rows[$entry_id]['publisher_lang_id'] = ee()->publisher_lib->lang_id;
            $rows[$entry_id]['publisher_status'] = ee()->publisher_lib->status;

            // Can someone explain to me why file paths go unparsed in core after this hook is called?
            foreach ($rows[$entry_id] as $column => $value)
            {
                if (substr($value, 0, 9) == '{filedir_')
                {
                    $rows[$entry_id][$column] = ee()->publisher_helper->parse_file_path($value);
                }
            }
        }

        return $rows;
    }
}