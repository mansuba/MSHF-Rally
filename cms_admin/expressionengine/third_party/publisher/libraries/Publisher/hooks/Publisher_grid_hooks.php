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
 *
 *
 *
 *
 * This is the order of method calls when a Grid field is saved
 *
 *    grid_model->get_entry_rows
 *    publisher_grid_hooks->grid_query
 *    grid_lib->save
 *    ft.relationships->save
 *    ft.relationships->save
 *    publisher_grid_hooks->grid_save
 *
 *    grid_model->get_entry_rows
 *    publisher_grid_hooks->grid_query
 *    ft.relationships->post_save
 *    publisher_relationships_hooks->relationships_post_save
 *    ft.relationships->post_save
 *    publisher_relationships_hooks->relationships_post_save
 *
 *    publisher_relationship->entry_submission_absolute_end
 */

class Publisher_grid_hooks extends Publisher_hooks_base
{
    /**
     * Modify the Grid query
     *
     * @param array   $entry_ids
     * @param integer $field_id
     * @param string  $content_type Usually "channel", but could be something else, e.g. "category"
     * @param string  $table_name Usually exp_channel_grid_field_XX
     * @param string  $sql Current query being executed, but does not contain a table name.
     * @return array  Query result array.
     */
	public function grid_query($entry_ids, $field_id, $content_type, $table_name, $sql)
    {
        $table_name = ee()->db->dbprefix.$table_name;
        $publisher_lang_id = ee()->publisher_lib->lang_id;
        $publisher_status = ee()->publisher_lib->status;

        // Reset the current query in progress otherwise the ORM will get
        // confused and mix up query strings.
        ee()->db->_reset_select();

        // Publisher doesn't support Grid inside Low Vars, so
        // return a correctly modified query object.
        if (strstr($table_name, 'low_variables_grid'))
        {
            $query = ee()->publisher_query->modify('WHERE', 'FROM '. $table_name .' WHERE', $sql);

            return $query->result_array();
        }

        // This hook is called immediately after saving an entry by Grid_lib->save(),
        // then to grid_model->get_entry_rows() to fetch the newly saved entries.
        // So we need to make sure we're querying of status we just saved as.
        if (ee()->input->post('publisher_save_status'))
        {
            $publisher_status = ee()->input->post('publisher_save_status');
        }

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

        // If its an ignored field or channel, return immediately.
        if ( !$overrides && ee()->publisher_entry->is_ignored($entry_ids, $field_id, $publisher_lang_id))
        {
            $query = ee()->publisher_query->modify(
                'WHERE',
                'FROM '.$table_name.' WHERE publisher_status = "'. PUBLISHER_STATUS_OPEN .'" AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND ',
                $sql
            );

            return $query->result_array();
        }

        // Modify offset & limit
        $sql = $this->modify_limit($sql);

        $query = ee()->publisher_query->modify(
            'WHERE',
            'FROM '.$table_name.' WHERE publisher_status = "'. $publisher_status .'" AND publisher_lang_id = '. $publisher_lang_id .' AND ',
            $sql
        );

        // Publisher is disabled, but we still need to get default rows,
        // otherwise it'll show duplicate rows.
        if ( !ee()->publisher_setting->enabled())
        {
            return $query->result_array();
        }

        // If no rows were found, and fallback is set, then query default language instead.
        if (ee()->publisher_setting->get('display_fallback') && $query->num_rows() == 0)
        {
            $query = ee()->publisher_query->modify(
                'WHERE',
                'FROM '.$table_name.' WHERE publisher_status = "'. $publisher_status .'" AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND ',
                $sql
            );

            // Still no results?? Get the default language and open status as a last resort.
            // Also used if the entry is in an ignored channel.
            if($query->num_rows() == 0)
            {
                $query = ee()->publisher_query->modify(
                    'WHERE',
                    'FROM '.$table_name.' WHERE publisher_status = "'. PUBLISHER_STATUS_OPEN .'" AND publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND ',
                    $sql
                );
            }
        }

        // Ugh, persistence. Why did I ever add that feature?
        $query = $this->filter_persistent_rows($sql, $query, 'grid', 'WHERE', 'FROM '.$table_name.' WHERE ');

        // Slice the array to return proper offset/limit
        $query = $this->slice_results($query);

        // return a query result
        return $query->result_array();
    }

    private function get_col_type($id)
    {
        $qry = ee()->db->select('col_type')
                   ->where('col_id', $id)
                   ->get('grid_columns');

        return $qry->row('col_type');
    }

    /**
     * Called at the end of the Grid save process. Not as complex as saving
     * a Matrix field, but still a lot of array manipulation going on. Fun times.
     *
     * @param integer $entry_id
     * @param integer $field_id
     * @param string  $content_type Usually "channel", but could be something else, e.g. "category"
     * @param string  $table_name Usually exp_channel_grid_field_XX
     * @param array   $data array([deleted_rows], [new_rows], [updated_rows])
     * @return array  Modified $data array
     */
    public function grid_save($entry_id, $field_id, $content_type, $table_name, $data)
    {
        // Safety first!
        // If its a Low Vars field it will be a low_variables content type.
        if ($content_type != 'channel') return $data;

        // Find all relationship fields inside this grid.
        if (ee()->input->post('field_id_'.$field_id))
        {
            foreach (ee()->input->post('field_id_'.$field_id) as $row_id => $row_data)
            {
                foreach ($row_data as $col_id => $col_data)
                {
                    $col_id_num = str_replace('col_id_', '', $col_id);

                    $field_type = $this->get_col_type($col_id_num);

                    // Save data to cache, will be used in
                    // Publisher_relationship.php->entry_submission_absolute_end() to save the data
                    if ($field_type == 'relationship')
                    {
                        if ( !isset(ee()->session->cache['publisher']['grid_relationships']))
                        {
                            ee()->session->cache['publisher']['grid_relationships'] = array();
                            ee()->session->cache['publisher']['grid_relationships_columns'] = array();
                        }

                        ee()->session->cache['publisher']['grid_relationships'][$field_id][] =
                            ee()->session->cache['Relationship_ft'][$col_id.$row_id];

                        ee()->session->cache['publisher']['grid_relationships_columns'][$field_id][] = $col_id_num;
                    }
                }
            }
        }

        // Is it ignored? If so add default Publisher values and return.
        if (ee()->publisher_entry->is_ignored($entry_id, $field_id))
        {
            foreach($data as $row_set => $rows)
            {
                if (in_array($row_set, array('new_rows', 'updated_rows')) && !empty($data[$row_set]))
                {
                    foreach($data[$row_set] as $row_key => $row_data)
                    {
                        $data[$row_set][$row_key]['publisher_lang_id'] = ee()->publisher_lib->default_lang_id;
                        $data[$row_set][$row_key]['publisher_status']  = PUBLISHER_STATUS_OPEN;
                    }
                }
            }

            return $data;
        }

        $where = array(
            'entry_id'      => $entry_id,
            'publisher_lang_id' => ee()->publisher_lib->lang_id,
            'publisher_status'  => PUBLISHER_STATUS_DRAFT
        );

        // First, get all rows to work with before we start deleting stuff
        $draft_query = ee()->db->where($where)->get($table_name);
        $where['publisher_status'] = PUBLISHER_STATUS_OPEN;
        $open_query = ee()->db->where($where)->get($table_name);

        $publisher_view_status = ee()->publisher_lib->status;
        $publisher_save_status = ee()->publisher_lib->publisher_save_status;

        $draft_ids = array();
        foreach ($draft_query->result_array() as $row)
        {
            $draft_ids[] = $row['row_id'];
        }

        $open_ids = array();
        foreach ($open_query->result_array() as $row)
        {
            $open_ids[] = $row['row_id'];
        }

        // Make sure each of the new rows has our column values.
        foreach ($data['new_rows'] as $row_key => $row_data)
        {
            $data['new_rows'][$row_key]['publisher_lang_id'] = ee()->publisher_lib->lang_id;
            $data['new_rows'][$row_key]['publisher_status']  = $publisher_save_status;
        }

        // If its a new draft with no rows, return now, nothing else to process
        if ($publisher_save_status == PUBLISHER_STATUS_DRAFT && empty($open_ids) && empty($draft_ids))
        {
            return $data;
        }

        /*
        $_POST array example for a Grid field

        'field_id_36' =>
            array (size=2)
              'row_id_213' =>
                array (size=2)
                  'col_id_5' => string 'row 1 updated 2' (length=15)
                  'col_id_6' =>
                    array (size=2)
                      'sort' =>
                        array (size=1)
                          0 => string '1' (length=1)
                      'data' =>
                        array (size=1)
                          0 => string '31' (length=2)
              'row_id_214' =>
                array (size=2)
                  'col_id_5' => string 'row 2' (length=5)
                  'col_id_6' =>
                    array (size=2)
                      'sort' =>
                        array (size=2)
                          0 => string '1' (length=1)
                          1 => string '2' (length=1)
                      'data' =>
                        array (size=2)
                          0 => string '37' (length=2)
                          1 => string '26' (length=2)

         */

        // Grab the updated rows, add our column values, and append it to existing new rows array.
        // This is the most important part of the returned data.
        foreach ($data['updated_rows'] as $row_key => $row_data)
        {
            $row_data['publisher_lang_id'] = ee()->publisher_lib->lang_id;
            $row_data['publisher_status']  = ee()->publisher_lib->publisher_save_status;
            $row_data['entry_id']          = $entry_id;

            unset($row_data['row_id']);
            $data['new_rows'][] = $row_data;
        }

        // We're never updating existing rows, always deleting and adding new ones.
        $data['updated_rows'] = array();
        $data['deleted_rows'] = array();

        // Now update as necessary
        if ($publisher_view_status != $publisher_save_status &&
            $entry_id &&
            ee()->publisher_setting->sync_drafts()
        ){
            if ($publisher_view_status == PUBLISHER_STATUS_OPEN && $publisher_save_status == PUBLISHER_STATUS_DRAFT)
            {
                // Delete all the old draft rows.
                foreach ($draft_ids as $draft_id)
                {
                    $data['deleted_rows'][] = array('row_id' => $draft_id);
                }
            }
            else if ($publisher_view_status == PUBLISHER_STATUS_DRAFT && $publisher_save_status == PUBLISHER_STATUS_OPEN)
            {
                // Delete all the old draft rows.
                foreach ($draft_ids as $draft_id)
                {
                    $data['deleted_rows'][] = array('row_id' => $draft_id);
                }

                // Delete all the old published rows.
                foreach ($open_ids as $open_id)
                {
                    $data['deleted_rows'][] = array('row_id' => $open_id);
                }

                // Now copy the open row as a new draft row so they are in sync.
                foreach ($data['new_rows'] as $row_key => $row_data)
                {
                    $row_data['publisher_lang_id'] = ee()->publisher_lib->lang_id;
                    $row_data['publisher_status']  = PUBLISHER_STATUS_DRAFT;

                    $data['new_rows'][] = $row_data;
                }

                foreach ($data['updated_rows'] as $row_key => $row_data)
                {
                    $row_data['publisher_lang_id'] = ee()->publisher_lib->lang_id;
                    $row_data['publisher_status']  = PUBLISHER_STATUS_DRAFT;

                    unset($row_data['row_id']);
                    $data['new_rows'][] = $row_data;
                }
            }
        }
        elseif ($publisher_save_status == PUBLISHER_STATUS_OPEN && ee()->publisher_setting->sync_drafts())
        {
            // Delete all the old published rows.
            foreach ($open_ids as $open_id)
            {
                $data['deleted_rows'][] = array('row_id' => $open_id);
            }

            foreach ($draft_ids as $draft_id)
            {
                $data['deleted_rows'][] = array('row_id' => $draft_id);
            }

            // Now copy the open row as a new draft row so they are in sync.
            foreach ($data['new_rows'] as $row_key => $row_data)
            {
                $row_data['publisher_lang_id'] = ee()->publisher_lib->lang_id;
                $row_data['publisher_status']  = PUBLISHER_STATUS_DRAFT;

                $data['new_rows'][] = $row_data;
            }

            foreach ($data['updated_rows'] as $row_key => $row_data)
            {
                $row_data['publisher_lang_id'] = ee()->publisher_lib->lang_id;
                $row_data['publisher_status']  = PUBLISHER_STATUS_DRAFT;

                unset($row_data['row_id']);
                $data['new_rows'][] = $row_data;
            }
        }
        elseif ($publisher_save_status == PUBLISHER_STATUS_DRAFT)
        {
            // Delete all the old draft rows.
            foreach ($draft_ids as $draft_id)
            {
                $data['deleted_rows'][] = array('row_id' => $draft_id);
            }
        }

        if ( !empty($data['new_rows']))
        {
            if ( !isset(ee()->session->cache['publisher']['grid']))
            {
                ee()->session->cache['publisher']['grid'] = array('post_data' => array());
            }

            ee()->session->cache['publisher']['grid']['post_data'][$field_id] = TRUE;
        }

        return $data;
    }
}