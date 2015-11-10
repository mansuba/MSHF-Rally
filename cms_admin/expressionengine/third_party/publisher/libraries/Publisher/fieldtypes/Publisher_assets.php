<?php

class Publisher_assets extends Publisher_fieldtype
{
	/**
     * Matrix hooks are not quite enough, need to do more processing
     * @param  array $data
     * @return array
     */
    public function post_save($data, $where = array())
    {
        if (ee()->publisher_entry->is_ignored($this->entry_id, $this->field_id, $this->publisher_lang_id))
        {
            return $data;
        }

        if($this->publisher_save_status == PUBLISHER_STATUS_OPEN &&
           $this->entry_id &&
           ee()->publisher_setting->sync_drafts()
        ){
            $where = array(
                'entry_id'          => $this->entry_id,
                'field_id'          => $this->field_id,
                'publisher_lang_id' => $this->publisher_lang_id,
                'publisher_status'  => PUBLISHER_STATUS_DRAFT
            );

            $draft_query = ee()->db->where($where)->get('assets_selections');
            $draft_ids = array();

            foreach ($draft_query->result_array() as $row)
            {
                $draft_ids[] = $row['selection_id'];
            }

            // aaaannnnd delete?
            if ( !empty($draft_ids))
            {
                ee()->db->where_in('selection_id', $draft_ids)->delete('assets_selections');
            }
        }

        // Only if the status has changed and we have a valid entry_id
        // Will never run for a brand new entry, we're only altering
        // existing data. New data is saved by Matrix as usual.
        if ($this->publisher_view_status != $this->publisher_save_status && $this->entry_id)
        {
            $where = array(
                'entry_id'          => $this->entry_id,
                'field_id'          => $this->field_id,
                'publisher_lang_id' => $this->publisher_lang_id,
                'publisher_status'  => PUBLISHER_STATUS_DRAFT
            );

            // First, get all rows to work with before we start deleting stuff
            $draft_query = ee()->db->where($where)->get('assets_selections');

            $where['publisher_status'] = PUBLISHER_STATUS_OPEN;

            $open_query = ee()->db->where($where)->get('assets_selections');

            $draft_ids = array();
            foreach ($draft_query->result_array() as $row)
            {
                $draft_ids[] = $row['selection_id'];
            }

            $open_ids = array();
            foreach ($open_query->result_array() as $row)
            {
                $open_ids[] = $row['selection_id'];
            }

            // Now handle change cases
            if (($this->publisher_view_status == PUBLISHER_STATUS_OPEN || $this->publisher_view_status == STATUS_PENDING) && $this->publisher_save_status == PUBLISHER_STATUS_DRAFT AND !empty($draft_ids))
            {
                // Delete all old draft rows for the entry
                ee()->db->where_in('selection_id', $draft_ids)->delete('assets_selections');
            }
            elseif (($this->publisher_view_status == PUBLISHER_STATUS_DRAFT || $this->publisher_view_status == STATUS_PENDING) && $this->publisher_save_status == PUBLISHER_STATUS_OPEN)
            {
                // Delete open entries, they're going to get re-created with the $altered_data array below
                if ( !empty($open_ids))
                {
                    // Delete all old open rows for the entry
                    ee()->db->where_in('selection_id', $open_ids)->delete('assets_selections');
                }

                // Delete drafts too, if syncing, they'll get re-created below in submit_entry_absolute_end()
                if ( !empty($draft_ids) AND ee()->publisher_setting->sync_drafts())
                {
                    ee()->db->where_in('selection_id', $draft_ids)->delete('assets_selections');
                }
            }
        }

        // Return unmodified data
        return $data;
    }

    public function post_save_cell($data)
    {
        $this->post_save($data);
    }

    /**
     * When saving a "draft" entry as "open", update all the "draft" items to be identical as "open"
     * @param  array $params
     * @return void
     */
    public function entry_submission_absolute_end($params)
    {
        // Duplicating the newly edited open row to draft status, so they're in sync.
        if($params['publisher_save_status'] == PUBLISHER_STATUS_OPEN &&
           ee()->publisher_setting->sync_drafts()
        ){
            $cache = isset(ee()->session->cache['publisher']['matrix_save_draft_as_open']) ?
                     ee()->session->cache['publisher']['matrix_save_draft_as_open'] :
                     array();

            $where = array(
                'publisher_lang_id' => $params['publisher_lang_id'],
                'entry_id'   => $params['entry_id']
            );

            // Grab the open rows first
            $open_rows = ee()->db->where($where)
                             ->where('publisher_status', PUBLISHER_STATUS_OPEN)
                             ->get('assets_selections');

            // Get the old row_ids from the cache that will be updated below
            // If the open_rows don't have matching row_ids then they are old
            // Matrix rows, new ones were added, hence the cache.
            $row_ids_to_keep = array_keys($cache);
            $rows_to_keep = array();

            foreach ($open_rows->result_array() as $row)
            {
                if ($row['row_id'] !== null && !empty($cache) && !in_array($row['row_id'], $row_ids_to_keep))
                {
                    ee()->db->where('selection_id', $row['selection_id'])
                        ->delete('assets_selections');
                }
                else
                {
                    $rows_to_keep[] = $row;
                }
            }

            // Delete the old drafts before we create the new ones
            $to_delete = ee()->db->where($where)
                             ->where('publisher_status', PUBLISHER_STATUS_DRAFT)
                             ->delete('assets_selections');

            foreach($rows_to_keep as $row)
            {
                $row['publisher_lang_id'] = $params['publisher_lang_id'];
                $row['publisher_status'] = PUBLISHER_STATUS_DRAFT;

                // If its a Playa field inside of Matrix, this process will create new Matrix row_ids
                // for the draft rows, so we need to update the row_id in the Playa table too.
                if ($row['row_id'] != '' && isset($cache[$row['row_id']]))
                {
                    $row['row_id'] = $cache[$row['row_id']];
                }

                // Will get duplicate key error if we don't unset this.
                unset($row['selection_id']);

                ee()->db->insert('assets_selections', $row);
            }
        }
    }

    public function install()
    {
        if (ee()->db->table_exists('assets_selections') && !ee()->db->field_exists('publisher_lang_id', 'assets_selections'))
        {
            // Create a unique ID column since Assets does not have one, it makes post_save easier for Publisher.
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."assets_selections` ADD `selection_id` int(4) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY AFTER `file_id`");
            // Move it to the front.
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."assets_selections` MODIFY COLUMN `file_id` int(10) AFTER `selection_id`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."assets_selections` ADD `publisher_lang_id` int(4) NOT NULL DEFAULT  ". ee()->publisher_lib->default_lang_id ." AFTER `entry_id`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."assets_selections` ADD `publisher_status` varchar(24) NULL DEFAULT '". PUBLISHER_STATUS_OPEN ."' AFTER `publisher_lang_id`");
        }
    }
}