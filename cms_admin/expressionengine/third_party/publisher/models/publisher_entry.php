<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Model Class
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

class Publisher_entry extends Publisher_model
{
    /**
     * Default values for the publisher_data table columns.
     * Array is populated by the post and meta data for
     * updating of the Publisher tables.
     * @var array
     */
    public $data_columns = array(
        'site_id'                   => 1,
        'entry_id'                  => 0,
        'publisher_lang_id'         => 1,
        'publisher_status'          => PUBLISHER_STATUS_OPEN,
        'publisher_approval_status' => 'approved'
    );

    /**
     * Default values for the publisher_titles table columns.
     * Array is populated by the post and meta data for
     * updating of the Publisher tables.
     * @var array
     */
    public $title_columns = array(
        'entry_date' => NULL,
        'edit_date'  => NULL,
        'edit_by'    => NULL
    );

    /**
     * Collects the posted field data for updating Publisher tables.
     * @var array
     */
    public $post_data = array();

    /**
     * Collects the posted entry meta data for updating Publisher tables.
     * @var array
     */
    public $meta_data = array();

    /**
     * Is the current channel:entries tag in pagination mode?
     * @var boolean
     */
    public $is_paginating = FALSE;

    public function __construct()
    {
        parent::__construct();

        $this->field_table = 'channel_fields';
        $this->data_table_source = 'channel_data';
        $this->title_table = 'publisher_titles';
        $this->data_table = 'publisher_data';

        // Title columns also have the same columns as $data_columns
        $this->title_columns = array_merge($this->data_columns, $this->title_columns);

        // Required to make pagination work when Persistent Entries is turned off
        if ( !ee()->publisher_setting->persistent_entries())
        {
            ee()->db->save_queries = TRUE;
        }
    }

    /**
     * Prepare the entry data before saving
     *
     * @param  int $entry_id
     * @param  array $meta
     * @param  array $data
     * @return void
     */
    public function prepare($entry_id, $meta, $data)
    {
        // -------------------------------------------
        //  'publisher_entry_save_start' hook
        //
            if (ee()->extensions->active_hook('publisher_entry_save_start'))
            {
                list($meta, $data) = ee()->extensions->call('publisher_entry_save_start', $entry_id, $meta, $data);
            }
        //
        // -------------------------------------------

        $page_url = '';
        $hide_in_nav = 'n';
        $template_id = 0;
        $parent_id = 0;
        $channel_id = ee()->input->post('channel_id');
        $page_data = array();

        // If Structure is installed, look for it first.
        if (ee()->publisher_site_pages->is_installed('structure'))
        {
            $page_url = ee()->input->post('structure__uri') ?: '';
            $template_id = ee()->input->post('structure__template_id') ?: $template_id;
            $parent_id = ee()->input->post('structure__parent_id') ?: $parent_id;
            $hide_in_nav = ee()->input->post('structure__hidden') ?: $hide_in_nav;
        }
        else if (ee()->publisher_site_pages->is_installed('pages'))
        {
            // Fetch our default template assigned to this channel incase someone hid the template field
            $page_url = ee()->input->post('pages__pages_uri') ?: '';
            $template_id = ee()->input->post('pages__pages_template_id') ?: ee()->publisher_template->get_pages_default_template($channel_id);

            // If they try to save the entry with no value, it'll be saved as the value in the
            // language file, e.g. /example/pages/uri/, which will mess up subsequent saves.
            if ($page_url == lang('example_uri')) {
                $page_url = '';
            }

            // Merge the data in so publisher_site_pages knows what to do.
            $page_data = array(
                'pages_template_id' => $template_id,
                'pages_uri' => $page_url
            );

            // Make sure it always has a prefixing /
            $page_url = ee()->publisher_helper->add_prefix($page_url, '/');
        }

        // Make sure we get the $meta and $post data from EE
        $this->post_data = array_merge($data, $page_data);
        $this->meta_data = $meta;

        // Take the POST lang_id incase the user has the same entry open in multiple tabs, other wise it'll save
        // based on session lang_id and not posted, then data will get crossed. Don't cross the streams!
        $lang_id = ee()->input->post('site_language') ?: ee()->publisher_lib->lang_id;

        // For cross site MSM posting
        $site_id = ee()->input->post('site_id') ?: ee()->config->item('site_id');

        $this->title_columns['entry_id']                    = $entry_id;
        $this->title_columns['site_id']                     = $site_id;
        $this->title_columns['channel_id']                  = $channel_id;
        $this->title_columns['publisher_status']            = ee()->input->post('publisher_save_status');
        $this->title_columns['publisher_lang_id']           = $lang_id;
        $this->title_columns['publisher_approval_status']   = 'approved';
        $this->title_columns['entry_date']                  = $meta['entry_date'];
        $this->title_columns['edit_date']                   = date("YmdHis");
        $this->title_columns['edit_by']                     = ee()->session->userdata('member_id');
        $this->title_columns['title']                       = ee()->input->post('title');
        $this->title_columns['url_title']                   = $this->create_url_title(ee()->input->post('url_title'), $entry_id, TRUE, $site_id);
        $this->title_columns['page_url']                    = $page_url;
        $this->title_columns['hide_in_nav']                 = $hide_in_nav;
        $this->title_columns['template_id']                 = $template_id;
        $this->title_columns['parent_id']                   = $parent_id;

        $this->data_columns['site_id']                      = $this->title_columns['site_id'];
        $this->data_columns['entry_id']                     = $this->title_columns['entry_id'];
        $this->data_columns['channel_id']                   = $this->title_columns['channel_id'];
        $this->data_columns['publisher_lang_id']            = $this->title_columns['publisher_lang_id'];
        $this->data_columns['publisher_status']             = $this->title_columns['publisher_status'];
        $this->data_columns['publisher_approval_status']    = $this->title_columns['publisher_approval_status'];

        $this->where['site_id']                             = $this->title_columns['site_id'];
        $this->where['entry_id']                            = $this->title_columns['entry_id'];
        $this->where['publisher_lang_id']                   = $this->title_columns['publisher_lang_id'];
        $this->where['publisher_status']                    = $this->title_columns['publisher_status'];
    }

    /**
     * Save the entry into our publisher_data table
     *
     * @return void
     */
    public function save()
    {
        $publisher_save_status = ee()->publisher_lib->publisher_save_status;
        $save_language = ee()->publisher_lib->lang_id;

        // Make sure all column names follow field_id_x format
        $this->data_columns = $this->transpose_column_names($this->data_columns);

        // "title" fields in Grid somehow get added to this array,
        // even if I ignore Grid fields in publisher_lib.
        if (isset($this->data_columns['title']))
        {
            unset($this->data_columns['title']);
        }

        $this->insert_or_update('publisher_titles', $this->title_columns, $this->where);
        $this->insert_or_update('publisher_data', $this->data_columns, $this->where);

        ee()->load->model('publisher_approval_entry');

        if ($publisher_save_status == PUBLISHER_STATUS_OPEN)
        {
            // If option is enabled, and saving as open, update the draft versions too so they are in sync.
            if (ee()->publisher_setting->sync_drafts() && !ee()->publisher_setting->disable_drafts())
            {
                // Delete an the approval if it exists, b/c we just saved as open, thus whatever was pending approval is now live.
                ee()->publisher_approval_entry->delete(ee()->publisher_lib->entry_id);

                $this->where['publisher_status'] = PUBLISHER_STATUS_DRAFT;
                $this->data_columns['publisher_status'] = PUBLISHER_STATUS_DRAFT;
                $this->title_columns['publisher_status'] = PUBLISHER_STATUS_DRAFT;

                $this->insert_or_update('publisher_titles', $this->title_columns, $this->where);
                $this->insert_or_update('publisher_data', $this->data_columns, $this->where);
            }
        }
        else
        {
            // If the user is not a Publisher, send approvals.
            ee()->publisher_approval_entry->save(ee()->publisher_lib->entry_id, $this->meta_data, $this->post_data);
        }

        // Post submit cleanup. Depending on the case we need to add 1 of 2 values to the channel_data
        //  blank = if the field doesn't have data, conditionals need to return false.
        //  value = if the field is ignored by Publisher, set the value to whatever the open value should be.
        //      In this case the draft value is still saved to Publisher for future editing.
        foreach ($this->data_columns as $field_name => $value)
        {
            if (strstr($field_name, 'field_id_'))
            {
                // v0.86 - Removed the setting of 1 to the field, instead we'll always
                // add the published/open default language version of the content to the table.
                // This way, fallbacks will work better, and when viewing the site in the
                // default language, we don't need to use Publisher at all.
                if ($publisher_value = $this->get_field_value($this->where['entry_id'], $field_name))
                {
                    $value = $publisher_value;
                }
                else
                {
                    $value = '';
                }

                // Now set the real table values so it returns true when used in condtionals.
                ee()->db->where('entry_id', $this->where['entry_id'])
                    ->update($this->data_table_source, array($field_name => $value));
            }
        }

        // Make sure the core site_pages array is correct and does not
        // have a translated value in it, and save our translated version.
        ee()->publisher_site_pages->save($this->post_data, $this->meta_data);

        // Update our title and url_title columns to make sure the exp_channel_titles
        // table keeps the default language title for it. Third party add-ons
        // use this value, and we want the Titles be correct in the CP Edit list etc.
        // We have to do this otherwise when saving a new copy/language record
        // for an entry, it will want to update channel_titles with the new Title.
        // Basically the new title gets saved for a split second, then we return
        // it to it's original/default language value.
        $qry = ee()->db->select('title, url_title')
                ->where('publisher_status', PUBLISHER_STATUS_OPEN)
                ->where('publisher_lang_id', $this->default_language_id)
                ->where('entry_id', $this->where['entry_id'])
                ->get('publisher_titles');

        if ($qry->num_rows() == 1)
        {
            ee()->db->where('entry_id', $this->where['entry_id'])
                ->update('channel_titles', array(
                    'title' => $qry->row('title'),
                    'url_title' => $qry->row('url_title')
                ));
        }

        // -------------------------------------------
        //  'publisher_entry_save_end' hook
        //      - Grab $this by reference in your hook.
        //
            if (ee()->extensions->active_hook('publisher_entry_save_end'))
            {
                ee()->extensions->call('publisher_entry_save_end', ee()->publisher_lib->entry_id, $this->meta_data, $this->post_data);
            }
        //
        // -------------------------------------------
    }

    /**
     * Called after prepare() and insert(). If new entry saved as Draft, make sure
     * exp_channel_titles is set to Draft so it does not appear on the site.
     *
     * @param meta data from entry_submission_absolute_end()
     * @return void
     */
    public function save_as_draft($meta)
    {
        ee()->lang->loadfile('publisher');
        $lang_key = lang('publisher_'.PUBLISHER_STATUS_DRAFT);

        if (ee()->input->post('publisher_save_status') == PUBLISHER_STATUS_DRAFT AND
            isset(ee()->publisher_lib->is_new_entry) AND
            ee()->publisher_lib->is_new_entry)
        {
            $channel_id = $meta['channel_id'];

            $qry = ee()->db->select('status_group')
                    ->where('channel_id', $channel_id)
                    ->get('channels');

            $status_group = $qry->row('status_group');

            if ($status_group !== NULL)
            {
                $data = array(
                    'status'   => $lang_key,
                    'group_id' => $status_group
                );

                // See if the status exists
                $qry = ee()->db->select('status')->where($data)->get('statuses');

                // If not, add "draft" as a usable status
                if($qry->num_rows() == 0)
                {
                    $data = array_merge($data, array(
                        'status_order' => 999,
                        'highlight'    => ee()->publisher_setting->draft_status_color()
                    ));

                    ee()->db->insert('statuses', $data);
                }

                // See ticket http://boldminded.com/support/ticket/373
                // if (in_array($meta['status'], array(PUBLISHER_STATUS_DRAFT, PUBLISHER_STATUS_OPEN)))
                // {
                    // Now set this entry's status to our new draft
                    ee()->db->where('entry_id', $this->data_columns['entry_id'])
                             ->update('channel_titles', array('status' => $lang_key));
                // }
            }
        }

        // We have an existing entry saved as Open/Published, update it's main status so it's visible on the site
        if (ee()->input->post('publisher_save_status') != PUBLISHER_STATUS_DRAFT AND $meta['status'] == $lang_key)
        {
            ee()->db->where('entry_id', $this->data_columns['entry_id'])
                ->update('channel_titles', array('status' => PUBLISHER_STATUS_OPEN));
        }
    }

    /**
     * Delete a translation of an entry
     *
     * @param  int  $entry_id
     * @param  int  $language_id
     * @return void
     */
    public function delete_translation($entry_id, $language_id = FALSE)
    {
        $where = array(
            'entry_id'          => $entry_id,
            'publisher_status'  => ee()->publisher_lib->status,
            'publisher_lang_id' => $language_id
        );

        ee()->db->delete('publisher_titles', $where);
        ee()->db->delete('publisher_data', $where);
    }

    /**
     * Delete a draft of an entry
     *
     * @param  int $entry_id
     * @return void
     */
    public function delete_draft($entry_id)
    {
        $where = array(
            'entry_id'          => $entry_id,
            'publisher_status'  => PUBLISHER_STATUS_DRAFT,
            'publisher_lang_id' => ee()->publisher_lib->lang_id
        );

        ee()->db->delete('publisher_titles', $where);
        ee()->db->delete('publisher_data', $where);

        // And remove any existing approvals too
        ee()->load->model('publisher_approval_entry');
        ee()->publisher_approval_entry->delete($entry_id);

        // We just deleted the draft, see if there is an open version of the entry
        // if not, then we will also delete the main entry from EE too.
        if ( !$this->has_any_open($entry_id))
        {
            ee()->load->library('api');
            ee()->api->instantiate('channel_entries');
            ee()->api_channel_entries->delete_entry($entry_id);
        }
    }

    /**
     * See if its an ignored channel for field
     *
     * @param  int    $entry_id
     * @param  mixed  $field
     * @param  int    $lang_id
     * @return boolean
     */
    public function is_ignored($entry_id, $field_id = FALSE, $lang_id = FALSE)
    {
        if (is_array($entry_id))
        {
            foreach ($entry_id as $eid)
            {
                $this->_cache_ignored_entry($eid);

                if ($this->_check_is_ignored_channel_cache($eid))
                {
                    return TRUE;
                }
            }
        }
        else
        {
            $this->_cache_ignored_entry($entry_id);

            if ($this->_check_is_ignored_channel_cache($entry_id))
            {
                return TRUE;
            }
        }

        if ($field_id && $this->is_ignored_field($field_id))
        {
            return TRUE;
        }

        // ee()->publisher_lib->entry_id is not set unless its a CP request
        // if ($lang_id && REQ == 'CP' && !ee()->publisher_entry->has_draft(ee()->publisher_lib->entry_id, $lang_id))
        // {
        //     return TRUE;
        // }

        return FALSE;
    }

    /**
     * Cache this query, good possibility it could be run multiple times.
     *
     * @param  integer $entry_id
     * @return void
     */
    private function _cache_ignored_entry($entry_id)
    {
        if ( !isset(ee()->session->cache['publisher']['entries_to_channels'][$entry_id]))
        {
            $qry = ee()->db->select('channel_id')
                       ->where('entry_id', $entry_id)
                       ->get('channel_titles');

            if ($qry->num_rows())
            {
                ee()->session->cache['publisher']['entries_to_channels'][$entry_id] = $qry->row('channel_id');
            }
            else
            {
                ee()->session->cache['publisher']['entries_to_channels'][$entry_id] = FALSE;
            }
        }
    }

    /**
     * Check the entry against the cache to see if its an ignored channel
     *
     * @param  integer $entry_id
     * @return boolean
     */
    private function _check_is_ignored_channel_cache($entry_id)
    {
        if (isset(ee()->session->cache['publisher']['entries_to_channels'][$entry_id]) && ee()->session->cache['publisher']['entries_to_channels'][$entry_id])
        {
            if ($this->is_ignored_channel(ee()->session->cache['publisher']['entries_to_channels'][$entry_id]))
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * See if a draft exists for a requested entry and if its newer than the open version
     * If entries_query_result_entry_ids is set then it came from a channel:entries tag
     * and the channel_entries_query_result hook.
     *
     * @param  array $data Array of data about the entry
     * @param  int $language_id
     * @return boolean TRUE = newer, FALSE = older
     */
    public function has_draft($entry_id, $language_id = FALSE)
    {
        // Get by current language or a requested one?
        $language_id = $language_id ?: ee()->publisher_lib->lang_id;

        if (REQ != 'CP' && isset(ee()->session->cache['publisher']['entries_query_result_entry_ids']))
        {
            return $this->_has_draft_multi($entry_id, $language_id);
        }
        else
        {
            return $this->_has_draft_single($entry_id, $language_id);
        }

        return FALSE;
    }

    /**
     * The old has_draft method contents. Run the full queries for each entry.
     * Used inside of the CP on the edit entries page.
     *
     * @param  integer  $entry_id
     * @param  integer  $language_id
     * @return boolean
     */
    private function _has_draft_single($entry_id, $language_id)
    {
        $where = array(
            'entry_id'          => $entry_id,
            'publisher_status'  => PUBLISHER_STATUS_DRAFT,
            'publisher_lang_id' => $language_id
        );

        $qry = ee()->db->select('edit_date')->get_where('publisher_titles', $where);

        // If we have a draft, see if its newer than the open version.
        if ($qry->num_rows())
        {
            $draft_date = strtotime($qry->row('edit_date'));

            $where = array(
                'entry_id'          => $entry_id,
                'publisher_status'  => PUBLISHER_STATUS_OPEN,
                'publisher_lang_id' => $language_id
            );

            $qry = ee()->db->select('edit_date')->get_where('publisher_titles', $where);

            $open_date = $qry->row('edit_date') ? strtotime($qry->row('edit_date')) : 0;

            if ($draft_date > $open_date)
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Fetch multiple entries at once and save to the session cache,
     * then compare the dates from the cached result for the requested entry.
     * Queries will only get run once per entries tag.
     *
     * @param  integer  $entry_id
     * @param  integer  $language_id
     * @return boolean
     */
    private function _has_draft_multi($entry_id, $language_id)
    {
        $entry_ids = ee()->session->cache['publisher']['entries_query_result_entry_ids'];

        if ( !isset(ee()->session->cache['publisher']['has_draft_multi_draft']))
        {
            $where = array(
                'publisher_status'  => PUBLISHER_STATUS_DRAFT,
                'publisher_lang_id' => $language_id
            );

            $qry = ee()->db->select('entry_id, edit_date')
                       ->where($where)
                       ->where_in('entry_id', $entry_ids)
                       ->get('publisher_titles');

            ee()->session->cache['publisher']['has_draft_multi_draft'] = array();

            foreach ($qry->result() as $row)
            {
                ee()->session->cache['publisher']['has_draft_multi_draft'][$row->entry_id] = $row->edit_date;
            }
        }

        if ( !isset(ee()->session->cache['publisher']['has_draft_multi_open']))
        {
            $where = array(
                'publisher_status'  => PUBLISHER_STATUS_OPEN,
                'publisher_lang_id' => $language_id
            );

            $qry = ee()->db->select('entry_id, edit_date')
                       ->where($where)
                       ->where_in('entry_id', $entry_ids)
                       ->get('publisher_titles');

            ee()->session->cache['publisher']['has_draft_multi_open'] = array();

            foreach ($qry->result() as $row)
            {
                ee()->session->cache['publisher']['has_draft_multi_open'][$row->entry_id] = $row->edit_date;
            }
        }

        $open_date = 0;
        $draft_date = 0;

        if (isset(ee()->session->cache['publisher']['has_draft_multi_open'][$entry_id]))
        {
            $open_date = ee()->session->cache['publisher']['has_draft_multi_open'][$entry_id];
        }

        if (isset(ee()->session->cache['publisher']['has_draft_multi_draft'][$entry_id]))
        {
            $draft_date = ee()->session->cache['publisher']['has_draft_multi_draft'][$entry_id];
        }

        if($draft_date > $open_date)
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * See if an open entry exists for a requested entry
     *
     * @param  array  $data Array of data about the entry
     * @param  int  $language_id
     * @return boolean
     */
    public function has_open($entry_id, $language_id = FALSE)
    {
        // Get by current language or a requested one?
        $language_id = $language_id ?: ee()->publisher_lib->lang_id;

        $where = array(
            'entry_id'          => $entry_id,
            'publisher_status'  => PUBLISHER_STATUS_OPEN,
            'publisher_lang_id' => $language_id
        );

        $qry = ee()->db->get_where('publisher_titles', $where);

        // We have an open entry
        if ($qry->num_rows())
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * See if an open entry exists in _any_ language
     *
     * @param  int      $entry_id
     * @return boolean
     */
    public function has_any_open($entry_id)
    {
        $return = FALSE;

        foreach (ee()->publisher_model->languages as $lang_id => $language)
        {
            if ($this->has_open($entry_id, $lang_id))
            {
                $return = TRUE;
                break;
            }
        }

        return $return;
    }

    /**
     * See if an draft entry exists in _any_ language
     *
     * @param  int      $entry_id
     * @return boolean
     */
    public function has_any_draft($entry_id)
    {
        $return = FALSE;

        foreach (ee()->publisher_model->languages as $lang_id => $language)
        {
            if ($this->has_draft($entry_id, $lang_id))
            {
                $return = TRUE;
                break;
            }
        }

        return $return;
    }

    /**
     * See if a translation exists for the requested entry
     *
     * @param  array  $data         Array of data about the entry
     * @param  int    $language_id
     * @return boolean
     */
    public function has_translation($entry_id, $language_id = FALSE, $status = FALSE)
    {
        // Get by current language or a requested one?
        $language_id = $language_id ?: ee()->publisher_lib->lang_id;
        $status = $status ?: ee()->publisher_lib->status;

        $where = array(
            'entry_id'          => $entry_id,
            'publisher_status'  => $status,
            'publisher_lang_id' => $language_id
        );

        $cache_key = 'has_translation/'. md5(serialize($where));

        // Using Cache?
        if (($cache_results = ee()->publisher_cache->driver->get($cache_key)) !== FALSE)
        {
            return $cache_results;
        }

        $qry = ee()->db->get_where('publisher_titles', $where);

        if ($qry->num_rows())
        {
            ee()->publisher_cache->driver->save($cache_key, TRUE);

            return TRUE;
        }

        return FALSE;
    }

    /**
     * Get an entry in its entirety
     *
     * @param  int    $entry_id Array of data about the entry
     * @param  string $status
     * @param  int    $lang_id
     * @return boolean
     */
    public function get($entry_id, $status = PUBLISHER_STATUS_OPEN, $lang_id = FALSE, $custom_field_names = FALSE, $use_cache = FALSE)
    {
        // Get by current language or a requested one?
        $lang_id = $lang_id ?: ee()->publisher_lib->lang_id;

        $where = array(
            't.entry_id'            => $entry_id,
            'd.entry_id'            => $entry_id,
            't.publisher_status'    => $status,
            'd.publisher_status'    => $status,
            't.publisher_lang_id'   => $lang_id,
            'd.publisher_lang_id'   => $lang_id,
            't.site_id'             => ee()->publisher_lib->site_id
        );

        $select_fields = array('t.title as title');
        $select_fields_str = '';

        $cache_key = 'entry_get/'. $entry_id .'/'. $lang_id .'/'. $status;

        // Using Cache?
        if (isset(ee()->TMPL) && ee()->TMPL->fetch_param('publisher_cache') == 'yes')
        {
            if (($cache_results = ee()->publisher_cache->driver->get($cache_key)) !== FALSE)
            {
                return $cache_results;
            }
        }

        // If this is set, then we've already queried for and filtered our results
        // and the same tag must be included on the page multiple times. Tsk tsk, developers.
        if (isset(ee()->session->cache['publisher']['entry_get'][$cache_key]))
        {
            return ee()->session->cache['publisher']['entry_get'][$cache_key];
        }

        if ($custom_field_names)
        {
            $custom_fields = $this->get_custom_field_names();

            foreach ($custom_fields as $k => $name)
            {
                $select_fields[] = 'd.'. $k .' AS `'. $name .'`';
            }
        }

        $select_fields_str = ', '. implode(', ', $select_fields);

        $qry = ee()->db->select('*'. $select_fields_str)
                    ->from('publisher_titles AS t')
                    ->join('publisher_data AS d', 't.entry_id = d.entry_id')
                    ->where($where)
                    ->get();

        // If we have it, return it.
        if ($qry->num_rows() == 1)
        {
            $entry = $qry->row();
        }
        else
        {
            $entry = FALSE;
        }

        if (isset(ee()->TMPL) && ee()->TMPL->fetch_param('publisher_cache') == 'yes')
        {
            ee()->publisher_cache->driver->save($cache_key, $entry);
        }

        ee()->session->cache['publisher']['entry_get'][$cache_key] = $entry;

        return $entry;
    }

    /**
     * Take the channel_entries_query_data and replace the contents with our custom data if
     * defined in $results. If not, then just fetch all entries within an ID collection.
     *
     * @param  array $entry_ids     Array of entry IDs to get entries for
     * @param  array $results       Array of entry data from the EE hook
     * @param  string $lang_filter
     * @param  string $status_filter
     * @return array
     */
    public function get_all($entry_ids, $results = array(), $lang_filter = '', $status_filter = '')
    {
        $lang_id = $lang_filter ?: ee()->publisher_lib->lang_id;
        $status  = $status_filter ?: ee()->publisher_lib->status;
        $site_id = ee()->publisher_lib->site_id;

        // Turn it off, find entries only if they exist in the requested language.
        if ( !ee()->publisher_setting->persistent_entries())
        {
            $lang_id = ee()->publisher_lib->lang_id;
        }

        if (isset(ee()->TMPL))
        {
            // Override on the template tag?
            if (ee()->TMPL->fetch_param('publisher_status'))
            {
                $status = ee()->TMPL->fetch_param('publisher_status');
            }

            if (ee()->TMPL->fetch_param('publisher_lang_id'))
            {
                $lang_id = ee()->TMPL->fetch_param('publisher_lang_id');
            }

            // Internal/Publisher use only. Used in Diffs. Uses own
            // parameters so publisher_entry->filter_query_result() works
            if (ee()->TMPL->fetch_param('publisher_diff_status'))
            {
                $status = ee()->TMPL->fetch_param('publisher_diff_status');
            }

            if (ee()->TMPL->fetch_param('publisher_diff_lang_id'))
            {
                $lang_id = ee()->TMPL->fetch_param('publisher_diff_lang_id');
            }

            if (ee()->TMPL->fetch_param('site'))
            {
                $site = ee()->TMPL->fetch_param('site');

                foreach (ee()->publisher_model->get_sites() as $row)
                {
                    if ($row->site_name == $site)
                    {
                        $site_id = $row->site_id;
                    }
                }
            }
        }

        $cache_key = 'entries_get_all/'. $lang_id .'/'. $status .'/'. md5(serialize($entry_ids) . serialize(ee()->TMPL->tagparams));

        // Using Zend Cache?
        if (ee()->TMPL->fetch_param('publisher_cache') == 'yes')
        {
            if (($cache_results = ee()->publisher_cache->driver->get($cache_key)) !== FALSE)
            {
                return $cache_results;
            }
        }

        // If this is set, then we've already queried for and filtered our results
        // and the same tag must be included on the page multiple times. Tsk tsk, developers.
        if (isset(ee()->session->cache['publisher']['entries_get_all_results'][$cache_key]))
        {
            return ee()->session->cache['publisher']['entries_get_all_results'][$cache_key];
        }

        $where = array(
            't.publisher_status'    => $status,
            'd.publisher_status'    => $status,
            't.publisher_lang_id'   => $lang_id,
            'd.publisher_lang_id'   => $lang_id,
            't.site_id'             => $site_id
        );

        $entries = $this->get_all_query($entry_ids, $where);
        $persistent_entries = ee()->publisher_setting->persistent_entries();

        // If persistence is off then pagination may be
        // altering the results by adding new entries to the set.
        if ( !$persistent_entries && $this->is_paginating)
        {
            $result_entry_ids = array();

            foreach ($results as $k => $entry)
            {
                $result_entry_ids[] = $entry['entry_id'];
            }

            foreach ($entries as $k => $entry)
            {
                if ( !array_search($entry['entry_id'], $result_entry_ids) !== FALSE)
                {
                    $results[] = $entries[$entry['entry_id']];
                }
            }
        }

        // YAY for cyclomatic complexity!

        // First try to find the entries of the requested language.
        // We have native EE results, and valid Publisher results.
        if ( !empty($results) && !empty($entries))
        {
            foreach ($results as $k => $entry)
            {
                if (ee()->publisher_model->is_ignored_channel($entry['channel_id']))
                {
                    continue;
                }

                // If any filters were used and we don't have an Publisher entry, cut it.
                // If its not in the translated query result and presistence is off then
                // we need to remove the entry from the result set.
                if (
                    (($status_filter || $lang_filter) && !isset($entries[$entry['entry_id']])) ||
                    (!isset($entries[$entry['entry_id']]) && !$persistent_entries)
                ){
                    unset($results[$k]);
                }
                else
                {
                    if (is_array(ee()->publisher_setting->ignored_fields()))
                    {
                        foreach ($entry as $field => $value)
                        {
                            if (ee()->publisher_model->is_ignored_field($field))
                            {
                                $results[$k][$field] = $value;
                            }
                            else if (isset($entries[$entry['entry_id']][$field]))
                            {
                                $results[$k][$field] = $entries[$entry['entry_id']][$field];
                            }
                        }
                    }
                    else
                    {
                        // Only merge if we have a translated version
                        if (isset($entries[$entry['entry_id']]))
                        {
                            $results[$k] = array_merge($results[$k], $entries[$entry['entry_id']]);
                        }
                    }

                    // If translated URLs are not enabled, use the default url_title instead
                    if ( !ee()->publisher_setting->url_translations() AND isset($entries[$results[$k]['entry_id']]))
                    {
                        $results[$k]['url_title'] = $entries[$results[$k]['entry_id']]['default_url_title'];
                    }
                }
            }

            // If fallback is turned on and its not the default language, see if we need
            // to grab the default language data for the entries and swap out content.
            if ($persistent_entries &&
                ee()->publisher_setting->replace_fallback() &&
                ee()->publisher_lib->lang_id != ee()->publisher_lib->default_lang_id
            ){
                $default_results = $this->_get_all_default($results, $entry_ids, $where);

                foreach ($results as $k => $entry)
                {
                    foreach ($entry as $field => $value)
                    {
                        if ($value == '')
                        {
                            foreach($default_results as $default_entry)
                            {
                                if ($default_entry['entry_id'] == $entry['entry_id'] && $default_entry[$field] != '')
                                {
                                    $results[$k][$field] = $default_entry[$field];
                                }
                            }
                        }
                    }
                }
            }
        }
        // If nothing was found, fallback to the default, regardless of the fallback settings.
        else
        {
            // Normal, most common scenario
            if ($persistent_entries)
            {
                $results = $this->_get_all_default($results, $entry_ids, $where);
            }
            // Hidden setting, least common scenario
            else if (empty($entries) && !$persistent_entries)
            {
                foreach ($results as $k => $entry)
                {
                    if ( !ee()->publisher_model->is_ignored_channel($entry['channel_id']))
                    {
                        unset($results[$k]);
                    }
                }
            }
        }

        $results = $this->add_publisher_fields_to_results($results, $status);

        // If we're using non-persistent entries the normal limit and offset
        // parameters don't work due to how EE queries for entry ids, then
        // runs yet another query based on those specific ids, which get passed
        // to the hook. Need to use our own limit and offset parameters to
        // filter the final result set. Would rather this be done in the
        // original query, but it just isn't possible.
        if ( !$persistent_entries)
        {
            $limit = ee()->TMPL->fetch_param('publisher_limit');
            $offset = ee()->TMPL->fetch_param('publisher_offset');

            if ($limit && $offset)
            {
                $results = array_slice($results, $offset, $limit);
            }
            elseif ($limit)
            {
                $results = array_slice($results, 0, $limit);
            }
        }

        // Save to cache so we don't repeat the same queries and processing.
        ee()->session->cache['publisher']['entries_get_all_results'][$cache_key] = $results;

        // Save to Zend Cache if we're using it.
        if (ee()->TMPL->fetch_param('publisher_cache') == 'yes')
        {
            ee()->publisher_cache->driver->save($cache_key, $results);
        }

        return $results;
    }

    /**
     * Get all entries with the most basic options
     *
     * @param  array   $entry_ids
     * @param  string $lang_id
     * @param  string $status
     * @return array
     */
    public function get_all_query($entry_ids, $where = array())
    {
        if (empty($entry_ids))
        {
            return array();
        }

        if (empty($where))
        {
            $lang_id = ee()->publisher_lib->lang_id;

            // Turn it off, find entries only if they exist in the requested language.
            if ( !ee()->publisher_setting->persistent_entries())
            {
                $lang_id = ee()->publisher_lib->lang_id;
            }

            $where = array(
                't.publisher_status'    => ee()->publisher_lib->status,
                'd.publisher_status'    => ee()->publisher_lib->status,
                't.publisher_lang_id'   => $lang_id,
                'd.publisher_lang_id'   => $lang_id,
                't.site_id'             => ee()->publisher_lib->site_id
            );
        }

        $columns = implode(',', array(
            'ct.*',
            'cd.*',
            't.*',
            'd.*',
            'c.*',
            'm.*',
            'ct.url_title AS default_url_title',
            't.title AS title'
        ));

        $qry = ee()->db->select($columns)
                    ->from('publisher_titles AS t')
                    ->join('publisher_data AS d', 't.entry_id = d.entry_id')
                    ->join('channel_titles AS ct', 'ct.entry_id = t.entry_id')
                    ->join('channel_data AS cd', 'ct.entry_id = cd.entry_id')
                    ->join('channels AS c', 'c.channel_id = t.channel_id')
                    ->join('members AS m', 'm.member_id = t.edit_by')
                    ->where($where)
                    ->where_in('t.entry_id', $entry_ids)
                    ->get();

        // If no rows were found, and fallback is set, then query default language instead.
        if ($qry->num_rows() == 0 && ee()->publisher_setting->show_fallback())
        {
            $where['t.publisher_lang_id'] = ee()->publisher_lib->default_lang_id;
            $where['d.publisher_lang_id'] = ee()->publisher_lib->default_lang_id;

            $qry = ee()->db->select($columns)
                    ->from('publisher_titles AS t')
                    ->join('publisher_data AS d', 't.entry_id = d.entry_id')
                    ->join('channel_titles AS ct', 'ct.entry_id = t.entry_id')
                    ->join('channel_data AS cd', 'ct.entry_id = cd.entry_id')
                    ->join('channels AS c', 'c.channel_id = t.channel_id')
                    ->join('members AS m', 'm.member_id = t.edit_by')
                    ->where($where)
                    ->where_in('t.entry_id', $entry_ids)
                    ->get();

            // if ($qry->num_rows() == 0)
            // {
            //     $where['t.publisher_status'] = PUBLISHER_STATUS_OPEN;
            //     $where['d.publisher_status'] = PUBLISHER_STATUS_OPEN;

            //     $qry = ee()->db->select('ct.*, t.*, d.*, ct.url_title AS default_url_title, t.title AS title')
            //             ->from('publisher_titles AS t')
            //             ->join('publisher_data AS d', 't.entry_id = d.entry_id')
            //             ->join('channel_titles AS ct', 'ct.entry_id = t.entry_id')
            //             ->where($where)
            //             ->where_in('t.entry_id', $entry_ids)
            //             ->get();
            // }
        }

        $entries = array();

        // Create local cache to hold the lang_id and publisher_status
        // values so they can be added back in the add_publisher_fields_to_entry method.
        if ( !isset(ee()->session->cache['publisher']['publisher_fields']))
        {
            ee()->session->cache['publisher']['publisher_fields'] = array();
        }

        foreach ($qry->result_array() as $entry)
        {
            $entries[$entry['entry_id']] = $entry;

            ee()->session->cache['publisher']['publisher_fields'][$entry['entry_id']] = array(
                'publisher_lang_id' => $entry['publisher_lang_id'],
                'publisher_status'  => $entry['publisher_status']
            );
        }

        return $entries;
    }

    /**
     * Search for the the default version of the entries
     *
     * @param  array $results
     * @param  array $entry_ids
     * @param  array $where
     * @return array
     */
    private function _get_all_default($results, $entry_ids, $where)
    {
        $where['t.publisher_lang_id'] = ee()->publisher_lib->default_lang_id;
        $where['d.publisher_lang_id'] = ee()->publisher_lib->default_lang_id;

        $qry = ee()->db->select('ct.*, t.*, d.*, ct.url_title AS default_url_title')
                ->from('publisher_titles AS t')
                ->join('publisher_data AS d', 't.entry_id = d.entry_id')
                ->join('channel_titles AS ct', 'ct.entry_id = t.entry_id')
                ->where($where)
                ->where_in('t.entry_id', $entry_ids)
                ->get();

        foreach ($qry->result_array() as $entry)
        {
            $entries[$entry['entry_id']] = $entry;
        }

        foreach ($results as $k => $entry)
        {
            if (is_array(ee()->publisher_setting->ignored_fields()))
            {
                foreach ($entry as $field => $value)
                {
                    if (ee()->publisher_model->is_ignored_field($field))
                    {
                        $results[$k][$field] = $value;
                    }
                    else if (isset($entries[$entry['entry_id']][$field]))
                    {
                        $results[$k][$field] = $entries[$entry['entry_id']][$field];
                    }
                }
            }
            else
            {
                // Only merge if we have a translated version
                if (isset($entries[$entry['entry_id']]))
                {
                    $results[$k] = array_merge($results[$k], $entries[$entry['entry_id']]);
                }
            }
        }

        return $results;
    }

    /**
     * If its a pagination entries tag, and presistent entries are turned off
     *
     * @param  Channel $channel
     * @return array
     */
    public function get_pagination_entries(Channel $channel)
    {
        // Get the queries and reverse the list so it does fewer loops.
        $current_queries = array_reverse(ee()->db->queries);
        $entry_ids = array();
        $limit = 100;
        $offset = 0;
        $count = 0;
        $limit_sql = '';

        foreach ($current_queries as $query)
        {
            if (
                (strpos($query, 'SELECT t.entry_id FROM '. ee()->db->dbprefix . 'channel_titles AS t') !== FALSE) ||
                (strpos($query, 'SELECT DISTINCT(t.entry_id) FROM '. ee()->db->dbprefix .'channel_titles AS t') !== FALSE)
            ){
                // Modify the query and use the offset and limit to grab the current
                // subset of results for the current page.
                $sql = str_replace(
                    'WHERE',
                    'LEFT JOIN exp_publisher_titles AS pt ON pt.entry_id = t.entry_id
                        WHERE pt.publisher_lang_id = '.ee()->publisher_lib->lang_id .'
                        AND pt.publisher_status = "'.ee()->publisher_lib->status .'" AND ',
                    $query
                );

                // Find our new set of entry_ids with original offset and limit
                $qry = ee()->db->query($sql);

                foreach ($qry->result_array() as $row)
                {
                    $entry_ids[] = $row['entry_id'];
                }

                // Remove the offset and limit so we can grab the count
                // and update the pagination class.
                $sql = preg_replace('/LIMIT [0-9,\s]+$/', '', $sql);

                $qry = ee()->db->query($sql);
                $count = $qry->num_rows();

                // Exit the loop, found what we need.
                break;
            }
        }

        $channel->pagination->page_links = NULL;
        $channel->pagination->absolute_results = $count;
        $channel->pagination->total_rows = $count;
        $channel->EE->pagination->total_rows = $count;
        $channel->pagination->total_pages = $count;

        $channel->pagination->build($count, $full_sql);

        return $entry_ids;
    }

    /**
     * Loop over the whole results array and add custom fields to the entry
     *
     * @param array $results Full channel:entries result set
     */
    public function add_publisher_fields_to_results($results, $status = NULL)
    {
        $status = $status ?: ee()->publisher_lib->status;

        $cache_key = 'add_publisher_fields_to_results/'. ee()->publisher_lib->lang_id .'/'. ee()->publisher_lib->status .'/'. md5(serialize(ee()->TMPL->tagparams));

        if (ee()->TMPL->fetch_param('publisher_cache') == 'yes')
        {
            if (($cahe_results = ee()->publisher_cache->driver->get($cache_key)) !== FALSE)
            {
                return $cahe_results;
            }
        }

        $prefix = $this->_get_publisher_fields_prefix();

        // Add default fields, no queries required.
        // Get the lang_id & status from the publisher_titles table.
        if (isset(ee()->session->cache['publisher']['publisher_fields']))
        {
            foreach ($results as $k => $entry)
            {
                if (isset(ee()->session->cache['publisher']['publisher_fields'][$entry['entry_id']]))
                {
                    $entry_cache = ee()->session->cache['publisher']['publisher_fields'][$entry['entry_id']];

                    $results[$k][$prefix.'publisher_lang_id'] = $entry_cache['publisher_lang_id'];
                    $results[$k][$prefix.'publisher_status'] = $entry_cache['publisher_status'];
                }
                else
                {
                    $results[$k][$prefix.'publisher_lang_id'] = '';
                    $results[$k][$prefix.'publisher_status'] = '';
                }
            }
        }
        // Its a default language and open status query, so set the vars
        // to the current requested status/lang so the variables parse in templates.
        else
        {
            foreach ($results as $k => $entry)
            {
                $results[$k][$prefix.'publisher_lang_id'] = ee()->publisher_lib->lang_id;
                $results[$k][$prefix.'publisher_status'] = ee()->publisher_lib->status;
            }
        }

        // Add extra fields to the results that require separate queries.
        // publisher_fields can't be equal to "n" or "no"
        if (ee()->TMPL->fetch_param('publisher_fields') &&
            !ee()->publisher_helper->is_boolean_param('publisher_fields', FALSE))
        {
            foreach ($results as $k => $entry)
            {
                $results[$k] = $this->add_publisher_fields_to_entry($entry, $status);
            }
        }

        if (ee()->TMPL->fetch_param('publisher_cache') == 'yes')
        {
            ee()->publisher_cache->driver->save($cache_key, $results);
        }

        return $results;
    }

    /**
     * Add fields to specific entry
     * @param array $entry Single channel:entry
     */
    public function add_publisher_fields_to_entry($entry, $status)
    {
        if ($this->is_ignored($entry['entry_id']))
        {
            return $entry;
        }

        $prefix = $this->_get_publisher_fields_prefix();

        $entry[$prefix.'has_newer_draft'] = $this->has_draft($entry['entry_id']);
        $entry[$prefix.'is_translation_complete'] = $this->is_translated($entry['entry_id']);
        $entry[$prefix.'has_translation'] = $this->has_translation($entry['entry_id']);
        $entry[$prefix.'has_pending_approval'] = ee()->publisher_approval->exists($entry['entry_id']);

        return $entry;
    }

    /**
     * Get the prefix to be used when adding Publisher fields to the entries results
     * @return string $prefix
     */
    private function _get_publisher_fields_prefix()
    {
        $prefix = '';

        // If its not a "boolean" then assume its a custom prefix.
        if (ee()->TMPL->fetch_param('publisher_fields') &&
            !ee()->publisher_helper->is_boolean_param('publisher_fields'))
        {
            $prefix = ee()->TMPL->tagparams['publisher_fields'];
        }

        return $prefix;
    }

    /**
     * Get all translated versions of the entry and add it as a nested field pair.
     *
     * 03/17/2014 - This never worked, entries parser does not support custom tag pairs.
     *
     * @param array $entry
     * @param string $status
     */
    public function add_publisher_translations_to_entry($entry, $status, $prefix = NULL)
    {
        foreach (ee()->publisher_model->languages as $lang_id => $language)
        {
            $translated = $this->get($entry['entry_id'], $status, $lang_id);

            if ($translated)
            {
                $entry['translations'][] = $this->transpose_column_names(get_object_vars($translated), 'name', 'translation:');
            }
        }

        return $entry;
    }

    /**
     * Reorder the query result array based on a custom field
     *
     * @param  array $entry_ids     Array of entry IDs to get entries for
     * @param  array $results       Array of entry data
     * @param  string $orderby_field Field to order by
     * @return array
     */
    public function order_query_result($entry_ids, $results = array(), $orderby_field = '', $sort = 'desc')
    {
        if ($orderby_field != 'title' AND $orderby_field != '')
        {
            if ($field_id = ee()->publisher_model->get_custom_field($orderby_field, 'field_id', TRUE))
            {
                $orderby_field = 'field_id_'. $field_id;
            }
        }

        // All entries should have the same fields, so see if the first entry
        // has the requested field. If so, we can assume the rest do too.
        if ( !isset($results[0][$orderby_field]))
        {
            return $results;
        }

        $this->_usort_use = $orderby_field;
        usort($results, array($this, "_usort"));

        // usort($results, function($a, $b) use ($orderby_field)
        // {
        //     return strcmp($a[$orderby_field], $b[$orderby_field]);
        // });

        if ($sort == 'desc')
        {
            $results = array_reverse($results);
        }

        return $results;
    }

    /**
     * usort function that works with PHP 5.2 or less
     *
     * @param  array $a
     * @param  array $b
     * @return array
     */
    private function _usort($a, $b)
    {
        // http://stackoverflow.com/questions/3371697/replacing-accented-characters-php
        $unwanted_array = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A',
            'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O',
            'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U',
            'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a',
            'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e',
            'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
            'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y',
            'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y',
            // Turkish
            'Ğ'=>'G', 'İ'=>'I', 'Ş'=>'S', 'ğ'=>'g', 'ı'=>'i', 'ş'=>'s', 'ü'=>'u',
            // Romanian
            'ă'=>'a', 'Ă'=>'A', 'ș'=>'s', 'Ș'=>'S', 'ț'=>'t', 'Ț'=>'T');

        // strtolower does not work on multibyte characters, so try mb_strtolower if it exists.
        if (function_exists('mb_strtolower'))
        {
            $func = 'mb_strtolower';
        }
        else
        {
            $func = 'strtolower';
        }

        $a_str = strtr($func($a[$this->_usort_use]), $unwanted_array);
        $b_str = strtr($func($b[$this->_usort_use]), $unwanted_array);

        return strcmp($a_str, $b_str);

        // According to the Googles this should work, but didn't.
        // return strcmp(
        //     iconv('UTF-8', 'ISO-8859-1//TRANSLIT', mb_strtolower($a[$this->_usort_use])),
        //     iconv('UTF-8', 'ISO-8859-1//TRANSLIT', mb_strtolower($b[$this->_usort_use]))
        // );

        // Original
        // return strcmp($a[$this->_usort_use], $b[$this->_usort_use]);
    }

    /**
     * Get only the title fields from publisher_titles
     *
     * @param  array $entry_ids
     * @param  string $status
     * @return result obj
     */
    public function get_titles($entry_ids, $status = FALSE)
    {
        $status = $status ?: ee()->publisher_lib->status;

        $where = array(
            'publisher_status'    => $status,
            'publisher_lang_id'   => ee()->publisher_lib->lang_id,
            'site_id'             => ee()->publisher_lib->site_id
        );

        $qry = ee()->db->select('entry_id, title')
                            ->from('publisher_titles')
                            ->where($where)
                            ->where_in('entry_id', $entry_ids)
                            ->get();

        $titles = array();

        foreach ($qry->result() as $row)
        {
            $titles[$row->entry_id] = $row->title;
        }

        return $titles;
    }

    /**
     * DEPRECATED - Get only entries in the requested language or status
     * @param  array $entry_ids
     * @param  array $results
     * @param  string $lang_filter
     * @param  string $status_filter
     * @return array
     */
    public function filter_query_result($entry_ids, $results, $lang_filter, $status_filter)
    {
        return $this->get_all($entry_ids, $results, $lang_filter, $status_filter);
    }

    /**
     * Search by a translated url_title, and return the default language version of it.
     *
     * @param  string $url_title
     * @return string
     */
    public function get_default_url_title($url_title, $return = 'url_title')
    {
        $lang_id = ee()->publisher_lib->lang_id;
        $status  = ee()->publisher_lib->status;

        $cache_key = 'get_default_url_title/entry/'. $lang_id .'/'. $status .'/'. $url_title .'/'. $return;

        // Make sure this query run as few times as possible
        if ( !isset(ee()->session->cache['publisher'][$cache_key]))
        {
            $qry = ee()->db->select('entry_id')
                    ->where('url_title', $url_title)
                    ->where('publisher_lang_id', $lang_id)
                    ->where('publisher_status', $status)
                    ->get('publisher_titles');

            if ($qry->num_rows() == 1)
            {
                $entry_id = $qry->row('entry_id');

                if ($return == 'entry_id')
                {
                    return $entry_id;
                }

                $qry = ee()->db->select('url_title')
                        ->where('entry_id', $entry_id)
                        ->get('channel_titles');

                ee()->session->cache['publisher'][$cache_key] = $qry->num_rows() ? $qry->row('url_title') : $url_title;
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

        $cache_key = 'get_translated_url_title/'. $lang_id_from .'/'. $lang_id_to .'/'. md5($url_title);

        // Using Cache?
        if (($cache_results = ee()->publisher_cache->driver->get($cache_key)) !== FALSE)
        {
            return $cache_results;
        }
        else
        {
            $qry = ee()->db->select('entry_id')
                    ->where('url_title', $url_title)
                    ->where('publisher_lang_id', $lang_id_from)
                    ->where('publisher_status', ee()->publisher_lib->status)
                    ->get('publisher_titles');

            if ($qry->num_rows() == 1)
            {
                $entry_id = $qry->row('entry_id');

                $qry = ee()->db->select('url_title')
                        ->where('entry_id', $entry_id)
                        ->where('publisher_lang_id', $lang_id_to)
                        ->where('publisher_status', ee()->publisher_lib->status)
                        ->get('publisher_titles');

                $url_title = $qry->num_rows() ? $qry->row('url_title') : $url_title;
            }

            ee()->publisher_cache->driver->save($cache_key, $url_title);

            return $url_title;
        }
    }

    /*
        TESTING
    */
    public function get_all_translated_url_titles($entry_ids)
    {
        $qry = ee()->db->select('url_title')
            ->where_in('entry_id', $entry_ids)
            ->where('publisher_lang_id', ee()->publisher_lib->lang_id)
            ->where('publisher_status', ee()->publisher_lib->status)
            ->get('publisher_titles');

        $key = ee()->publisher_lib->lang_id.'/'.ee()->publisher_lib->lang_id;

        $this->cache['translated_url_title'] = array();

        foreach ($qry->result() as $row)
        {
            $url_title = $row->url_title;

            $this->cache['translated_url_title'][$url_title][$key] = $url_title;
        }
    }

    /**
     * See if an entry has a record for each language available,
     * if it does, assume its fully translated
     *
     * @param  int  $entry_id
     * @return boolean
     */
    public function is_translated($entry_id, $detailed = FALSE)
    {
        $languages = ee()->publisher_model->get_enabled_languages();
        $languages_enabled = array_keys($languages);

        if ( !$detailed && REQ != 'CP' && isset(ee()->session->cache['publisher']['entries_query_result_entry_ids']))
        {
            if ( !isset(ee()->session->cache['publisher']['is_translated']))
            {
                $entry_ids = ee()->session->cache['publisher']['entries_query_result_entry_ids'];

                $qry = ee()->db->select('entry_id, publisher_lang_id, publisher_status')
                        ->where_in('entry_id', $entry_ids)
                        ->where('publisher_status', PUBLISHER_STATUS_OPEN)
                        ->where_in('publisher_lang_id', $languages_enabled)
                        ->get($this->title_table);

                ee()->session->cache['publisher']['is_translated'] = array();

                foreach ($qry->result() as $row)
                {
                    ee()->session->cache['publisher']['is_translated'][$row->entry_id][] = $row;
                }
            }

            if (isset(ee()->session->cache['publisher']['is_translated'][$entry_id]))
            {
                $num_rows = count(ee()->session->cache['publisher']['is_translated'][$entry_id]);
            }
            else
            {
                $num_rows = 0;
            }
        }
        else
        {
            $qry = ee()->db->select('entry_id, publisher_lang_id, publisher_status')
                    ->where('entry_id', $entry_id)
                    ->where('publisher_status', PUBLISHER_STATUS_OPEN)
                    ->where_in('publisher_lang_id', $languages_enabled)
                    ->get($this->title_table);

            $num_rows = $qry->num_rows();
        }

        if ( !$detailed && $num_rows == count($languages_enabled))
        {
            return TRUE;
        }
        else if ($detailed)
        {
            $return = array();

            foreach ($languages_enabled as $lang_id)
            {
                foreach ($qry->result() as $row)
                {
                    if ($row->publisher_lang_id == $lang_id)
                    {
                        $return[] = 'complete_'.strtoupper($languages[$row->publisher_lang_id]['short_name']);
                    }
                }

                if ( !in_array('complete_'.strtoupper($languages[$lang_id]['short_name']), $return))
                {
                    $return[] = 'incomplete_'.strtoupper($languages[$lang_id]['short_name']);
                }
            }

            return $return;
        }

        return FALSE;
    }

    public function is_translated_formatted($entry_id, $detailed = FALSE)
    {
        $status = $this->is_translated($entry_id, $detailed);

        if ($this->is_ignored($entry_id))
        {
            return '';
        }

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
            $channel_id = ee()->input->get('channel_id', TRUE);

            $languages = ee()->publisher_model->get_languages_by('short_name');

            foreach ($status as $stat)
            {
                $short_name = explode('_', $stat, 2);
                $lang_id = $languages[ee()->publisher_helper->segment_to_lang($short_name[1])]['id'];

                $link = BASE.'&C=content_publish&M=entry_form&channel_id='. $channel_id .'&entry_id='. $entry_id .'&lang_id='. $lang_id;

                $return .= '<a href="'. $link .'" class="publisher-translation-status publisher-translation-status-'. $short_name[0] .'">'. $short_name[1] .'</a>';
            }

        }

        return $return;
    }

    /**
     * Make sure the URL title is created properly with increment and all
     *
     * @param  string $url_title posted value if present
     * @return string
     */
    public function create_url_title($str = NULL, $entry_id, $translated = FALSE, $site_id = NULL)
    {
        $channel_id = ee()->input->post('channel_id');
        $post_title = ee()->input->post('title');
        $site_id = $site_id ?: ee()->publisher_lib->site_id;

        ee()->load->helper('url_helper');

        if ( !$str AND $post_title)
        {
            $str = $post_title;
        }
        else if ( !$str AND $entry_id)
        {
            $qry = ee()->db->select('title')
                    ->where('entry_id', $entry_id)
                    ->get('channel_titles');

            $str = $qry->row('title');
        }

        $url_title = strtolower(url_title($str));

        // See if there might be a naming collision.
        if ($translated)
        {
            $count = ee()->db->where('publisher_status', PUBLISHER_STATUS_OPEN)
                        ->where('publisher_lang_id', ee()->publisher_lib->lang_id)
                        ->where('url_title', $url_title)
                        ->where('entry_id !=', $entry_id)
                        ->where('channel_id', $channel_id)
                        ->where('site_id', $site_id)
                        ->count_all_results('publisher_titles');
        }
        else
        {
            $count = ee()->db->where('url_title', $url_title)
                        ->where('entry_id !=', $entry_id)
                        ->where('channel_id', $channel_id)
                        ->where('site_id', $site_id)
                        ->count_all_results('channel_titles');
        }

        if ($count >= 1)
        {
            $separator = ee()->config->item('word_separator') == 'dash' ? '-' : '_';

            // Blow it up
            $parts = explode($separator, $url_title);

            // Get only the last part, which may or may not be an integer
            $top = array_pop($parts);

            // If its an integer we need to PUMP. IT. UP.
            if (is_numeric($top))
            {
                $new_count = $top + 1;
                // Put the pieces back together, sans the last int
                $url_title = implode($separator, $parts);
            }
            elseif ($count > 1)
            {
                $new_count = $count + 1;
            }
            else
            {
                $new_count = 1;
            }

            $url_title = $url_title . $separator . $new_count;

            // Recursion. See if the new url_title is itself a dupe
            // and keep going until we have a unique, or $count = 0
            $url_title = $this->create_url_title($url_title, $entry_id, $translated, $site_id);
        }

        return $url_title;
    }

    /**
     * After installation migrate all existing data to the Publisher tables, set all existing data to "1"
     * If $update is set, then we are inserting or updating data, which is more intensive due to
     * extra queries.
     *
     * @param $entry_ids array
     * @return void
     */
    public function migrate_data($entry_ids = array(), $where = array())
    {
        // If we have entries, then we're updating.
        $updating = !empty($entry_ids) ? TRUE : FALSE;

        // This could take awhile for a larger site. Work around for now, find a better,
        // more efficient way to do this later, e.g. don't use Active Record.
        set_time_limit(2400);

        $qry = ee()->db->select('site_id, entry_id, channel_id, title, entry_date, edit_date, url_title, author_id AS edit_by')
                   ->get('channel_titles');

        // Nothing to migrate
        if ( !$qry->num_rows())
        {
            return;
        }

        // Uh oh, we already have entries in publisher_titles, so don't migrate
        if ( !$updating && ee()->db->count_all_results(ee()->db->dbprefix.'publisher_titles'))
        {
            return;
        }

        $site_pages = ee()->config->item('site_pages');

        if ($qry->num_rows())
        {
            foreach ($qry->result_array() as $row)
            {
                $data  = array();

                $data['site_id']             = "'". $row['site_id'] ."'";
                $data['channel_id']          = "'". $row['channel_id'] ."'";
                $data['entry_id']            = "'". $row['entry_id'] ."'";
                $data['publisher_lang_id']   = $this->default_language_id;
                $data['publisher_status']    = "'". PUBLISHER_STATUS_OPEN ."'";
                $data['title']               = "'". ee()->db->escape_str($row['title']) ."'";
                $data['url_title']           = "'". $row['url_title'] ."'";

                // If installing on a site with structure, grab the last segment value
                if (isset($site_pages[$row['site_id']]['uris'][$row['entry_id']]))
                {
                    $uri = trim($site_pages[$row['site_id']]['uris'][$row['entry_id']], '/');

                    // Pages expects the full URI, Structure just gets the slug.
                    if (array_key_exists('structure', ee()->addons->get_installed('modules')))
                    {
                        $uri = array_pop(explode('/', $uri));
                    }
                    else
                    {
                        $uri = '/'.$uri;
                    }

                    $template_id = isset($site_pages[$row['site_id']]['templates'][$row['entry_id']]) ?
                                   $site_pages[$row['site_id']]['templates'][$row['entry_id']] : 0;

                    $data['page_url'] = "'". $uri ."'";
                    $data['template_id'] = "'". $template_id ."'";
                }
                else
                {
                    $data['page_url'] = "''";
                }

                $data['entry_date'] = $row['entry_date'];
                $data['edit_date']  = isset($row['edit_date']) ? $row['edit_date'] : "''";
                $data['edit_by']    = $row['edit_by'];

                if ($updating)
                {
                    $where = array_merge(array(
                        'site_id' => $row['site_id'],
                        'channel_id' => $row['channel_id'],
                        'entry_id' => $row['entry_id']
                    ), $where);

                    $this->insert_or_update('publisher_titles', $data, $where);
                }
                else
                {
                    $query = "INSERT INTO `". ee()->db->dbprefix ."publisher_titles` (".implode(', ', array_keys($data)).") VALUES (".implode(', ', array_values($data)).")";
                    ee()->db->query($query);
                }
            }
        }

        // Now handle the channel_data
        $qry = ee()->db->get($this->data_table_source);

        if ($qry->num_rows())
        {
            $columns = array();
            $custom_fields = array();

            foreach ($qry->result_array() as $row)
            {
                $data  = array();

                // Move all our current/default data to custom table
                $data['entry_id']           = $row['entry_id'];
                $data['site_id']            = $row['site_id'];
                $data['channel_id']         = $row['channel_id'];
                $data['publisher_lang_id']  = $this->default_language_id;
                $data['publisher_status']   = "'". PUBLISHER_STATUS_OPEN ."'";

                foreach ($row as $field => $value)
                {
                    if ( !in_array($field, $custom_fields) AND $this->is_custom_field($field))
                    {
                        $custom_fields[] = $field;
                    }

                    if (in_array($field, $custom_fields))
                    {
                        if ( !in_array($field, $columns))
                        {
                            $this->add_column($field);
                            $columns[] = $field;
                        }

                        $data[$field] = ($value ? "'". ee()->db->escape_str($value) ."'" : "''");
                    }
                }

                if ($updating)
                {
                    $where = array_merge(array(
                        'site_id' => $row['site_id'],
                        'channel_id' => $row['channel_id'],
                        'entry_id' => $row['entry_id']
                    ), $where);

                    $this->insert_or_update('publisher_data', $data, $where);
                }
                else
                {
                    $sql = "INSERT INTO `". ee()->db->dbprefix ."publisher_data` (".implode(', ', array_keys($data)).") VALUES (".implode(', ', array_values($data)).")";
                    ee()->db->query($sql);
                }
            }
        }

        // Only copy site_pages array if we're installing for the first time, not updating.
        if ( !$updating)
        {
            // Copy the site_pages to our table
            $insert_data = array(
                'site_id' => ee()->publisher_lib->site_id,
                'publisher_lang_id' => $this->default_language_id,
                'publisher_status' => PUBLISHER_STATUS_OPEN,
                'site_pages' => json_encode($site_pages)
            );

            ee()->db->insert('publisher_site_pages', $insert_data);
        }
    }
}