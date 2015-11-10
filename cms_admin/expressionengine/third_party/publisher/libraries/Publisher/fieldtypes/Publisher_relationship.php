<?php

class Publisher_relationship extends Publisher_fieldtype
{
    /**
     * After an entry is saved update the tables.
     *
     * @param   array $params
     * @return  void
     */
    public function entry_submission_absolute_end($params)
    {
        $save_status = $params['publisher_save_status'];
        $view_status = $params['publisher_view_status'];
        $sync_drafts = ee()->publisher_setting->sync_drafts();

        if (isset(ee()->session->cache['publisher']['grid_relationships']) &&
            !empty(ee()->session->cache['publisher']['grid_relationships'])
        ){
            $grid_relationships = ee()->session->cache['publisher']['grid_relationships'];

            foreach ($grid_relationships as $grid_field_id => $grid_rel_rows)
            {
                $grid_rows = $this->_get_grid_rows($grid_field_id, array(
                    'entry_id' => $params['entry_id'],
                    'publisher_lang_id' => $params['publisher_lang_id']
                ));

                $grid_rows_by_status = array();

                // Organize by status so its easier to work with.
                foreach (array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT) as $status)
                {
                    foreach ($grid_rows as $row_index => $row_data)
                    {
                        if ($row_data['publisher_status'] == $status)
                        {
                            $grid_rows_by_status[$status][] = $row_data;
                        }
                    }
                }

                foreach ($grid_rel_rows as $row_index => $row_data)
                {
                    $column_id = ee()->session->cache['publisher']['grid_relationships_columns'][$grid_field_id][$row_index];

                    foreach ($row_data['data'] as $data_index => $child_id)
                    {
                        $grid_row_id = $grid_rows_by_status[$params['publisher_save_status']][$row_index]['row_id'];
                        $order = isset($row_data['sort'][$data_index]) ? $row_data['sort'][$data_index] : 0;

                        $new_relationship = array(
                            'child_id' => $child_id,
                            'parent_id' => $params['entry_id'],
                            'order' => $order,
                            'publisher_lang_id' => $params['publisher_lang_id'],
                            'publisher_status' => $params['publisher_save_status'],
                            'field_id' => $column_id,
                            'grid_col_id' => $column_id,
                            'grid_field_id' => $grid_field_id,
                            'grid_row_id' => $grid_row_id
                        );

                        ee()->db->insert('publisher_relationships', $new_relationship);

                        // if saving as open, duplicate and save as draft
                        if ($save_status == PUBLISHER_STATUS_OPEN && $sync_drafts && isset($grid_rows_by_status[PUBLISHER_STATUS_DRAFT]))
                        {
                            $new_relationship['publisher_status'] = PUBLISHER_STATUS_DRAFT;
                            $new_relationship['grid_row_id'] = $grid_rows_by_status[PUBLISHER_STATUS_DRAFT][$row_index]['row_id'];

                            ee()->db->insert('publisher_relationships', $new_relationship);
                        }
                    }
                }
            }
        }

        if (isset(ee()->session->cache['publisher']['field_relationships']) &&
            !empty(ee()->session->cache['publisher']['field_relationships'])
        ){
            $field_relationships = ee()->session->cache['publisher']['field_relationships'];

            foreach ($field_relationships as $field_id => $field_rows)
            {
                ee()->db->insert_batch('publisher_relationships', $field_rows);

                // if saving as open, duplicate and save as draft
                if ($save_status == PUBLISHER_STATUS_OPEN && $sync_drafts && !ee()->publisher_entry->is_ignored($params['entry_id'], $field_id))
                {
                    foreach ($field_rows as $row_index => $row_data)
                    {
                        $field_rows[$row_index]['publisher_status'] = PUBLISHER_STATUS_DRAFT;
                    }

                    ee()->db->insert_batch('publisher_relationships', $field_rows);
                }
            }
        }
    }

    private function _get_grid_rows($grid_field_id, $where)
    {
        $qry = ee()->db
                   ->where($where)
                   ->order_by('row_order')
                   ->get('channel_grid_field_'. $grid_field_id);

        return $qry->result_array();
    }

    /**
     * Delete from our custom table when an entry is deleted
     *
     * @param array $data
     * @return void
     */
    public function delete_entries_start($data)
    {
        ee()->db->where_in('parent_id', $data['entry_id'])
            ->delete('publisher_relationships');
    }

    /**
     * Delete from our custom table when an entry is deleted
     *
     * @param array $data
     * @return void
     */
    public function delete_entries_loop($data)
    {
        $data['parent_id'] = $data['entry_id'];
        unset($data['entry_id']);
        unset($data['channel_id']);

        ee()->db->where($data)->delete('publisher_relationships');
    }
}
