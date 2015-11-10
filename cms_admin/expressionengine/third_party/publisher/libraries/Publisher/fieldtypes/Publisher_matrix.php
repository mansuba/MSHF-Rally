<?php

class Publisher_matrix extends Publisher_fieldtype
{
    /**
     * Matrix hooks are not quite enough, need to do more processing
     *
     * @param  array $data
     * @return array
     */
    public function post_save($data)
    {
        if (ee()->publisher_entry->is_ignored($this->entry_id, $this->field_id, $this->publisher_lang_id))
        {
            return '';
        }

        // Only if the status has changed and we have a valid entry_id
        // Will never run for a brand new entry, we're only altering
        // existing data. New data is saved by Matrix as usual.
        $where = array(
            'entry_id'      => $this->entry_id,
            'site_id'       => ee()->publisher_lib->site_id,
            'field_id'      => $this->field_id,
            'publisher_lang_id' => $this->publisher_lang_id,
            'publisher_status'  => PUBLISHER_STATUS_DRAFT
        );

        if($this->publisher_save_status == PUBLISHER_STATUS_OPEN &&
           $this->entry_id &&
           ee()->publisher_setting->sync_drafts()
        ){
            // Get draft rows
            $draft_query = ee()->db->where($where)->get('matrix_data');
            $draft_ids = array();

            foreach ($draft_query->result_array() as $row)
            {
                $draft_ids[] = $row['row_id'];
            }

            if ( !empty($draft_ids))
            {
                ee()->db->where_in('row_id', $draft_ids)->delete('matrix_data');
            }
        }

        if ($this->publisher_view_status != $this->publisher_save_status &&
            $this->entry_id &&
            ee()->publisher_setting->sync_drafts()
        ){
            // First, get all rows to work with before we start deleting stuff
            $draft_query = ee()->db->where($where)->get('matrix_data');

            $where['publisher_status'] = PUBLISHER_STATUS_OPEN;

            $open_query = ee()->db->where($where)->get('matrix_data');

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

            // Now handle change cases
            if ($this->publisher_view_status == PUBLISHER_STATUS_OPEN && $this->publisher_save_status == PUBLISHER_STATUS_DRAFT && !empty($draft_ids))
            {
                // Delete all old draft rows for the entry
                ee()->db->where_in('row_id', $draft_ids)->delete('matrix_data');
            }
            elseif ($this->publisher_view_status == PUBLISHER_STATUS_DRAFT && $this->publisher_save_status == PUBLISHER_STATUS_OPEN)
            {
                // Delete open entries, they're going to get re-created with the $altered_data array below
                if ( !empty($open_ids))
                {
                    // Delete all old open rows for the entry
                    ee()->db->where_in('row_id', $open_ids)->delete('matrix_data');
                }

                // Delete drafts too, if syncing, they'll get re-created below in submit_entry_absolute_end()
                if ( !empty($draft_ids) && ee()->publisher_setting->sync_drafts())
                {
                    ee()->db->where_in('row_id', $draft_ids)->delete('matrix_data');
                }
            }

            $this->_make_new();
        }
        // Saving as same status, but still need to make sure the correct language rows are entered
        else
        {
            $where['publisher_status'] = $this->publisher_save_status;

            $qry = ee()->db->where($where)->get('matrix_data');

            // If no rows exist, make sure all of them register as new rows
            if ( !$qry->num_rows())
            {
                $this->_make_new();
            }
            // Get the rows that exist, and only create new rows for those that don't
            else
            {
                $row_ids = array();

                foreach ($qry->result_array() as $row)
                {
                    $row_ids[] = 'row_id_'.$row['row_id'];
                }

                $this->_make_new($row_ids);
            }
        }

        // Finally, handle deleted rows.
        $field_data = isset($_POST['field_id_'.$this->field_id]) ? $_POST['field_id_'.$this->field_id] : array();

        if (isset($field_data['deleted_rows']) && !empty($field_data['deleted_rows']))
        {
            $deleted_rows = array();

            // Now handle deleting the actual Matrix rows.
            foreach ($field_data['deleted_rows'] as $row)
            {
                $where['publisher_status'] = $this->publisher_save_status;
                $where['row_id'] = str_replace('row_id_', '', $row);

                // Before deleting grab the row_ids of the rows where
                // matching the status and lang_id condition too, otherwise
                // it will clobber too many rows when the cell calls its delete_rows method.
                $qry = ee()->db->where($where)->get('matrix_data');

                foreach ($qry->result() as $row_to_delete)
                {
                    $deleted_rows[] = 'row_id_'.$row_to_delete->row_id;
                }

                ee()->db->where($where)->delete('matrix_data');
            }

            // Mark rows to be deleted so Matrix calls the celltype->delete_rows()
            // method so celltypes cleanout their tables too. This is mostly for Playa.
            ee()->session->cache['matrix']['post_data'][$this->field_id]['deleted_rows'] = $deleted_rows;
        }

        // Return blank. Publisher_searchable will
        // handle the field content if it sould be searchable
        // and added to the publisher/channel_data tables.
        return '';
    }

    /**
     * Now lets take the data saved in Matrix's cache, which was just posted
     * and update the array keys to row_new_X. When Matrix gets ahold of
     * our updated cache in it's post_save method it'll think everything is
     * brand new, thus it'll insert new rows with the same data, just different status
     *
     * @param  array  $row_ids Existing rows to ignore
     * @return void
     */
    private function _make_new($row_ids = array())
    {
        $i = 0;
        $altered_data = array();

        if (isset(ee()->session->cache['matrix']['post_data'][$this->field_id]))
        {
            $data = ee()->session->cache['matrix']['post_data'][$this->field_id];

            // More elegant delete solution from Nick L?
            // if (isset($data['deleted_rows']))
            // {
            //     $altered_data['deleted_rows'] = $data['deleted_rows'];
            // }

            if ($data AND isset($data['row_order']))
            {
                // Update the key names so Matrix thinks they're new rows
                foreach ($data['row_order'] as $row_id)
                {
                    if ( !empty($row_ids))
                    {
                        // Only add a new row if it doesn't exist yet
                        if ( !in_array($row_id, $row_ids))
                        {
                            $altered_data['row_order'][] = 'row_new_'.$i;
                            $altered_data['row_new_'.$i] = $data[$row_id];
                            $i++;
                        }
                        // It already exists, so keep it intact
                        else
                        {
                            $altered_data['row_order'][] = $row_id;
                            $altered_data[$row_id] = $data[$row_id];
                        }
                    }
                    else
                    {
                        $altered_data['row_order'][] = 'row_new_'.$i;
                        $altered_data['row_new_'.$i] = $data[$row_id];
                        $i++;
                    }
                }

                ee()->session->cache['matrix']['post_data'][$this->field_id] = $altered_data;
            }
        }
    }

    /**
     * When saving a "draft" entry as "open", update all the "draft" items to be identical as "open"
     *
     * @param  array $params
     * @return void
     */
    public function entry_submission_absolute_end($params)
    {
        if (isset(ee()->session->cache['matrix']) &&
            isset(ee()->session->cache['matrix']['post_data']))
        {
            // Update the channel/publisher_data tables accordingly
            ee()->load->library('Publisher/Publisher_searchable');

            foreach (ee()->session->cache['matrix']['post_data'] as $field_id => $data)
            {
                ee()->publisher_searchable->save_entry($params['entry_id'], $field_id, 'matrix_data');
            }
        }

        // Duplicating the newly edited open row to draft status, so they're in sync.
        if(
            $params['publisher_save_status'] == PUBLISHER_STATUS_OPEN AND
            ee()->publisher_setting->sync_drafts()
        ){
            // Grab the new rows first
            $new_rows = ee()->db->where('publisher_lang_id', $params['publisher_lang_id'])
                             ->where('publisher_status', PUBLISHER_STATUS_OPEN)
                             ->where('entry_id', $params['entry_id'])
                             ->get('matrix_data');

            ee()->session->cache['publisher']['matrix_save_draft_as_open'] = array();

            foreach($new_rows->result_array() as $row)
            {
                $row['publisher_lang_id'] = $params['publisher_lang_id'];
                $row['publisher_status'] = PUBLISHER_STATUS_DRAFT;

                $old_row_id = $row['row_id'];

                // Will get duplicate key error if we don't unset this.
                unset($row['row_id']);

                ee()->db->insert('matrix_data', $row);

                // Save this for later, Publisher_playa/Publisher_assets will need it to update its row_ids
                ee()->session->cache['publisher']['matrix_save_draft_as_open'][$old_row_id] = ee()->db->insert_id();
            }
        }
    }

    /**
     * Get all the Matrix col types, we need it for the persistent Matrix,
     * and most notably persistent Playa fields
     *
     * @return void
     */
    public function sessions_end()
    {
        if ( !isset(ee()->session->cache['publisher']['matrix_col_types']) AND
            array_key_exists('playa', ee()->addons->get_installed('fieldtypes')))
        {
            $qry = ee()->db->select('col_type, col_id, field_id')
                                ->get('matrix_cols');

            ee()->session->cache['publisher']['matrix_col_types'] = array();

            foreach ($qry->result() as $row)
            {
                ee()->session->cache['publisher']['matrix_col_types'][$row->field_id][$row->col_id] = $row->col_type;
            }
        }
    }

    public function install()
    {
        if (ee()->db->table_exists('matrix_data') AND !ee()->db->field_exists('publisher_lang_id', 'matrix_data'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."matrix_data` ADD `publisher_lang_id` int(4) NOT NULL DEFAULT ". ee()->publisher_lib->default_lang_id ." AFTER `site_id`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."matrix_data` ADD `publisher_status` varchar(24) NULL DEFAULT '".PUBLISHER_STATUS_OPEN ."' AFTER `publisher_lang_id`");
        }
    }

    public function uninstall()
    {
        if (ee()->db->table_exists('matrix_data') AND ee()->db->field_exists('publisher_lang_id', 'matrix_data'))
        {
            ee()->db->where('publisher_status', 'draft')->delete('matrix_data');
            ee()->db->where('publisher_lang_id !=', ee()->publisher_lib->default_lang_id)->delete('matrix_data');

            ee()->dbforge->drop_column('matrix_data', 'publisher_status');
            ee()->dbforge->drop_column('matrix_data', 'publisher_lang_id');
        }
    }
}