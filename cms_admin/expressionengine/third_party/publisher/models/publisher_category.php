<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Category Model Class
 *
 * @package     ExpressionEngine
 * @subpackage  Models
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

class Publisher_category extends Publisher_model
{
    public function __construct()
    {
        parent::__construct();

        $this->field_table = 'category_fields';
        $this->data_table_source = 'category_field_data';
        $this->data_table = 'publisher_categories';
    }

    /**
     * Get all categories assigned to an entry
     *
     * @param  integer $entry_id
     * @param  string  $status
     * @return array
     */
    public function get_category_posts($entry_id = NULL, $status = NULL)
    {
        $where = array(
            'entry_id' => ($entry_id ?: ee()->publisher_lib->entry_id),
            'publisher_status'   => ($status ?: ee()->publisher_lib->status),
            'publisher_lang_id'  => ee()->publisher_lib->lang_id
        );

        $qry = ee()->db->where($where)
                ->get('publisher_category_posts');

        if (ee()->publisher_setting->show_fallback() && $qry->num_rows() == 0)
        {
            $where['publisher_lang_id'] = ee()->publisher_lib->default_lang_id;

            $qry = ee()->db->where($where)
                    ->get('publisher_category_posts');

            if ($qry->num_rows() == 0)
            {
                $where['publisher_status'] = PUBLISHER_STATUS_OPEN;

                $qry = ee()->db->where($where)
                        ->get('publisher_category_posts');
            }
        }

        if ($qry->num_rows() == 0)
        {
            return array();
        }
        else
        {
            $cat_ids = array();

            foreach ($qry->result_array() as $row)
            {
                $cat_ids[] = $row['cat_id'];
            }

            return $cat_ids;
        }
    }

    /**
     * Save assigned categories to an entry
     *
     * @param  integer $entry_id
     * @param  array   $meta
     * @param  array   $data
     * @return void
     */
    public function save_category_posts($entry_id, $meta, $data)
    {
        $categories = array();

        // Channel:Form/Safecracker
        if (isset($data['categories']))
        {
            $categories = $data['categories'];
        }
        // Normal Entry Save
        elseif (isset($data['revision_post']['category']))
        {
            $categories = $data['revision_post']['category'];
        }

        $publisher_save_status = ee()->input->post('publisher_save_status');

        // Insert new categories.
        $this->_save_category_posts($entry_id, $publisher_save_status, $categories);

        // If option is enabled, and saving as open, delete drafts and save new draft rows too.
        if(
            $publisher_save_status == PUBLISHER_STATUS_OPEN &&
            ee()->publisher_setting->sync_drafts()
        ){
            $this->_save_category_posts($entry_id, 'draft', $categories);
        }

        // Get open records, and re-insert them into category_posts, otherwise
        // we get draft category assignments added to the core table.
        if(
            $publisher_save_status == PUBLISHER_STATUS_DRAFT &&
            ($categories = $this->get_category_posts($entry_id, PUBLISHER_STATUS_OPEN))
        ){
            // First delete all category assignments for this entry.
            ee()->db->where('entry_id', $entry_id)
                    ->delete('category_posts');

            foreach ($categories as $category)
            {
                $data = array(
                    'cat_id'    => $category,
                    'entry_id'  => $entry_id
                );

                $qry = ee()->db->where($data)->get('category_posts');

                if ($qry->num_rows() == 0)
                {
                    ee()->db->insert('category_posts', $data);
                }
            }
        }
    }

    private function _save_category_posts($entry_id, $publisher_save_status, $categories = array())
    {
        // First delete all category assignments for this entry.
        ee()->db->where('publisher_status', $publisher_save_status)
                ->where('publisher_lang_id', ee()->publisher_lib->lang_id)
                ->where('entry_id', $entry_id)
                ->delete('publisher_category_posts');

        /*
        TODO? - On save, see if the entry has a row for the requested status/lang_id in publisher_titles
        then we don't update the categories for that entry.

        if no rows exist, then get the categories for the default language, then insert them for the
        requested status/lang_id so it has records, thus sharing the same cats as the default lang.
        requires new loop over each language with the same insert array.
         */

        if ( !empty($categories))
        {
            foreach ($categories as $cat_id)
            {
                $data = array(
                    'cat_id'            => $cat_id,
                    'entry_id'          => $entry_id,
                    'publisher_status'  => $publisher_save_status,
                    'publisher_lang_id' => ee()->publisher_lib->lang_id
                );

                ee()->db->insert('publisher_category_posts', $data);
            }
        }
    }

    /**
     * Handle multi_entry_category_update. There is no hook
     * in EE so we only trigger this if the $_POST is correct.
     *
     * Thanks to Mark Croxton for contributing this.
     *
     * @return void
     */
    public function multi_entry_category_update()
    {
        // Does the user have permission?
        if ( !ee()->publisher_helper->allowed_group('can_access_content'))
        {
            show_error(lang('unauthorized_access'));
        }

        $entries   = ee()->input->post('entry_ids', TRUE);
        $cat_ids   = ee()->input->post('category', TRUE);
        $type      = ee()->input->post('type', TRUE);
        $entry_ids = array();

        if ( !$entries || !$type)
        {
            show_error(lang('unauthorized_to_edit'));
        }

        if ( !$cat_ids || !is_array($cat_ids) || empty($cat_ids))
        {
            return ee()->output->show_user_error('submission', lang('no_categories_selected'));
        }

        // For the entries affected, sync publisher_category_posts to category_posts

        foreach (explode('|', trim($entries)) as $entry_id)
        {
            $entry_ids[] = $entry_id;
        }

        // default states
        $default_language_id  = ee()->publisher_model->default_language_id;;
        $default_view_status  = ee()->publisher_setting->default_view_status();
        $states = array();

        foreach($entry_ids as $entry_id)
        {
            // we'll always have a state for the default language and status
            $states = array(array(
                'publisher_lang_id' => $default_language_id,
                'publisher_status'  => $default_view_status
            ));

            if ($type == 'add')
            {
                // Adding categories
                // ----------------------------------------------------------------

                // We want to add categories to all the open and draft versions
                // of an entry without changing existing category selections

                // for each entry, look up existing distinct states
                $query = ee()->db->distinct()
                                 ->select('publisher_lang_id, publisher_status')
                                 ->where('entry_id', $entry_id)
                                 ->get('publisher_titles');

                if ($query->num_rows() > 0)
                {
                    $result = $query->result_array();

                    foreach($result as $row)
                    {
                        if (FALSE === ($row['publisher_lang_id'] == $default_language_id && $row['publisher_status'] == $default_view_status))
                        {
                            $states[] = array(
                                'publisher_lang_id' => $row['publisher_lang_id'],
                                'publisher_status'  => $row['publisher_status']
                            );
                        }
                    }
                }

                // build an an array of records to insert into publisher_category_posts
                $data = array();

                foreach($states as $state)
                {
                    // add the new categories
                    foreach($cat_ids as $cat_id)
                    {
                        $data[] = array(
                            'entry_id'          => $entry_id,
                            'cat_id'            => $cat_id,
                            'publisher_lang_id' => $state['publisher_lang_id'],
                            'publisher_status'  => $state['publisher_status']
                        );
                    }
                }

                // delete any relationships with the newly added categories that already exist
                // for this entry so that we don't end up with duplicate rows
                ee()->db->where('entry_id', $entry_id)
                        ->where_in('cat_id', $cat_ids)
                        ->delete('publisher_category_posts');

                // (re)insert the categories with the appropriate states
                ee()->db->insert_batch('publisher_category_posts', $data);
            }

            elseif($type == 'remove')
            {
                // Removing categories
                // ----------------------------------------------------------------

                // we're simply removing the selected categories from all versions of the entry
                ee()->db->where('entry_id', $entry_id)
                        ->where_in('cat_id', $cat_ids)
                        ->delete('publisher_category_posts');
            }
        }
    }


    //--------------------------------------------------------------------------------
    // MCP Methods
    //--------------------------------------------------------------------------------

    /**
     * Get a specific category, and optionally all translations for it.
     *
     * @param  integer - Category ID
     * @param  string - Status
     * @param  boolean - Translations?
     * @return array
     */
    public function get($cat_id = FALSE, $status = PUBLISHER_STATUS_OPEN, $translations = FALSE)
    {
        if ( !$cat_id)
        {
            show_error('$cat_id is required. publisher_category.php->get()');
        }

        if ($translations)
        {
            // TODO: turns out I never used this... Doesn't exist.
            return $this->get_category_translations($cat_id);
        }
        else
        {
            $qry = ee()->db->select('c.*, uc.*, c.cat_id as cat_id')
                                ->from('publisher_categories AS uc')
                                ->join('categories AS c', 'c.cat_id = uc.cat_id')
                                ->where('c.cat_id', $cat_id)
                                ->where('c.site_id', ee()->publisher_lib->site_id)
                                ->get();

            return $qry->row() ?: FALSE;
        }
    }

    /**
     * Get all categories optionally by group with no translations, just category data.
     *
     * @param  integer - Group ID
     * @param  srtring - Status
     * @return array
     */
    public function get_all($group_id = FALSE, $status = PUBLISHER_STATUS_OPEN)
    {
        ee()->db->where('site_id', ee()->publisher_lib->site_id);

        if (is_numeric($group_id))
        {
            ee()->db->where('group_id', $group_id);
        }

        $qry = ee()->db
                   ->order_by('cat_name', 'asc')
                   ->get('categories');

        $categories = array();

        foreach ($qry->result() as $category)
        {
            $category->translation_status = $this->is_translated_formatted($category->cat_id, ee()->publisher_setting->detailed_translation_status());

            $categories[] = $category;
        }

        return !empty($categories) ? $categories : FALSE;
    }

    /**
     * Grab specific category group, and optionally return only the group ID
     *
     * @param  integer - Group ID
     * @param  boolean - Return the group ID only
     * @return array
     */
    public function get_group($group_id = FALSE, $return_id = FALSE)
    {
        // If no category group was specified, get the first one found.
        // This is used in the CP Category management landing page.
        if (is_numeric($group_id))
        {
            $qry = ee()->db->where('group_id', $group_id)
                                ->get('category_groups');
        }
        else
        {
            $qry = ee()->db->limit(1)->get('category_groups');
        }

        return $qry->num_rows() ? ($return_id ? $qry->row('group_id') : $qry->row()) : FALSE;
    }

    /**
     * Grab all category groups for the current site
     *
     * @return array
     */
    public function get_groups()
    {
        $qry = ee()->db->where('site_id', ee()->publisher_lib->site_id)
                       ->order_by('group_name', 'asc')
                       ->get('category_groups');

        $groups = array();

        if ($qry->num_rows())
        {
            foreach ($qry->result() as $group)
            {
                $groups[$group->group_id] = $group;
            }
        }

        return $groups;
    }

    /**
     * Get all groups and categories in each group
     *
     * @return mixed
     */
    public function get_by_group($formatted = FALSE)
    {
        $groups = $this->get_groups();
        $categories = $this->get_all();
        $grouped = array();

        foreach ($groups as $group_id => $group)
        {
            $grouped[$group_id] = array(
                'value' => $group_id,
                'label' => $group->group_name
            );

            foreach ($categories as $index => $category)
            {
                if ($category->group_id == $group_id)
                {
                    $grouped[$group_id]['categories'][] = array(
                        'value' => $category->cat_id,
                        'label' => $category->cat_name
                    );
                }
            }
        }

        if ( !$formatted)
        {
            return $grouped;
        }

        // Leave a blank option other wise the on change event won't work
        $out = '<option value=""></option>';

        foreach ($grouped as $group_index => $group)
        {
            $out .= '<optgroup label="'. $group['label'] .'">';

            if ( !isset($group['categories'])) continue;

            foreach ($group['categories'] as $category_index => $category)
            {
                $url = ee()->publisher_helper_cp->mod_link('categories', array('group_id' => $group['value'] .'#category-'. $category['value']));

                $out .= '<option value="'. $url .'">'. $category['label'] .'</option>';
            }

            $out .= '</optgroup>';
        }

        return $out;
    }

    /**
     * MCP ONLY Get all the translations for a category, and optionally return only a specific lang_id.
     * @param   integer - Category ID
     * @param   string - Status
     * @param   integer - Language ID
     * @return  boolean
     */
    public function get_translations($cat_id = FALSE, $group_id = FALSE, $status = PUBLISHER_STATUS_OPEN, $lang_id = FALSE)
    {
        if ( !$cat_id)
        {
            show_error('$cat_id is required. publisher_category.php->get_translations()');
        }

        if ( !$group_id)
        {
            show_error('$group_id is required. publisher_category.php->get_translations()');
        }

        $categories = array();
        $translations = array();

        $where = array(
            'publisher_status'  => $status,
            'cat_id'            => $cat_id,
            'site_id'           => ee()->publisher_lib->site_id
        );

        $qry = ee()->db->from('publisher_categories')
                            ->where($where)
                            ->get();

        foreach ($qry->result() as $row)
        {
            $categories[$row->publisher_lang_id] = $row;
        }

        if ($lang_id)
        {
            $translations[$lang_id] = isset($categories[$lang_id]) ? $categories[$lang_id] : $categories[ee()->publisher_lib->default_lang_id];
        }
        else
        {
            $fields = $this->get_custom_fields($group_id);

            $field_select_default = array();

            foreach ($fields as $name => $field)
            {
                if (preg_match('/field_id_(\d+)/', $name, $matches))
                {
                    $field_select_default[] = 'cfd.'. $name;
                }
            }

            foreach ($this->get_enabled_languages() as $lid => $language)
            {
                // If we have existing category data
                if (isset($categories[$lid]))
                {
                    $translations[$lid] = $categories[$lid];
                }
                // If the language ID in the loop is what our current default lang is
                elseif ($lid == ee()->publisher_lib->default_lang_id)
                {
                    $default_qry = ee()->db->select('c.*, '. implode(',', $field_select_default))
                                    ->from('categories AS c')
                                    ->join('category_field_data AS cfd', 'cfd.cat_id = c.cat_id', 'left')
                                    ->where('c.cat_id', $cat_id)
                                    ->where('c.site_id', ee()->publisher_lib->site_id)
                                    ->get();

                    $default_category = (object) array();

                    // Kind of silly, but NULL values in the DB don't work when accessing
                    // the property value, e.g. $category->$field_name will throw an error.
                    foreach ($default_qry->row() as $field => $value)
                    {
                        $default_category->$field = $value === NULL ? '' : $value;
                    }

                    $translations[$lid] = $default_category;
                }
                // The category has not been translated yet, so create the vars
                // with an empty translation value so the view doesn't bomb.
                else
                {
                    $categories[$lid] = new stdClass();

                    // Make sure our object has the same properties, but blank,
                    // as a translated entry.
                    foreach ($fields as $file_name => $field_data)
                    {
                        $categories[$lid]->$file_name = '';
                    }

                    $categories[$lid]->cat_id = $cat_id;
                    $categories[$lid]->publisher_lang_id = $lid;

                    $translations[$lid] = $categories[$lid];
                }

                $translations[$lid]->text_direction = $this->get_language($lid, 'direction');
            }
        }

        return $translations;
    }

    /**
     * Get the categories for the current language
     * @param  int $by_group optional, get a specific category group
     * @return array
     */
    public function get_current($by_group = FALSE)
    {
        if ( !ee()->publisher_setting->enabled())
        {
            return array();
        }

        // @todo - cache
        if(FALSE)
        {

        }
        else if (isset(ee()->session->cache['publisher']['all_categories']))
        {
            if ($by_group)
            {
                return ee()->session->cache['publisher']['all_categories'][$by_group];
            }
            else
            {
                return ee()->session->cache['publisher']['all_categories'];
            }
        }
        else
        {
            // Grab the custom fields. We don't need field_id_N in our templates
            $fields = $this->get_custom_fields();

            $field_select_default = array();
            $field_select_custom = array();

            foreach ($fields as $name => $field)
            {
                if (preg_match('/field_id_(\d+)/', $name, $matches))
                {
                    $field_select_default[] = 'cfd.'. $name .' AS \''. $field->field_name.'\'';
                    $field_select_custom[] = $name .' AS \''. $field->field_name.'\'';
                }
            }

            $qry = ee()->db->select('c.*, '. implode(',', $field_select_default))
                                ->from('categories AS c')
                                ->join('category_field_data AS cfd', 'cfd.cat_id = c.cat_id', 'left')
                                ->where('c.site_id', ee()->publisher_lib->site_id)
                                ->get();

            ee()->session->cache['publisher']['all_categories'] = array();
            ee()->session->cache['publisher']['translated_categories'] = array();

            foreach ($qry->result_array() as $category)
            {
                ee()->session->cache['publisher']['all_categories'][$category['group_id']][$category['cat_id']] = $category;
            }

            $where = array(
                'publisher_lang_id' => ee()->publisher_lib->lang_id,
                'publisher_status'  => ee()->publisher_lib->status,
                'site_id'           => ee()->publisher_lib->site_id
            );

            $qry = ee()->db->select('*, '. implode(',', $field_select_custom))
                                ->from('publisher_categories')
                                ->where($where)
                                ->get();

            foreach ($qry->result_array() as $category)
            {
                ee()->session->cache['publisher']['translated_categories'][$category['group_id']][$category['cat_id']] = $category;
            }

            foreach (ee()->session->cache['publisher']['all_categories'] as $group_id => $group)
            {
                foreach ($group as $cat_id => $category)
                {
                    if (isset(ee()->session->cache['publisher']['translated_categories'][$group_id][$cat_id]))
                    {
                        foreach (ee()->session->cache['publisher']['translated_categories'][$group_id][$cat_id] as $field_name => $field_value)
                        {
                            if ($field_value != '')
                            {
                                ee()->session->cache['publisher']['all_categories'][$group_id][$cat_id][$field_name] = $field_value;
                            }
                        }
                    }
                }
            }

            foreach (ee()->session->cache['publisher']['all_categories'] as $group_id => $group)
            {
                foreach ($group as $cat_id => $category)
                {
                    foreach ($category as $field => $value)
                    {
                        ee()->session->cache['publisher']['all_categories'][$group_id][$cat_id][str_replace('cat_', 'category_', $field)] = $value;
                    }
                }
            }

            if ($by_group)
            {
                return ee()->session->cache['publisher']['all_categories'][$by_group];
            }
            else
            {
                return ee()->session->cache['publisher']['all_categories'];
            }
        }
    }

    /**
     * Get first category group
     *
     * @return integer
     */
    public function get_first_group()
    {
        return ee()->db->select('MIN(group_id) AS group_id')
                   ->get('category_groups')
                   ->row('group_id');
    }

    /**
     * Grab all the custom fields for a category
     *
     * @return array
     */
    public function get_custom_fields($group_id = FALSE)
    {
        // Default fields
        $fields = array(
            // Return the Image field first so we can float it in the view.
            'cat_image' => (object) array(
                'field_name'  => 'cat_image',
                'field_label' => 'Image'
            ),
            'cat_name' => (object) array(
                'field_name'  => 'cat_name',
                'field_label' => 'Name'
            ),
            'cat_description' => (object) array(
                'field_name'  => 'cat_description',
                'field_label' => 'Description'
            ),
            'cat_url_title'   => (object) array(
                'field_name'  => 'cat_url_title',
                'field_label' => 'URL Title'
            ),
        );

        // Grab the columns from the table
        $columns = $this->get_table_columns($this->data_table);

        // Grab the custom field data
        $field_data = array();

        if ($group_id)
        {
            ee()->db->where('group_id', $group_id);
        }

        $qry = ee()->db->get('category_fields');

        foreach ($qry->result() as $row)
        {
            $field_data[$row->field_id] = $row;
        }

        foreach ($columns as $column_name)
        {
            if (substr($column_name, 0, 9) == 'field_id_')
            {
                $id = preg_replace('/field_id_(\d+)/', "$1", $column_name);

                if (array_key_exists($id, $field_data))
                {
                    $fields[$column_name] = $field_data[$id];
                }
            }
        }

        return $fields;
    }

    /**
     * Save a category and all entered translations
     *
     * @return  boolean
     */
    public function save_translation($translation, $status)
    {
        // -------------------------------------------
        //  'publisher_category_save_start' hook
        //
            if (ee()->extensions->active_hook('publisher_category_save_start'))
            {
                $translation = ee()->extensions->call('publisher_category_save_start', $translation, $status);
            }
        //
        // -------------------------------------------

        ee()->load->model('publisher_approval_category');

        // Make sure we have custom fields first, or the update below will bomb.
        $has_custom_fields = ee()->db->count_all_results($this->field_table);

        foreach ($translation as $cat_id => $data)
        {
            $qry = ee()->db->select('group_id')
                                ->where('cat_id', $cat_id)
                                ->get('categories');

            foreach ($data as $lang_id => $fields)
            {
                $default_cat_data = array();
                $default_field_data = array();

                $publisher_data = array(
                    'site_id'           => ee()->publisher_lib->site_id,
                    'group_id'          => $qry->row('group_id'),
                    'cat_id'            => $cat_id,
                    'publisher_lang_id' => $lang_id,
                    'publisher_status'  => $status,
                    'edit_date'         => ee()->localize->now,
                    'edit_by'           => ee()->session->userdata['member_id']
                );

                $where = array(
                    'site_id'           => ee()->publisher_lib->site_id,
                    'cat_id'            => $cat_id,
                    'publisher_lang_id' => $lang_id,
                    'publisher_status'  => $status,
                    'site_id'           => ee()->publisher_lib->site_id
                );

                foreach ($fields as $field_name => $field_value)
                {
                    // cat_image field saves default of 0 if there is no image. Lame.
                    if ($field_name == 'cat_image' AND $field_value == '')
                    {
                        $field_value = 0;
                    }

                    $publisher_data[$field_name] = $field_value;

                    // Grab the default fields, should be cat_name, cat_description, and cat_image
                    if (substr($field_name, 0, 4) == 'cat_')
                    {
                        $default_cat_data[$field_name] = $field_value;
                    }
                    else
                    {
                        $default_field_data[$field_name] = $field_value;
                    }
                }

                $result = $this->insert_or_update($this->data_table, $publisher_data, $where, 'cat_id');

                // Now update the core EE category tables, but only if we save as published/open
                if ($lang_id == ee()->publisher_lib->default_lang_id)
                {
                    if ($status == PUBLISHER_STATUS_OPEN)
                    {
                        // Remove an existing approval. If its saved as open, there is nothing to approve.
                        ee()->publisher_approval_category->delete($cat_id, $lang_id);

                        // Remove publisher columns, otherwise we get an error.
                        unset($where['id']);
                        unset($where['publisher_lang_id']);
                        unset($where['publisher_status']);

                        $this->insert_or_update('categories', $default_cat_data, $where, 'cat_id');

                        if ($has_custom_fields && !empty($default_field_data))
                        {
                            $this->insert_or_update($this->data_table_source, $default_field_data, $where, 'cat_id');
                        }
                    }
                    else
                    {
                        // If the user is not a Publisher, send approvals.
                        ee()->publisher_approval_category->save($cat_id, $publisher_data);
                    }
                }

                // Just like entries, save as Draft if we're syncing.
                if ($status == PUBLISHER_STATUS_OPEN AND ee()->publisher_setting->sync_drafts() AND !ee()->publisher_setting->disable_drafts())
                {
                    $where['publisher_status'] = PUBLISHER_STATUS_DRAFT;
                    $publisher_data['publisher_status'] = PUBLISHER_STATUS_DRAFT;

                    $this->insert_or_update($this->data_table, $publisher_data, $where, 'cat_id');
                }

                if ( !$result)
                {
                    return $result;
                }
            }
        }

        // -------------------------------------------
        //  'publisher_category_save_end' hook
        //
            if (ee()->extensions->active_hook('publisher_category_save_end'))
            {
                ee()->extensions->call('publisher_category_save_end', $translation, $status);
            }
        //
        // -------------------------------------------

        return TRUE;
    }

    public function is_translated($cat_id, $detailed = FALSE)
    {
        $languages = ee()->publisher_model->get_enabled_languages();
        $languages_enabled = array_keys($languages);

        $is_translated = FALSE;

        $default_qry = ee()->db->select('c.*, cfd.*')
                        ->from('categories AS c')
                        ->join('category_field_data AS cfd', 'cfd.cat_id = c.cat_id', 'left')
                        ->where('c.cat_id', $cat_id)
                        ->where('c.site_id', ee()->publisher_lib->site_id)
                        ->get();

        $default = $default_qry->row();

        $return = array();
        $return_boolean = TRUE;

        foreach ($languages_enabled as $lang_id)
        {
            $qry = ee()->db->where('cat_id', $cat_id)
                    ->where('publisher_status', PUBLISHER_STATUS_OPEN)
                    ->where_in('publisher_lang_id', $lang_id)
                    ->get($this->data_table);

            // A newly created category, no translations at all yet, thus no rows.
            if ( !$qry->num_rows())
            {
                $return[] = 'incomplete_'.strtoupper($languages[$lang_id]['short_name']);
            }
            // Existing category, check each custom field, if any one of them are
            // blank consider it in complete, then bust out of the loop b/c thats all we need.
            else
            {
                foreach ($qry->result() as $row)
                {
                    foreach ($default as $field_name => $field_value)
                    {
                        if (isset($row->$field_name) AND $row->$field_name == '' AND $field_value != '')
                        {
                            $return_boolean = FALSE;
                            $return[] = 'incomplete_'.strtoupper($languages[$lang_id]['short_name']);
                            break;
                        }
                    }
                }

                if ( !in_array('incomplete_'.strtoupper($languages[$lang_id]['short_name']), $return))
                {
                    $return[] = 'complete_'.strtoupper($languages[$lang_id]['short_name']);
                }
            }
        }

        return $detailed ? $return : $return_boolean;
    }

    public function is_translated_formatted($cat_id, $detailed = FALSE)
    {
        $status = $this->is_translated($cat_id, $detailed);

        if ($status === TRUE)
        {
            $return = '<span class="publisher-translation-status publisher-translation-status-complete">'. lang('publisher_translation_complete') .'</span>';
        }
        elseif ($status === FALSE)
        {
            $return = '<span class="publisher-translation-status publisher-translation-status-incomplete">'. lang('publisher_translation_incomplete') .'</span>';
        }
        else
        {
            $return = '';

            foreach ($status as $stat)
            {
                $short_name = explode('_', $stat, 2);
                $return .= '<span class="publisher-translation-status publisher-translation-status-'. $short_name[0] .'">'. $short_name[1] .'</span>';
            }

        }

        return $return;
    }

    /**
     * See if a draft exists for a requested category
     * @param  array  $data         Array of data about the category
     * @param  boolean $language_id
     * @return boolean
     */
    public function has_draft($cat_id, $language_id = FALSE)
    {
        // Get by current language or a requested one?
        $language_id = $language_id ?: ee()->publisher_lib->lang_id;

        $where = array(
            'cat_id'         => $cat_id,
            'publisher_status'  => PUBLISHER_STATUS_DRAFT,
            'publisher_lang_id' => $language_id
        );

        $qry = ee()->db->select('edit_date')->get_where($this->data_table, $where);

        // If we have a draft, see if its newer than the open version.
        if ($qry->num_rows())
        {
            $draft_date = $qry->row('edit_date');

            $where = array(
                'cat_id'            => $cat_id,
                'publisher_status'  => PUBLISHER_STATUS_OPEN,
                'publisher_lang_id' => $language_id
            );

            $qry = ee()->db->select('edit_date')->get_where($this->data_table, $where);

            $open_date = $qry->row('edit_date') ?: 0;

            if ($draft_date > $open_date)
            {
                return TRUE;
            }
        }
    }

    /**
     * Search by a translated url_title, and return the default language version of it.
     *
     * @param  string $url_title
     * @return string
     */
    public function get_default_url_title($url_title, $return = 'url_title')
    {
        // First see if its a Cat URL Indicator translation
        if ($seg = $this->_get_default_cat_url_indicator($url_title))
        {
            return $seg;
        }

        $cache_key = 'get_default_url_title/cat'. $url_title .'/'. $return;

        // Make sure this query run as few times as possible
        if ( !isset(ee()->session->cache['publisher'][$cache_key]))
        {
            $qry = ee()->db->select('cat_id')
                    ->where('cat_url_title', $url_title)
                    ->where('publisher_lang_id', ee()->publisher_lib->lang_id)
                    ->where('publisher_status', ee()->publisher_lib->status)
                    ->get('publisher_categories');

            if ($qry->num_rows() == 1)
            {
                $cat_id = $qry->row('cat_id');

                if ($return == 'cat_id')
                {
                    ee()->session->cache['publisher'][$cache_key] = $cat_id;
                }
                else
                {
                    $qry = ee()->db->select('cat_url_title')
                                        ->where('cat_id', $cat_id)
                                        ->get('categories');

                    $url_title = $qry->num_rows() ? $qry->row('cat_url_title') : $url_title;

                    ee()->session->cache['publisher'][$cache_key] = $url_title;
                }
            }
        }

        if (isset(ee()->session->cache['publisher'][$cache_key]))
        {
            return ee()->session->cache['publisher'][$cache_key];
        }

        return ($return === FALSE) ? FALSE : $url_title;
    }

    /**
     * Search for a default url_title, and return the translated version of it.
     *
     * @param  string $url_title
     * @return string
     */
    public function get_translated_url_title($url_title, $lang_id_from = NULL, $lang_id_to = NULL)
    {
        $lang_id_from = $lang_id_from ?: ee()->publisher_lib->prev_lang_id;
        $lang_id_to   = $lang_id_to   ?: ee()->publisher_lib->lang_id;

        // If we're in the middle of a language switch, I don't care what was passed to this method.
        if (ee()->publisher_lib->switching)
        {
            $lang_id_from = ee()->publisher_lib->prev_lang_id;
        }

        $key = $lang_id_from.':'.$lang_id_to;

        if ( !isset($this->cache['translated_cat_url_title'][$url_title][$key]))
        {
            // Is it a Cat URL Title indicator?
            if ($seg = $this->_get_translated_cat_url_indicator($url_title, $lang_id_from, $lang_id_to))
            {
                $this->cache['translated_cat_url_title'][$seg][$key] = $seg;

                return $seg;
            }

            $qry = ee()->db->select('cat_id')
                    ->where('cat_url_title', $url_title)
                    ->where('publisher_lang_id', $lang_id_from)
                    ->where('publisher_status', ee()->publisher_lib->status)
                    ->get('publisher_categories');

            if ($qry->num_rows() == 1)
            {
                $cat_id = $qry->row('cat_id');

                $qry = ee()->db->select('cat_url_title')
                                    ->where('cat_id', $cat_id)
                                    ->where('publisher_lang_id', $lang_id_to)
                                    ->where('publisher_status', ee()->publisher_lib->status)
                                    ->get('publisher_categories');

                $url_title = $qry->num_rows() ? $qry->row('cat_url_title') : $url_title;

                $this->cache['translated_cat_url_title'][$url_title][$key] = $url_title;

                return $url_title;
            }
        }

        $this->cache['translated_cat_url_title'][$url_title][$key] = $url_title;

        return $url_title;
    }

    /**
     * Search for the default Cat URL Indicator segment based on the
     * currently translated segment.
     * @param  string $url_title Translated segment
     * @return mixed             Default segment, or FALSE if default not found
     */
    private function _get_default_cat_url_indicator($url_title)
    {
        foreach (ee()->publisher_model->languages as $lang_id => $language)
        {
            if ($language['cat_url_indicator'] == $url_title)
            {
                return ee()->publisher_model->languages[ee()->publisher_lib->default_lang_id]['cat_url_indicator'];
            }
        }

        return FALSE;
    }

    /**
     * Search for the translated version of a Cat URL Indicator segment. Takes
     * a segment, and sees if it exists in the languages array. If it does,
     * return the translated version of it from the requested language
     * @param  string $str          Translated segment
     * @param  integer $lang_id_from
     * @param  integer $lang_id_to
     * @return mixed                Translated segment, or FALSE if not found
     */
    private function _get_translated_cat_url_indicator($str, $lang_id_from = NULL, $lang_id_to = NULL)
    {
        $from_language = ee()->publisher_model->languages[$lang_id_from];
        $to_language   = ee()->publisher_model->languages[$lang_id_to];

        if ($from_language['cat_url_indicator'] == $str)
        {
            return $to_language['cat_url_indicator'];
        }

        return FALSE;
    }

    /**
     * Get the current cateogry reserved category word.
     * @return string
     */
    public function get_cat_url_indicator()
    {
        if(isset(ee()->publisher_model->current_language['cat_url_indicator']) &&
            ee()->publisher_model->current_language['cat_url_indicator'] != '')
        {
            return ee()->publisher_model->current_language['cat_url_indicator'];
        }

        return ee()->config->item('reserved_category_word');
    }

    /**
     * See if a translation exists for the requested category
     * @param  array  $data         Array of data about the category
     * @param  int    $language_id
     * @return boolean
     */
    public function has_translation($data, $language_id = FALSE)
    {
        return TRUE;
    }

    /**
     * After installation migrate all existing data to the Publisher tables
     * @return void
     */
    public function migrate_data($entry_ids = array(), $where = array(), $delete_old = FALSE)
    {
        // Delete old rows. delete() resets the where(), so reset them.
        if ($delete_old && !empty($where) && !empty($entry_ids))
        {
            ee()->db->where_in('entry_id', $entry_ids)
                ->delete('publisher_category_posts');
        }

        if ( !empty($entry_ids))
        {
            ee()->db->where_in('entry_id', $entry_ids);
        }

        $qry = ee()->db->get('category_posts');

        if ($qry->num_rows())
        {
            foreach ($qry->result_array() as $row)
            {
                $data = $row;
                $data['publisher_lang_id']   = $this->default_language_id;
                $data['publisher_status']    = ee()->publisher_setting->default_view_status();

                $this->insert_or_update('publisher_category_posts', $data, $row, 'cat_id');
            }
        }
    }

    /**
     * @return void
     */
    public function uninstall_data()
    {
        // Don't need this for categories, it always keeps the latest
        // live default language version in the native EE category tables.
    }
}