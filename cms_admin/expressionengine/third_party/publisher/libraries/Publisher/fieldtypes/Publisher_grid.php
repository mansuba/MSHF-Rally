<?php

class Publisher_grid extends Publisher_fieldtype
{
    /**
     * When saving an entry, set the native tables to have a value,
     * otherwise they won't work with not IS_EMPTY. Also, this means
     * the columns are not searchable.
     *
     * @param array $params
     * @return void
     */
    public function entry_submission_absolute_end($params)
    {
        if (isset(ee()->session->cache['publisher']['grid']['post_data']))
        {
            ee()->load->library('Publisher/Publisher_searchable');

            $data = ee()->session->cache['publisher']['grid']['post_data'];

            foreach ($data as $field_id => $value)
            {
                ee()->publisher_searchable->save_entry($params['entry_id'], $field_id, 'channel_grid_field_'.$field_id);
            }
        }
    }

    /**
     * Every time a new Grid field is displayed in the CP we need
     * to make sure it has the right columns.
     *
     * @return void
     */
    public function install()
    {
        $grids = ee()->publisher_model->get_fields_by_type('grid');

        foreach ($grids as $field_id => $field_name)
        {
            $table_name = 'channel_grid_field_'.str_replace('field_id_', '', $field_id);

            if (ee()->db->table_exists($table_name) AND !ee()->publisher_model->column_exists('publisher_lang_id', $table_name))
            {
                ee()->db->query("ALTER TABLE `". ee()->db->dbprefix.$table_name ."` ADD `publisher_lang_id` int(4) NOT NULL DEFAULT ". ee()->publisher_lib->default_lang_id ." AFTER `row_order`");
                ee()->db->query("ALTER TABLE `". ee()->db->dbprefix.$table_name ."` ADD `publisher_status` varchar(24) NULL DEFAULT '".PUBLISHER_STATUS_OPEN ."' AFTER `publisher_lang_id`");
            }
        }
    }

    public function uninstall() {}
}