<?php

class Publisher_playa extends Publisher_fieldtype
{
    /**
     * When saving a "draft" entry as "open", update all the "draft" items to be identical as "open"
     *
     * @param  array $params
     * @return  void
     */
    public function entry_submission_absolute_end($params)
    {
        // Duplicating the newly edited open row to draft status, so they're in sync.
        if ($params['publisher_save_status'] == PUBLISHER_STATUS_OPEN &&
            ee()->publisher_setting->sync_drafts()
        ){
            $cache = isset(ee()->session->cache['publisher']['matrix_save_draft_as_open']) ?
                     ee()->session->cache['publisher']['matrix_save_draft_as_open'] :
                     array();

            $where = array(
                'publisher_lang_id' => $params['publisher_lang_id'],
                'parent_entry_id'   => $params['entry_id']
            );

            // Grab the open rows first
            $open_rows = ee()->db->where($where)
                             ->where('publisher_status', PUBLISHER_STATUS_OPEN)
                             ->get('playa_relationships');

            // Get the old row_ids from the cache that will be updated below
            // If the open_rows don't have matching row_ids then they are old
            // Matrix rows, new ones were added, hence the cache.
            $row_ids_to_keep = array_keys($cache);
            $rows_to_keep = array();

            foreach ($open_rows->result_array() as $row)
            {
                if ($row['parent_row_id'] !== null && !empty($cache) && !in_array($row['parent_row_id'], $row_ids_to_keep))
                {
                    ee()->db->where('rel_id', $row['rel_id'])
                        ->delete('playa_relationships');
                }
                else
                {
                    $rows_to_keep[] = $row;
                }
            }

            // Delete the old drafts before we create the new ones
            $to_delete = ee()->db->where($where)
                             ->where('publisher_status', PUBLISHER_STATUS_DRAFT)
                             ->delete('playa_relationships');

            foreach($rows_to_keep as $row)
            {
                $row['publisher_lang_id'] = $params['publisher_lang_id'];
                $row['publisher_status'] = PUBLISHER_STATUS_DRAFT;

                // If its a Playa field inside of Matrix, this process will create new Matrix row_ids
                // for the draft rows, so we need to update the row_id in the Playa table too.
                if ($row['parent_row_id'] != '' && isset($cache[$row['parent_row_id']]))
                {
                    $row['parent_row_id'] = $cache[$row['parent_row_id']];
                }

                // Will get duplicate key error if we don't unset this.
                unset($row['rel_id']);

                ee()->db->insert('playa_relationships', $row);
            }
        }
    }

    public function install()
    {
        // Update Playa tables to support drafts and languages
        if (ee()->db->table_exists('playa_relationships') && !ee()->db->field_exists('publisher_lang_id', 'playa_relationships'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."playa_relationships` ADD `publisher_lang_id` int(4) NOT NULL DEFAULT ". ee()->publisher_lib->default_lang_id ." AFTER `rel_order`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."playa_relationships` ADD `publisher_status` varchar(24) NULL DEFAULT '". PUBLISHER_STATUS_OPEN ."' AFTER `publisher_lang_id`");
        }
    }

    public function uninstall()
    {
        if (ee()->db->table_exists('playa_relationships') && ee()->db->field_exists('publisher_lang_id', 'playa_relationships'))
        {
            ee()->db->where('publisher_status', 'draft')->delete('playa_relationships');
            ee()->db->where('publisher_lang_id !=', ee()->publisher_lib->default_lang_id)->delete('playa_relationships');

            ee()->dbforge->drop_column('playa_relationships', 'publisher_status');
            ee()->dbforge->drop_column('playa_relationships', 'publisher_lang_id');
        }
    }
}