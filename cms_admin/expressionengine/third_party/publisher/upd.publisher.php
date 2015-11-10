<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Module Install/Update Class
 *
 * @package     ExpressionEngine
 * @subpackage  Modules
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

require PATH_THIRD.'publisher/config.php';

class Publisher_upd {

    public $version = PUBLISHER_VERSION;

    /**
     * Initiate something everytime the Run Update Modules button is clicked
     */
    public function __construct()
    {
        // Upon every update request, check that these columns are added.
        // If someone updated Publisher before EE this will add the columns.
        // $this->update_zero_wing();
    }

    /**
     * Installation Method
     *
     * @return  boolean     TRUE
     */
    public function install()
    {
        $mod_data = array(
            'module_name'           => PUBLISHER_NAME,
            'module_version'        => PUBLISHER_VERSION,
            'has_cp_backend'        => "y",
            'has_publish_fields'    => 'n'
        );

        if ( !session_id())
        {
            @session_start();
        }

        ee()->load->library('Publisher/Publisher_lib');
        ee()->publisher_lib->set_installing();

        ee()->load->library('Publisher/helpers/Publisher_helper');
        ee()->load->library('Publisher/Publisher_cache');
        ee()->load->model('publisher_model');

        ee()->db->insert('modules', $mod_data);

        ee()->load->dbforge();

        $this->install_ext();
        $this->install_data();
        $this->install_approvals();

        $this->install_categories();
        $this->install_relationships();
        $this->install_phrases();

        $this->install_lang();
        $this->install_settings();
        $this->install_actions();
        $this->install_log();

        $this->install_url_translations();
        $this->install_template_previews();

        $this->install_diff();

        return TRUE;
    }

    // ----------------------------------------------------------------

    /**
     * Uninstall
     *
     * @return  boolean     TRUE
     */
    public function uninstall()
    {
        ee()->publisher_relationships->uninstall_data();

        // Proceed with the destruction
        $mod_id = ee()->db->select('module_id')
                                ->get_where('modules', array(
                                    'module_name' => PUBLISHER_NAME
                                ))->row('module_id');

        ee()->db->where('module_id', $mod_id)
                     ->delete('module_member_groups');

        ee()->db->where('module_name', PUBLISHER_NAME)
                     ->delete('modules');

        ee()->load->dbforge();
        ee()->dbforge->drop_table('publisher_titles');
        ee()->dbforge->drop_table('publisher_data');
        ee()->dbforge->drop_table('publisher_categories');
        ee()->dbforge->drop_table('publisher_category_posts');
        ee()->dbforge->drop_table('publisher_relationships');
        ee()->dbforge->drop_table('publisher_phrases');
        ee()->dbforge->drop_table('publisher_phrase_groups');
        ee()->dbforge->drop_table('publisher_phrase_data');
        ee()->dbforge->drop_table('publisher_languages');
        ee()->dbforge->drop_table('publisher_approvals');
        ee()->dbforge->drop_table('publisher_log');
        ee()->dbforge->drop_table('publisher_settings');
        ee()->dbforge->drop_table('publisher_site_pages');
        ee()->dbforge->drop_table('publisher_templates');
        ee()->dbforge->drop_table('publisher_previews');
        ee()->dbforge->drop_table('publisher_diff_settings');

        // Delete records
        ee()->db->where('class', PUBLISHER_NAME)
                     ->delete('actions');

        ee()->db->where('class', PUBLISHER_EXT)
                     ->delete('extensions');

        ee()->publisher_lib->call('uninstall');

        // Remove draft and translated relationships and drop columns
        if (ee()->db->table_exists('relationships') && $this->column_exists('publisher_lang_id', 'relationships'))
        {
            ee()->db->where('publisher_status', 'draft')->delete('relationships');
            ee()->db->where('publisher_lang_id !=', ee()->publisher_lib->default_lang_id)->delete('relationships');
            ee()->dbforge->drop_column('relationships', 'publisher_status');
            ee()->dbforge->drop_column('relationships', 'publisher_lang_id');
        }

        // Remove draft and translated grid rows and drop columns
        if (ee()->db->table_exists('grid_columns'))
        {
            $grids = ee()->publisher_model->get_fields_by_type('grid');

            foreach ($grids as $field_id => $field_name)
            {
                $table_name = 'channel_grid_field_'.str_replace('field_id_', '', $field_id);

                if (ee()->db->table_exists($table_name) && $this->column_exists('publisher_lang_id', $table_name))
                {
                    ee()->db->where('publisher_status', 'draft')->delete($table_name);
                    ee()->db->where('publisher_lang_id !=', ee()->publisher_lib->default_lang_id)->delete($table_name);
                    ee()->dbforge->drop_column($table_name, 'publisher_status');
                    ee()->dbforge->drop_column($table_name, 'publisher_lang_id');
                }
            }
        }

        // Remove the language, otherwise if re-installing and user
        // was viewing a non-default lang they get an error.
        ee()->publisher_session->set('site_language', '');
        ee()->publisher_session->set('site_language_cp', '');

        return TRUE;
    }

    private function install_ext()
    {
        // Delete old hooks
        ee()->db->where('class', PUBLISHER_EXT)
                     ->delete('extensions');

        // Add new hooks
        $ext_template = array(
            'class'    => PUBLISHER_EXT,
            'settings' => '',
            'priority' => 5,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        // Make sure existing extensions are not hogging the redirect hook with a priority of 10, we need it, sorry.
        ee()->db->where('hook', 'entry_submission_redirect')
            ->update('extensions', array('priority' => 9));

        $extensions = array(
            array('hook'=>'sessions_start', 'method'=>'sessions_start', 'priority' => 1),
            array('hook'=>'sessions_end', 'method'=>'sessions_end', 'priority' => 1),

            array('hook'=>'entry_submission_ready', 'method'=>'entry_submission_ready'),
            array('hook'=>'entry_submission_absolute_end', 'method'=>'entry_submission_absolute_end', 'priority' => 10),
            array('hook'=>'entry_submission_start', 'method'=>'entry_submission_start'),
            array('hook'=>'entry_submission_redirect', 'method'=>'entry_submission_redirect'),
            array('hook'=>'safecracker_submit_entry_end', 'method'=>'safecracker_submit_entry_end'),
            array('hook'=>'safecracker_entry_form_tagdata_start', 'method'=>'safecracker_entry_form_tagdata_start'),

            array('hook'=>'delete_entries_loop', 'method'=>'delete_entries_loop'),
            array('hook'=>'delete_entries_start', 'method'=>'delete_entries_start'),

            array('hook'=>'publish_form_channel_preferences', 'method'=>'publish_form_channel_preferences'),
            array('hook'=>'publish_form_entry_data', 'method'=>'publish_form_entry_data'),
            array('hook'=>'channel_entries_query_result', 'method'=>'channel_entries_query_result'),
            array('hook'=>'channel_search_modify_search_query', 'method'=>'channel_search_modify_search_query'),
            array('hook'=>'channel_search_modify_result_query', 'method'=>'channel_search_modify_result_query'),
            array('hook'=>'channel_entries_tagdata_end', 'method'=>'channel_entries_tagdata_end'),
            array('hook'=>'channel_module_categories_start', 'method'=>'channel_module_categories_start'),
            array('hook'=>'channel_module_category_heading_start', 'method'=>'channel_module_category_heading_start'),
            array('hook'=>'core_template_route', 'method'=>'core_template_route', 'priority' => 11),
            array('hook'=>'cp_menu_array', 'method'=>'cp_menu_array'),
            array('hook'=>'cp_js_end', 'method'=>'cp_js_end'),
            array('hook'=>'channel_entries_tagdata', 'method'=>'channel_entries_tagdata'),
            array('hook'=>'template_post_parse', 'method'=>'template_post_parse'),

            // Relationship hooks
            array('hook'=>'relationships_query', 'method'=>'relationships_query'),
            array('hook'=>'relationships_display_field', 'method'=>'relationships_display_field'),
            array('hook'=>'relationships_post_save', 'method'=>'relationships_post_save'),
            array('hook'=>'relationships_modify_rows', 'method'=>'relationships_modify_rows'),

            // Grid hooks
            array('hook'=>'grid_query', 'method'=>'grid_query'),
            array('hook'=>'grid_save', 'method'=>'grid_save'),

            // Matrix hooks
            array('hook'=>'matrix_data_query', 'method'=>'matrix_data_query'),
            array('hook'=>'matrix_save_row', 'method'=>'matrix_save_row'),

            // Playa hooks
            array('hook'=>'playa_fetch_rels_query', 'method'=>'playa_fetch_rels_query'),
            array('hook'=>'playa_field_selections_query', 'method'=>'playa_field_selections_query'),
            array('hook'=>'playa_save_rels', 'method'=>'playa_save_rels'),

            // Assets hooks
            array('hook'=>'assets_save_row', 'method'=>'assets_save_row'),
            array('hook'=>'assets_field_selections_query', 'method'=>'assets_field_selections_query'),
            array('hook'=>'assets_data_query', 'method'=>'assets_data_query'),

            // Structure hooks
            array('hook'=>'structure_get_data_end', 'method'=>'structure_get_data_end'),
            array('hook'=>'structure_get_selective_data_results', 'method'=>'structure_get_selective_data_results'),
            array('hook'=>'structure_reorder_end', 'method'=>'structure_reorder_end'),
            array('hook'=>'structure_create_custom_titles', 'method'=>'structure_create_custom_titles')
        );

        foreach($extensions as $extension)
        {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }
    }

    private function install_actions()
    {
        // Insert our Action
        $query = ee()->db->get_where('actions', array('class' => 'Publisher'));

        if($query->num_rows() == 0)
        {
            $data[] = array(
                'class'     => 'Publisher',
                'method'    => 'set_language'
            );

            foreach ($data as $action)
            {
                ee()->db->insert('actions', $action);
            }
        }
    }

    private function install_log()
    {
        if (! ee()->db->table_exists('publisher_log'))
        {
            ee()->dbforge->add_field(array(
                'id'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'severity'  => array('type' => 'int', 'constraint' => 2),
                'method'    => array('type' => 'varchar', 'constraint' => 64),
                'member_id' => array('type' => 'int', 'constraint' => 10),
                'date'      => array('type' => 'int', 'constraint' => 10),
                'data'      => array('type' => 'text')
            ));

            ee()->dbforge->add_key('id', TRUE);

            ee()->dbforge->create_table('publisher_log');
        }
    }

    private function install_settings()
    {
        if (! ee()->db->table_exists('publisher_settings'))
        {
            ee()->dbforge->add_field(array(
                'id'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'site_id'   => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
                'key'       => array('type' => 'varchar', 'constraint' => 32),
                'val'       => array('type' => 'text'),
                'type'      => array('type' => 'char', 'constraint' => 7)
            ));

            ee()->dbforge->add_key('id', TRUE);

            ee()->dbforge->create_table('publisher_settings');
        }
    }

    private function install_approvals()
    {
        if (! ee()->db->table_exists('publisher_approvals'))
        {
            ee()->dbforge->add_field(array(
                'id'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'type_id'   => array('type' => 'int', 'constraint' => 10),
                'publisher_lang_id' => array('type' => 'int', 'constraint' => 10),
                'type'      => array('type' => 'varchar', 'constraint' => 24),
                'date'      => array('type' => 'int', 'constraint' => 10),
                'member_id' => array('type' => 'int', 'constraint' => 10),
                'data'      => array('type' => 'text'),
                'notes'     => array('type' => 'text')
            ));

            ee()->dbforge->add_key('id', TRUE);

            ee()->dbforge->create_table('publisher_approvals');
        }
    }

    private function install_lang()
    {
        if (! ee()->db->table_exists('publisher_languages'))
        {
            ee()->dbforge->add_field(array(
                'id'         => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'short_name' => array('type' => 'varchar', 'constraint' => 6),
                'long_name'  => array('type' => 'varchar', 'constraint' => 64),
                'language_pack' => array('type' => 'varchar', 'constraint' => 24, 'default' => 'english'),
                'cat_url_indicator' => array('type' => 'varchar', 'constraint' => 75),
                'country'    => array('type' => 'varchar', 'constraint' => 64),
                'is_default' => array('type' => 'char', 'constraint' => 1, 'default' => 'n'),
                'is_enabled' => array('type' => 'char', 'constraint' => 1, 'default' => 'y'),
                'direction'  => array('type' => 'char', 'constraint' => 3, 'default' => 'ltr'),
                'latitude'   => array('type' => 'FLOAT'),
                'longitude'  => array('type' => 'FLOAT'),
                'sites'      => array('type' => 'text')
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('short_name', TRUE);

            ee()->dbforge->create_table('publisher_languages');

            $i = 0;

            $config = ee()->config->item('publisher');

            // Let the user set the languages on install, but
            // default to English if none are defined.
            if( !isset($config['default_languages']))
            {
                $config['default_languages'] = array(
                    'en'    => 'English',
                );
            }

            ee()->load->library('Publisher/Publisher_lib');
            ee()->load->library('Publisher/helpers/Publisher_helper');
            ee()->load->model('publisher_model');
            ee()->load->model('publisher_phrase');

            $group_id = ee()->publisher_phrase->save_group(array(
                'group_name'    => 'languages',
                'group_label'   => 'Languages'
            ));

            $translation = array();

            // Get all current site_ids so the default language is associated to everything
            $qry = ee()->db->select('site_id')->get('sites');
            $sites = array();

            foreach ($qry->result_array() as $row)
            {
                $sites[] = $row['site_id'];
            }

            foreach($config['default_languages'] as $short => $long)
            {
                $data = array(
                    'short_name' => $short,
                    'long_name' => $long,
                    'sites' => json_encode($sites),
                );

                // First lang in array is assumed default
                if($i == 0)
                {
                    $data = array_merge($data, array(
                        'is_default' => 'y',
                        'cat_url_indicator' => ee()->config->item('reserved_category_word')
                    ));
                }

                ee()->db->insert('publisher_languages', $data);
                $lang_id = ee()->db->insert_id();

                // Fudge some data for now so the save() method works and inserts the data.
                ee()->publisher_lib->site_id = ee()->config->item('site_id');
                ee()->publisher_lib->default_lang_id = 1;

                ee()->session->cache['publisher']['install']['languages'] = array(
                    1 => $long
                );

                $phrase_id = ee()->publisher_phrase->save(array(
                    'group_id'      => $group_id,
                    'phrase_name'   => 'language_'. $short,
                    'phrase_value'  => $long
                ));

                $i++;
            }
        }
    }

    private function install_data()
    {
        if (! ee()->db->table_exists('publisher_data'))
        {
            ee()->dbforge->add_field(array(
                'id'                    => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'site_id'               => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
                'channel_id'            => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
                'entry_id'              => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
                'publisher_lang_id'         => array('type' => 'int', 'constraint' => 4),
                'publisher_status'          => array('type' => 'varchar', 'constraint' => 24, 'default' => PUBLISHER_STATUS_OPEN),
                'publisher_approval_status' => array('type' => 'varchar', 'constraint' => 24, 'default' => 'approved')
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('site_id');
            ee()->dbforge->add_key('entry_id');
            ee()->dbforge->add_key('publisher_lang_id');
            ee()->dbforge->add_key('publisher_status');

            ee()->dbforge->create_table('publisher_data');
        }

        if (! ee()->db->table_exists('publisher_titles'))
        {
            ee()->dbforge->add_field(array(
                'id'                    => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'site_id'               => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
                'channel_id'            => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
                'entry_id'              => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
                'publisher_lang_id'         => array('type' => 'int', 'constraint' => 4),
                'publisher_status'          => array('type' => 'varchar', 'constraint' => 24, 'default' => PUBLISHER_STATUS_OPEN),
                'publisher_approval_status' => array('type' => 'varchar', 'constraint' => 24, 'default' => 'approved'),
                'title'                 => array('type' => 'varchar', 'constraint' => 256),
                'url_title'             => array('type' => 'varchar', 'constraint' => 256),
                'page_url'              => array('type' => 'varchar', 'constraint' => 256),
                'hide_in_nav'           => array('type' => 'char', 'constraint' => 1, 'default' => 'n'),
                'template_id'           => array('type' => 'int', 'constraint' => 10, 'default' => 0),
                'parent_id'             => array('type' => 'int', 'constraint' => 10, 'default' => 0),
                'entry_date'            => array('type' => 'int', 'constraint' => 10),
                'edit_date'             => array('type' => 'bigint', 'constraint' => 14),
                'edit_by'               => array('type' => 'int', 'constraint' => 4)
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('site_id');
            ee()->dbforge->add_key('entry_id');
            ee()->dbforge->add_key('publisher_lang_id');
            ee()->dbforge->add_key('publisher_status');

            ee()->dbforge->create_table('publisher_titles');
        }
    }

    private function install_categories()
    {
        if ( !ee()->db->table_exists('publisher_categories'))
        {
            ee()->dbforge->add_field(array(
                'id'                 => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'site_id'            => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
                'publisher_lang_id'  => array('type' => 'int', 'constraint' => 4),
                'publisher_status'   => array('type' => 'varchar', 'constraint' => 24, 'default' => PUBLISHER_STATUS_OPEN),
                'approval_status'    => array('type' => 'varchar', 'constraint' => 24, 'default' => 'approved'),
                'group_id'           => array('type' => 'int', 'constraint' => 10),
                'cat_id'             => array('type' => 'int', 'constraint' => 10),
                'cat_name'           => array('type' => 'varchar', 'constraint' => 100),
                'cat_description'    => array('type' => 'text'),
                'cat_url_title'      => array('type' => 'varchar', 'constraint' => 75),
                'cat_image'          => array('type' => 'text'),
                'imported_date'      => array('type' => 'int', 'constraint' => 10),
                'exported_date'      => array('type' => 'int', 'constraint' => 10),
                'edit_date'          => array('type' => 'bigint', 'constraint' => 14),
                'edit_by'            => array('type' => 'int', 'constraint' => 4)
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('site_id');
            ee()->dbforge->add_key('cat_id');
            ee()->dbforge->add_key('publisher_lang_id');
            ee()->dbforge->add_key('publisher_status');

            ee()->dbforge->create_table('publisher_categories');
        }

        if ( !ee()->db->table_exists('publisher_category_posts'))
        {
            ee()->dbforge->add_field(array(
                'entry_id'          => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
                'cat_id'            => array('type' => 'int', 'constraint' => 10),
                'publisher_lang_id'     => array('type' => 'int', 'constraint' => 4),
                'publisher_status'      => array('type' => 'varchar', 'constraint' => 24, 'default' => PUBLISHER_STATUS_OPEN)
            ));

            ee()->dbforge->add_key('cat_id');
            ee()->dbforge->add_key('entry_id');
            ee()->dbforge->add_key('publisher_lang_id');
            ee()->dbforge->add_key('publisher_status');

            ee()->dbforge->create_table('publisher_category_posts');
        }
    }

    private function install_relationships()
    {
        if (version_compare(APP_VER, '2.6', '>=') && !ee()->db->table_exists('publisher_relationships'))
        {
            ee()->dbforge->add_field(array(
                'relationship_id'       => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'parent_id'             => array('type' => 'int', 'constraint' => 10),
                'child_id'              => array('type' => 'int', 'constraint' => 10),
                'field_id'              => array('type' => 'int', 'constraint' => 10),
                'grid_col_id'           => array('type' => 'int', 'constraint' => 10, 'default' => 0),
                'grid_field_id'         => array('type' => 'int', 'constraint' => 10, 'default' => 0),
                'grid_row_id'           => array('type' => 'int', 'constraint' => 10, 'default' => 0),
                'order'                 => array('type' => 'int', 'constraint' => 10),
                'publisher_lang_id'     => array('type' => 'int', 'constraint' => 4),
                'publisher_status'      => array('type' => 'varchar', 'constraint' => 24, 'default' => PUBLISHER_STATUS_OPEN)
            ));

            ee()->dbforge->add_key('relationship_id');
            ee()->dbforge->add_key('parent_id');
            ee()->dbforge->add_key('child_id');
            ee()->dbforge->add_key('field_id');
            ee()->dbforge->add_key('publisher_lang_id');
            ee()->dbforge->add_key('publisher_status');

            ee()->dbforge->create_table('publisher_relationships');
        }
        else if(version_compare(APP_VER, '2.6', '>='))
        {
            $this->update_zero_wing();
        }
    }

    private function install_phrases()
    {
        if (! ee()->db->table_exists('publisher_phrases'))
        {
            ee()->dbforge->add_field(array(
                'id'                 => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'site_id'            => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
                'group_id'           => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
                'phrase_name'        => array('type' => 'text'),
                'phrase_desc'        => array('type' => 'text'),
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('site_id');

            ee()->dbforge->create_table('publisher_phrases');
        }

        if (! ee()->db->table_exists('publisher_phrase_groups'))
        {
            ee()->dbforge->add_field(array(
                'id'                 => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'site_id'            => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
                'group_name'         => array('type' => 'varchar', 'constraint' => 50),
                'group_label'        => array('type' => 'varchar', 'constraint' => 50),
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('site_id');

            ee()->dbforge->create_table('publisher_phrase_groups');

            // Add our default group
            ee()->db->insert('publisher_phrase_groups', array(
                'site_id'       => ee()->config->item('site_id'),
                'group_name'    => 'default',
                'group_label'   => 'Default'
            ));
        }

        if (! ee()->db->table_exists('publisher_phrase_data'))
        {
            ee()->dbforge->add_field(array(
                'id'                 => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'phrase_id'          => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
                'site_id'            => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
                'publisher_lang_id'      => array('type' => 'int', 'constraint' => 4),
                'publisher_status'       => array('type' => 'varchar', 'constraint' => 24, 'default' => PUBLISHER_STATUS_OPEN),
                'publisher_approval_status' => array('type' => 'varchar', 'constraint' => 24, 'default' => 'approved'),
                'edit_date'          => array('type' => 'bigint', 'constraint' => 14),
                'edit_by'            => array('type' => 'int', 'constraint' => 4),
                'phrase_value'       => array('type' => 'text')
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('site_id');
            ee()->dbforge->add_key('publisher_lang_id');
            ee()->dbforge->add_key('publisher_status');

            ee()->dbforge->create_table('publisher_phrase_data');
        }
    }

    public function install_url_translations()
    {
        // Add new tables
        if ( !ee()->db->table_exists('publisher_templates'))
        {
            ee()->dbforge->add_field(array(
                'id'              => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'type'            => array('type' => 'varchar', 'constraint' => 8),
                'type_id'         => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
                'lang_id'         => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
                'parent_id'       => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
                'translated_name' => array('type' => 'varchar', 'constraint' => 50),
                'route'           => array('type' => 'varchar', 'constraint' => 512)
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('type');
            ee()->dbforge->add_key('type_id');
            ee()->dbforge->add_key('lang_id');

            ee()->dbforge->create_table('publisher_templates');
        }

        if ( !ee()->db->table_exists('publisher_site_pages'))
        {
            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'site_id'           => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
                'publisher_lang_id' => array('type' => 'int', 'constraint' => 4),
                'publisher_status'  => array('type' => 'varchar', 'constraint' => 24, 'default' => PUBLISHER_STATUS_OPEN),
                'site_pages'        => array('type' => 'longtext')
            ));

            ee()->dbforge->add_key('id', TRUE);

            ee()->dbforge->create_table('publisher_site_pages');
        }
    }

    public function install_template_previews()
    {
        // Add new tables
        if ( !ee()->db->table_exists('publisher_previews'))
        {
            ee()->dbforge->add_field(array(
                'id'              => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'site_id'         => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
                'channel_id'      => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
                'template_id'     => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
                'append'          => array('type' => 'char', 'constraint' => 9),
                'custom'          => array('type' => 'text'),
                'override'        => array('type' => 'text'),
                'route'           => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE)
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('channel_id');
            ee()->dbforge->add_key('template_id');

            ee()->dbforge->create_table('publisher_previews');
        }
    }

    private function install_diff()
    {
        if (! ee()->db->table_exists('publisher_diff_settings'))
        {
            ee()->dbforge->add_field(array(
                'id'                => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
                'field_id'          => array('type' => 'int', 'constraint' => 10),
                'snippet_id'        => array('type' => 'int', 'constraint' => 10),
                'template_custom'   => array('type' => 'text'),
                'enabled'           => array('type' => 'char', 'constraint' => 1, 'default' => 'n'),
                'style'             => array('type' => 'char', 'constraint' => 4, 'default' => 'full')
            ));

            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->add_key('field_id', TRUE);

            ee()->dbforge->create_table('publisher_diff_settings');
        }
    }

    /**
     * Freakin ee()->db->field_exists() fails horribly too often.
     *
     * @param  string $column
     * @param  string $table
     * @return boolean
     */
    private function column_exists($column, $table = NULL)
    {
        $table = $table ? ee()->db->dbprefix.$table : ee()->db->dbprefix.$this->data_table;
        $qry = ee()->db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $qry->num_rows == 1 ? TRUE : FALSE;
    }

    /**
     * Module Updater
     *
     * @return  boolean     TRUE
     */
    public function update($current = '')
    {
        $releases = array(
            '0.7'   => array('install_approvals', 'install_log'),
            '0.87'  => array('update_087'),
            '0.94'  => array('update_094'),
            '0.96'  => array('update_096'),
            '0.97'  => array('update_097'),
            '0.97.6'=> array('update_0976', ),
            '0.980' => array('update_098'),
            '0.98.1'=> array('update_0981'),
            '0.98.3'=> array('update_cat_url_translations'),
            '0.98.5'=> array('update_0985'),
            '0.98.7'=> array('update_0987'),
            '0.98.8'=> array('update_0988'),
            '1.0.3' => array('update_103', 'update_fix_matrix_fields'),
            '1.0.3.1' => array('update_1031'),
            '1.0.3.3' => array('update_1033'),
            '1.0.5' => array('update_105'),
            '1.0.8' => array('update_108'),
            '1.0.10' => array('update_1010'),
            '1.0.12' => array('update_1012'),
            '1.0.13' => array('update_1013'),
            '1.1' => array('install_relationships', 'update_relationships', 'update_grid'),
            '1.1.3' => array('update_113'),
            '1.2.0' => array('update_120'),
            '1.2.1' => array('update_121'),
            '1.2.3' => array('update_123')
        );

        // Bust all cache on upgrade just incase any cache keys change.
        ee()->publisher_cache->driver->delete();

        foreach ($releases as $version => $methods)
        {
            if (version_compare($current, $version, '<'))
            {
                foreach ($methods as $method)
                {
                    $this->$method();
                }

                $this->update_ext($version);
            }
        }

        return TRUE;
    }

    /**
     * Make sure the exp_extensions table has the same version
     * @param  integer $version
     * @return void
     */
    private function update_ext($version)
    {
        ee()->db->where('class', PUBLISHER_EXT)
                     ->update('extensions', array('version' => $version));
    }


    private function update_087()
    {
        if (ee()->db->table_exists('publisher_phrases'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_phrases` ADD `phrase_desc` text NULL AFTER `phrase_name`");
        }
    }

    private function update_094()
    {
        $this->install_url_translations();

        // Turn it up to 11...
        ee()->db->where('class', PUBLISHER_EXT)
            ->where('hook', 'entry_submission_absolute_end')
            ->update('extensions', array('priority' => 11));

        // Add new hooks
        $ext_template = array(
            'class'    => PUBLISHER_EXT,
            'settings' => '',
            'priority' => 9,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        $extensions = array(
            // Assets hooks
            array('hook'=>'delete_entries_start', 'method'=>'delete_entries_start'),
            array('hook'=>'structure_reorder_end', 'method'=>'structure_reorder_end')
        );

        foreach($extensions as $extension)
        {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }

        // Add new fields
        if ( !$this->column_exists('url_title', 'publisher_titles'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_titles` ADD `url_title` varchar(256)  NULL AFTER `title`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_titles` ADD `page_url` varchar(256)  NULL AFTER `url_title`");
        }
    }

    private function update_096()
    {
        // Add new hooks
        $ext_template = array(
            'class'    => PUBLISHER_EXT,
            'settings' => '',
            'priority' => 9,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        $extensions = array(
            // Assets hooks
            array('hook'=>'assets_save_row', 'method'=>'assets_save_row'),
            array('hook'=>'assets_field_selections_query', 'method'=>'assets_field_selections_query'),
            array('hook'=>'assets_data_query', 'method'=>'assets_data_query'),
            array('hook'=>'cp_js_end', 'method'=>'cp_js_end'),
            array('hook'=>'channel_entries_tagdata', 'method'=>'channel_entries_tagdata'),
            array('hook'=>'template_post_parse', 'method'=>'template_post_parse'),
        );

        foreach($extensions as $extension)
        {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }

        $matrix_fields = ee()->db->select('entry_id, field_id')
                                      ->group_by('entry_id')
                                      ->get('matrix_data');

        foreach ($matrix_fields->result() as $field)
        {
            ee()->db->where('entry_id', $field->entry_id)
                          ->update('channel_data', array(
                            'field_id_'. $field->field_id => '1'
                          ));
        }
    }

    private function update_097()
    {
        // Add new fields
        if ( !$this->column_exists('url_title', 'publisher_languages'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_languages` ADD `language_pack` varchar(24)  NULL AFTER `long_name`");

            // Set defaults
            ee()->db->update('publisher_languages', array('language_pack' => 'english'));
        }
    }

    private function update_0976()
    {
        ee()->db->where('class', PUBLISHER_EXT)
                     ->where('hook', 'entry_submission_redirect')
                     ->delete('extensions');

        // Add new hook
        $ext_template = array(
            'class'    => PUBLISHER_EXT,
            'settings' => '',
            'priority' => 9,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        $extensions = array(
            array('hook'=>'structure_get_overview_title', 'method'=>'structure_get_overview_title'),
            array('hook'=>'zenbu_modify_status_display', 'method'=>'zenbu_modify_status_display')
        );

        foreach($extensions as $extension)
        {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }

        // BY TURNING IT UP TO 11!
        ee()->db->where('hook', 'core_template_route')
                     ->where('class', PUBLISHER_EXT)
                     ->update('extensions', array('priority' => '11'));
    }

    private function update_098()
    {
        $this->install_template_previews();
    }

    private function update_0981()
    {
        $this->install_diff();
    }

    private function update_cat_url_translations()
    {
        // Add new fields
        if ( !$this->column_exists('cat_url_title', 'publisher_categories'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_categories` ADD `cat_url_title` varchar(75) NULL AFTER `cat_description`");
        }

        if ( !$this->column_exists('cat_url_indicator', 'publisher_languages'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_languages` ADD `cat_url_indicator` varchar(75) NULL AFTER `language_pack`");
        }

        $qry = ee()->db->get('publisher_languages');

        foreach ($qry->result() as $row)
        {
            $sites = json_encode(unserialize($row->sites));

            ee()->db->where('id', $row->id)
                ->update('publisher_languages', array('sites' => $sites));
        }
    }

    private function update_0985()
    {
        ee()->db->insert('extensions', array(
            'class'    => PUBLISHER_EXT,
            'settings' => '',
            'priority' => 9,
            'version'  => $this->version,
            'enabled'  => 'y',
            'hook' => 'structure_create_custom_titles',
            'method' => 'structure_create_custom_titles'
        ));
    }

    private function update_0987()
    {
        // Add new hooks
        $ext_template = array(
            'class'    => PUBLISHER_EXT,
            'settings' => '',
            'priority' => 5,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        $extensions = array(
            array('hook'=>'entry_submission_redirect', 'method'=>'entry_submission_redirect', 'priority'=>9),
            array('hook'=>'relationships_query', 'method'=>'relationships_query'),
            array('hook'=>'relationships_display_field', 'method'=>'relationships_display_field'),
            array('hook'=>'relationships_post_save', 'method'=>'relationships_post_save'),
        );

        foreach($extensions as $extension)
        {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }
    }

    // If updating Publisher after updating EE
    private function update_zero_wing()
    {
        if (ee()->db->table_exists('publisher_relationships') AND
            !$this->column_exists('parent_id', 'publisher_relationships') AND
            version_compare(APP_VER, '2.6', '>=')
        ){
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` CHANGE `rel_id` `relationship_id` int(10) auto_increment");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` CHANGE `rel_parent_id` `parent_id` int(10)");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` CHANGE `rel_child_id` `child_id` int(10)");

            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` ADD `field_id` int(10) NULL AFTER `child_id`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` ADD `order` int(10) DEFAULT 0 AFTER `field_id`");

            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` DROP COLUMN `rel_data`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` DROP COLUMN `rel_type`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` DROP COLUMN `reverse_rel_data`");
        }
    }

    private function update_0988()
    {
        if ($this->column_exists('structure_url_title', 'publisher_titles'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_titles` CHANGE `structure_url_title` `page_url` varchar(256)");
        }
        elseif ( !$this->column_exists('page_url', 'publisher_titles'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_titles` ADD `page_url` varchar(256) NULL AFTER `url_title`");
        }

        // Add new hooks
        $ext_template = array(
            'class'    => PUBLISHER_EXT,
            'settings' => '',
            'priority' => 5,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        $extensions = array(
            array('hook'=>'safecracker_submit_entry_end', 'method'=>'safecracker_submit_entry_end'),
            array('hook'=>'channel_module_category_heading_start', 'method'=>'channel_module_category_heading_start'),
            array('hook'=>'safecracker_entry_form_tagdata_start', 'method'=>'safecracker_entry_form_tagdata_start')
        );

        foreach($extensions as $extension)
        {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }
    }

    private function update_103()
    {
        if ( !$this->column_exists('site_id', 'publisher_settings'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_settings` ADD `site_id` int(4) DEFAULT 1 AFTER `id`");

            ee()->db->where('key', 'enabled')
                ->update('publisher_settings', array('site_id' => 0));
        }
    }

    /**
     * Update the publisher_data so the matrix fields have a 1 so conditionals work
     */
    private function update_fix_matrix_fields()
    {
        if (ee()->db->table_exists('matrix_data'))
        {
            $matrix_fields = ee()->db->select('entry_id, field_id, publisher_lang_id, publisher_status')
                              ->get('matrix_data');

            foreach ($matrix_fields->result() as $row)
            {
                // Just incase someone's data is fubared
                if ( !$row->field_id) continue;

                ee()->db
                    ->where('entry_id', $row->entry_id)
                    ->where('publisher_lang_id', $row->publisher_lang_id)
                    ->where('publisher_status', $row->publisher_status)
                    ->update('publisher_data', array(
                        'field_id_'. $row->field_id => '1'
                    ));

            }
        }
    }

    private function update_1031()
    {
        if ( !$this->column_exists('template_id', 'publisher_titles'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_titles` ADD `template_id` int(10) NULL AFTER `page_url`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_titles` ADD `parent_id` int(10) NULL AFTER `template_id`");

            $structure_is_installed = ee()->publisher_site_pages->is_installed('structure');

            foreach (array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT) as $status)
            {
                foreach (ee()->publisher_model->languages as $lang_id => $language)
                {
                    $pages = ee()->publisher_site_pages->get($lang_id, FALSE, $status, TRUE);

                    foreach ($pages as $site_id => $site_pages)
                    {
                        foreach ($site_pages['templates'] as $entry_id => $template_id)
                        {
                            $parent_id = '';

                            if ($structure_is_installed)
                            {
                                $parent_id = ee()->db->select('parent_id')
                                                     ->where('entry_id', $entry_id)
                                                     ->get('structure')
                                                     ->row('parent_id');

                                if (is_array($parent_id)) $parent_id = '';
                            }

                            ee()->db->where('entry_id', $entry_id)
                                ->where('site_id', $site_id)
                                ->update('publisher_titles', array(
                                    'template_id' => $template_id,
                                    'parent_id'   => $parent_id
                                ));
                        }
                    }

                    if ($structure_is_installed)
                    {
                        $listings = ee()->db->get('structure_listings');

                        foreach ($listings->result() as $row)
                        {
                            ee()->db->where('entry_id', $row->entry_id)
                                ->where('site_id', $row->site_id)
                                ->update('publisher_titles', array(
                                    'template_id' => $row->template_id,
                                    'parent_id'   => $row->parent_id
                                ));
                        }
                    }
                }
            }
        }
    }

    private function update_1033()
    {
        $sites = ee()->publisher_model->get_sites();
        $site_options = array();

        foreach ($sites as $site_id => $site)
        {
            $site_options[] = (string) $site_id;
        }

        $sites = json_encode($site_options);

        ee()->db->where('is_default', 'y')
            ->update('publisher_languages', array('sites' => $sites));
    }

    private function update_105()
    {
        if (ee()->publisher_site_pages->is_installed('pages'))
        {
            $pages = ee()->publisher_site_pages->get_core_pages();
            $pages = $pages[ee()->publisher_lib->site_id];

            ee()->publisher_site_pages->rebuild(array(), $pages, ee()->publisher_model->languages);

            foreach ($pages['uris'] as $entry_id => $uri)
            {
                $template_id = isset($pages['templates'][$entry_id]) ? $pages['templates'][$entry_id] : 0;

                ee()->db->where('entry_id', $entry_id)
                    ->update('publisher_titles', array(
                        'page_url' => $uri,
                        'template_id' => $template_id
                    ));
            }
        }
    }

    private function update_108()
    {
        $qry = ee()->db->where(array(
            'hook' => 'safecracker_entry_form_tagdata_end',
            'class' => 'Publisher_ext'
        ))->get('extensions');

        if ( !$qry->num_rows())
        {
            ee()->db->insert('extensions', array(
                'class'    => PUBLISHER_EXT,
                'settings' => '',
                'priority' => 5,
                'version'  => $this->version,
                'enabled'  => 'y',
                'hook' => 'safecracker_entry_form_tagdata_end',
                'method' => 'safecracker_entry_form_tagdata_end'
            ));
        }

        // If string based approval emails exist, assign them to actual member's instead.
        $addresses = ee()->publisher_setting->get('approval[to]');

        if ($addresses == '') return;

        $addresses = explode("\n", $addresses);

        $qry = ee()->db->select('member_id')->where_in('email', $addresses)->get('members');

        $updates = array();
        foreach ($qry->result() as $row)
        {
            $updates[] = $row->member_id;
        }

        $qry = ee()->db->select('val')->where('key', 'approval')->get('publisher_settings');

        if ($approval = $qry->row('val'))
        {
            $approval = json_decode($approval);
            $approval->to = $updates;
            $approval = json_encode($approval);

            ee()->db->where('key', 'approval')->update('publisher_settings', array('val' => $approval));
        }
    }

    private function update_1010()
    {
        if ( !$this->column_exists('publisher_lang_id', 'publisher_approvals'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_approvals` ADD `publisher_lang_id` int(10) DEFAULT 1 AFTER `type`");
        }

        if ( !ee()->publisher_site_pages->is_installed())
        {
            return;
        }

        foreach(ee()->publisher_model->languages as $lang_id => $data)
        {
            foreach (array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT) as $status)
            {
                $pages = ee()->publisher_site_pages->get($lang_id, FALSE, $status, TRUE);

                foreach ($pages as $site_id => $data)
                {
                    foreach ($data['uris'] as $entry_id => $uri)
                    {
                        if ($uri == '/example/pages/uri/' || $uri == '/example/pages/uri')
                        {
                            unset($pages[$site_id]['uris'][$entry_id]);
                            unset($pages[$site_id]['templates'][$entry_id]);
                        }
                    }

                    $insert_data = array(
                        'publisher_lang_id' => $lang_id,
                        'publisher_status'  => $status,
                        'site_id'           => $site_id,
                        'site_pages'        => ee()->publisher_site_pages->json_encode_pages($pages)
                    );

                    $where = array(
                        'publisher_lang_id' => $lang_id,
                        'publisher_status'  => $status,
                        'site_id'           => $site_id
                    );

                    ee()->publisher_model->insert_or_update('publisher_site_pages', $insert_data, $where);

                    if ($status == PUBLISHER_STATUS_OPEN && $lang_id == ee()->publisher_lib->default_lang_id)
                    {
                        $insert_data = array('site_pages' => base64_encode(serialize($pages)));
                        $where = array('site_id' => $site_id);

                        // Update our core table
                        ee()->publisher_model->insert_or_update('sites', $insert_data, $where, 'site_id');
                    }
                }
            }
        }
    }

    private function update_1012()
    {
        if ( !$this->column_exists('publisher_lang_id', 'publisher_approvals'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_approvals` ADD `publisher_lang_id` int(10) DEFAULT 1 AFTER `type`");
        }
    }

    private function update_1013()
    {
        ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_approvals` MODIFY data LONGTEXT");
    }

    private function update_grid()
    {
        if (version_compare(APP_VER, '2.7', '<') || !ee()->db->table_exists('grid_columns'))
        {
            return;
        }

        ee()->load->library('Publisher/Publisher_fieldtype');
        ee()->load->library('Publisher/fieldtypes/Publisher_grid');
        ee()->publisher_grid->install();

        $qry = ee()->db->where('hook', 'grid_query')->get('extensions');

        if ($qry->num_rows() == 0)
        {
            // Add new hooks
            $ext_template = array(
                'class'    => PUBLISHER_EXT,
                'settings' => '',
                'priority' => 5,
                'version'  => $this->version,
                'enabled'  => 'y'
            );

            $extensions = array(
                array('hook'=>'grid_query', 'method'=>'grid_query'),
                array('hook'=>'grid_save', 'method'=>'grid_save')
            );

            foreach($extensions as $extension)
            {
                ee()->db->insert('extensions', array_merge($ext_template, $extension));
            }
        }

        if ( !$this->column_exists('grid_col_id', 'publisher_relationships'))
        {
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` ADD `grid_col_id` int(10) DEFAULT 1 AFTER `field_id`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` ADD `grid_field_id` int(10) DEFAULT 1 AFTER `grid_col_id`");
            ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_relationships` ADD `grid_row_id` int(10) DEFAULT 1 AFTER `grid_field_id`");
        }
    }

    private function update_relationships()
    {
        ee()->load->model('publisher_relationships');
        ee()->publisher_relationships->migrate_data();
    }

    private function update_113()
    {
        // Add new hooks
        $ext_template = array(
            'class'    => PUBLISHER_EXT,
            'settings' => '',
            'priority' => 5,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        $extensions = array(
            array('hook'=>'relationships_modify_rows', 'method'=>'relationships_modify_rows'),
            array('hook'=>'channel_entries_tagdata_end', 'method'=>'channel_entries_tagdata_end')
        );

        foreach($extensions as $extension)
        {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }
    }

    private function update_120()
    {
        // Add new hooks
        $ext_template = array(
            'class'    => PUBLISHER_EXT,
            'settings' => '',
            'priority' => 5,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        $extensions = array(
            array('hook'=>'channel_search_modify_search_query', 'method'=>'channel_search_modify_search_query'),
            array('hook'=>'channel_search_modify_result_query', 'method'=>'channel_search_modify_result_query')
        );

        foreach($extensions as $extension)
        {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }

        // Make what was a hidden setting public
        $setting = ee()->config->item('publisher_hide_prefix_on_default_language');

        if ($setting == 'y')
        {
            $qry = ee()->db->select('site_id')->get('sites');

            foreach ($qry->result_array() as $row)
            {
                ee()->db->insert('publisher_settings', array(
                    'site_id' => $row['site_id'],
                    'key' => 'hide_prefix_on_default_language',
                    'val' => 'yes',
                    'type' => 'boolean'
                ));
            }
        }

        ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_templates` ADD `parent_id` int(4) DEFAULT 0 AFTER `lang_id`");
        ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_templates` ADD `route` varchar(512) DEFAULT '' AFTER `translated_name`");

        // ALTER TABLE `exp_publisher_templates` ADD `parent_id` int(4) AFTER `lang_id`;
        // ALTER TABLE `exp_publisher_templates` ADD `route` varchar(512) DEFAULT '' AFTER `translated_name`;
    }

    private function update_121()
    {
        $qry = ee()->db->select('site_id')->get('sites');

        foreach ($qry->result_array() as $row)
        {
            $ignored_fields = ee()->publisher_setting->ignored_fields($row['site_id']);

            if (is_array($ignored_fields) && isset($ignored_fields[0]) && $ignored_fields[0] == '0')
            {
                ee()->db->where('site_id', $row['site_id'])
                        ->where('key', 'ignored_fields')
                        ->update('publisher_settings', array('val' => '[]'));
            }
        }

        ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_titles` ADD `hide_in_nav` char(1) DEFAULT 'n' AFTER `page_url`");
    }

    private function update_123()
    {
        ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_previews` ADD `override` text AFTER `custom`");
        ee()->db->query("ALTER TABLE `". ee()->db->dbprefix ."publisher_previews` ADD `route` int(4) NULL AFTER `override`");
    }

}
/* End of file upd.publisher.php */
/* Location: /system/expressionengine/third_party/publisher/upd.publisher.php */
