<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Phrase Model Class
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

class Publisher_phrase extends Publisher_model
{
    private $group_table            = 'publisher_phrase_groups';
    private $phrase_table           = 'publisher_phrases';
    private $phrase_entries_table   = 'publisher_phrase_data';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get a specific phrase, and optionally all translations for it.
     *
     * @param  int $phrase_id        optional
     * @param  string  $status       optional
     * @param  boolean $translations optionally get translated data
     * @return object
     */
    public function get($phrase_id = FALSE, $status = PUBLISHER_STATUS_OPEN, $translations = FALSE)
    {
        if ( !$phrase_id)
        {
            show_error('$phrase_id is required. publisher_phrase.php->get()');
        }

        if ($translations)
        {
            return $this->get_translations($phrase_id);
        }
        else
        {
            $qry = ee()->db->select('up.*, up.id as phrase_id')
                                ->from('publisher_phrases AS up')
                                ->where('up.id', $phrase_id)
                                ->where('up.site_id', ee()->publisher_lib->site_id)
                                ->get();

            return $qry->row() ?: FALSE;
        }
    }

    /**
     * Get all phrases optionally by group with no translations, just phrase data.
     *
     * @param  boolean $group_id optional
     * @param  string  $status   optional
     * @return object
     */
    public function get_all($group_id = FALSE, $status = PUBLISHER_STATUS_OPEN)
    {
        ee()->db->select('up.*, up.id as phrase_id')
                     ->from('publisher_phrases AS up')
                     ->order_by('up.phrase_name', 'asc')
                     ->where('site_id', ee()->publisher_lib->site_id);

        if ($group_id)
        {
            ee()->db->where('up.group_id', $group_id);
        }

        $qry = ee()->db->get();

        $phrases = array();

        foreach ($qry->result() as $phrase)
        {
            $phrase->translation_status = $this->is_translated_formatted($phrase->phrase_id, ee()->publisher_setting->detailed_translation_status());

            $phrases[] = $phrase;
        }

        return !empty($phrases) ? $phrases : FALSE;
    }

    /**
     * MCP ONLY Get all the translations for a phrase, and optionally return only a specific lang_id.
     *
     * @param  boolean $phrase_id optional
     * @param  string  $status    optional
     * @param  int     $lang_id   optional
     * @return array
     */
    public function get_translations($phrase_id = FALSE, $status = PUBLISHER_STATUS_OPEN, $lang_id = FALSE)
    {
        if ( !$phrase_id)
        {
            show_error('$phrase_id is required. publisher_phrase.php->get_translations()');
        }

        $qry = ee()->db->select('upd.*, up.*, up.id as phrase_id, upd.id as row_id')
                ->from('publisher_phrases AS up')
                ->join('publisher_phrase_data AS upd', 'upd.phrase_id = up.id', 'left')
                ->where('up.id', $phrase_id)
                ->where('upd.publisher_status', $status)
                ->where('up.site_id', ee()->publisher_lib->site_id)
                ->get();


        $phrases = array();
        $translations = array();

        foreach ($qry->result() as $row)
        {
            $phrases[$row->publisher_lang_id] = $row;
        }

        if ($lang_id)
        {
            $translations[$lang_id] = isset($phrases[$lang_id]) ? $phrases[$lang_id] : $phrases[ee()->publisher_lib->default_lang_id];
        }
        else
        {
            foreach ($this->get_enabled_languages() as $lang_id => $language)
            {
                // If we have existing phrase data
                if (isset($phrases[$lang_id]))
                {
                    $translations[$lang_id] = $phrases[$lang_id];
                }
                // For some reason we don't have a corresponding phrase_data row
                // create the vars with an empty translation value so the view doesn't bomb.
                else
                {
                    $phrases[$lang_id] = new stdClass();
                    $phrases[$lang_id]->phrase_id = $phrase_id;
                    $phrases[$lang_id]->publisher_lang_id = $lang_id;
                    $phrases[$lang_id]->phrase_value = '';

                    $translations[$lang_id] = $phrases[$lang_id];
                }

                $translations[$lang_id]->text_direction = $this->get_language($lang_id, 'direction');
            }
        }

        return $translations;
    }

    /**
     * Add the current phrases to the early parsed global vars array
     */
    public function set_globals()
    {
        if (($phrases = ee()->publisher_cache->driver->get('phrases')) === FALSE)
        {
            $phrases = $this->get_current();
            ee()->publisher_cache->driver->save('phrases', $phrases);
        }

        ee()->load->library('javascript');

        $phrases_js = array();

        foreach ($phrases as $group_id => $group_data)
        {
            foreach ($group_data as $phrase_id => $phrase)
            {
                ee()->config->_global_vars[ee()->publisher_setting->phrase_prefix() . $phrase->phrase_name] = $phrase->phrase_value;

                // If it has a {path} var in the value, take care of it ourselves,
                // otherwise we get double \\ and the JSON object is fubared.
                // This is the exact code used in the core to parse these vars,
                // doing it here just prevents double slashes.
                if (strpos($phrase->phrase_value, 'path=') !== FALSE)
                {
                    $phrases_js[$phrase->phrase_name] = preg_replace_callback("/".LD."\s*path=(.*?)".RD."/", array(&ee()->functions, 'create_url'), $phrase->phrase_value);
                }
                else
                {
                    $phrases_js[$phrase->phrase_name] = $phrase->phrase_value;
                }
            }
        }

        // $phrases_js = ee()->javascript->generate_json($phrases_js);
        $phrases_js = json_encode($phrases_js);

        ee()->config->_global_vars[ee()->publisher_setting->current_phrases_variable_name()] = $phrases_js;
    }

    /**
     * Get the phrases for the current language
     *
     * @param  int $by_group optional, get a specific group of phrases
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
        else if (isset(ee()->session->cache['publisher']['all_phrases']))
        {
            if ($by_group AND !empty(ee()->session->cache['publisher']['all_phrases']))
            {
                return ee()->session->cache['publisher']['all_phrases'][$by_group];
            }
            else
            {
                return ee()->session->cache['publisher']['all_phrases'];
            }
        }
        else
        {
            // Get defaults first, assume open, and get default language
            $where = array(
                'upd.publisher_status'    => PUBLISHER_STATUS_OPEN,
                'upd.publisher_lang_id'   => ee()->publisher_lib->default_lang_id,
                'up.site_id'              => ee()->publisher_lib->site_id
            );

            $key = 'phrases:get_current:'. md5(serialize($where));

            // Save the default query to session
            if ( !isset(ee()->session->cache['publisher']['phrases'][$key]))
            {
                $qry = ee()->db->select('upd.*, up.*, up.id as phrase_id, upd.id as row_id')
                            ->from('publisher_phrases AS up')
                            ->join('publisher_phrase_data AS upd', 'upd.phrase_id = up.id', 'left')
                            ->where($where)
                            ->get();

                ee()->session->cache['publisher']['phrases'][$key] = $qry;
                ee()->session->cache['publisher']['phrases']['languages'] = NULL;

                // If its an MSM site, we need to grab the language phrases from site #1
                if (ee()->publisher_lib->site_id != 1)
                {
                    $old_where = $where;

                    $where['up.site_id'] = 1;
                    $where['up.group_id'] = 2;

                    $qry = ee()->db->select('upd.*, up.*, up.id as phrase_id, upd.id as row_id')
                                ->from('publisher_phrases AS up')
                                ->join('publisher_phrase_data AS upd', 'upd.phrase_id = up.id', 'left')
                                ->where($where)
                                ->get();

                    ee()->session->cache['publisher']['phrases']['languages'] = $qry;

                    $where = $old_where;
                }
            }

            $qry = ee()->session->cache['publisher']['phrases'][$key];

            ee()->session->cache['publisher']['all_phrases'] = array();
            ee()->session->cache['publisher']['translated_phrases'] = array();

            foreach ($qry->result() as $phrase)
            {
                ee()->session->cache['publisher']['all_phrases'][$phrase->group_id][$phrase->phrase_id] = $phrase;
            }

            $where['upd.publisher_status'] = ee()->publisher_lib->status;
            $where['upd.publisher_lang_id'] = ee()->publisher_lib->lang_id;

            // If the soon to be run translated query is the same as the default
            // use it instead. This will be the case when viewing the site in the
            // default language and open/published status. Saves 1 whole query :(
            if ($key == 'phrases:get_current:'. md5(serialize($where)))
            {
                $qry = ee()->session->cache['publisher']['phrases'][$key];
            }
            else
            {
                $qry = ee()->db->select('upd.*, up.*, up.id as phrase_id, upd.id as row_id')
                            ->from('publisher_phrases AS up')
                            ->join('publisher_phrase_data AS upd', 'upd.phrase_id = up.id', 'left')
                            ->where($where)
                            ->get();
            }

            foreach ($qry->result() as $phrase)
            {
                ee()->session->cache['publisher']['translated_phrases'][$phrase->group_id][$phrase->phrase_id] = $phrase;
            }

            foreach (ee()->session->cache['publisher']['all_phrases'] as $group_id => $group)
            {
                foreach ($group as $phrase_id => $phrase)
                {
                    if (
                        isset(ee()->session->cache['publisher']['translated_phrases'][$group_id][$phrase_id]->phrase_value) AND
                        ee()->session->cache['publisher']['translated_phrases'][$group_id][$phrase_id]->phrase_value != ''
                    ){
                        ee()->session->cache['publisher']['all_phrases'][$group_id][$phrase_id] = ee()->session->cache['publisher']['translated_phrases'][$group_id][$phrase_id];
                    }
                }
            }

            if (ee()->session->cache['publisher']['phrases']['languages'])
            {
                foreach (ee()->session->cache['publisher']['phrases']['languages']->result() as $phrase)
                {
                    ee()->session->cache['publisher']['all_phrases'][$phrase->group_id][$phrase->phrase_id] = $phrase;
                }
            }

            //  @todo - save to cache
            //  ee()->session->cache['publisher']['all_phrases']

            if ($by_group)
            {
                return ee()->session->cache['publisher']['all_phrases'][$by_group];
            }
            else
            {
                return ee()->session->cache['publisher']['all_phrases'];
            }
        }
    }

    /**
     * Get a phrase group
     *
     * @param  int $group_id
     * @param  string $return_id Do we just want the ID?
     * @return database result object
     */
    public function get_group($group_id = FALSE, $return_id = FALSE)
    {
        // If no category group was specified, get the first one found.
        // This is used in the CP Category management landing page.
        if (is_numeric($group_id))
        {
            $qry = ee()->db->where('id', $group_id)
                    ->get($this->group_table);
        }
        else
        {
            $qry = ee()->db->limit(1)
                    ->where('site_id', ee()->publisher_lib->site_id)
                    ->get($this->group_table);
        }

        return $qry->num_rows() ? ($return_id ? $qry->row('id') : $qry->row()) : FALSE;
    }

    /**
     * Get all phrase groups
     *
     * @return array
     */
    public function get_groups()
    {
        $qry = ee()->db->where('site_id', ee()->publisher_lib->site_id)
                       ->order_by('group_name', 'asc')
                       ->get($this->group_table);

        $groups = array();

        if ($qry->num_rows())
        {
            foreach ($qry->result() as $group)
            {
                $groups[$group->id] = $group;
            }
        }

        return $groups;
    }

    /**
     * Get all groups and phrases in each group
     *
     * @return mixed
     */
    public function get_by_group($formatted = FALSE)
    {
        $groups = $this->get_groups();
        $phrases = $this->get_all();
        $grouped = array();

        foreach ($groups as $group_id => $group)
        {
            $grouped[$group_id] = array(
                'value' => $group_id,
                'label' => $group->group_label
            );

            foreach ($phrases as $index => $phrase)
            {
                if ($phrase->group_id == $group_id)
                {
                    $grouped[$group_id]['phrases'][] = array(
                        'value' => $phrase->phrase_id,
                        'label' => $phrase->phrase_name
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

            if ( !isset($group['phrases'])) continue;

            foreach ($group['phrases'] as $phrase_index => $phrase)
            {
                $url = ee()->publisher_helper_cp->mod_link('phrases', array('group_id' => $group['value'] .'#phrase-'. $phrase['value']));

                $out .= '<option value="'. $url .'">'. $phrase['label'] .'</option>';
            }

            $out .= '</optgroup>';
        }

        return $out;
    }

    /**
     * Save a group
     *
     * @param  array  $data optional
     * @return false or newly inserted id
     */
    public function save_group(Array $data = array())
    {
        if ( !empty($data))
        {
            $group_id   = isset($data['group_id']) ? $data['group_id'] : 0;
            $name       = isset($data['group_name']) ? $data['group_name'] : '';
            $label      = isset($data['group_label']) ? $data['group_label'] : '';
        }
        else
        {
            $group_id   = ee()->input->post('group_id');
            $name       = ee()->input->post('group_name');
            $label      = ee()->input->post('group_label');
        }

        $site_id = isset(ee()->publisher_lib->site_id) ? ee()->publisher_lib->site_id : ee()->config->item('site_id');

        $data = array(
            'group_name'    => ($name ? ee()->publisher_helper->create_short_name($name) : ee()->publisher_helper->create_short_name($label)),
            'group_label'   => $label,
            'site_id'       => $site_id
        );

        $where = array(
            'id'            => $group_id
        );

        return $this->insert_or_update($this->group_table, $data, $where);
    }

    /**
     * Get the first phrase group
     *
     * @return integer
     */
    public function get_first_group()
    {
        $min_group_id = ee()->db->select('MIN(id) AS id')
                            ->where('site_id', ee()->publisher_lib->site_id)
                            ->get('publisher_phrase_groups')
                            ->row('id');

        // Doesn't exist and we're not on the default site?
        // Create a new Default group.
        if ( !$min_group_id && ee()->publisher_lib->site_id != 1)
        {
            $min_group_id = $this->create_default(ee()->publisher_lib->site_id);
        }

        return $min_group_id;
    }

    /**
     * Create a new default phrase group for another MSM site
     *
     * @param  integer $site_id
     * @return integer
     */
    public function create_default($site_id = NULL)
    {
        if ($site_id)
        {
            // Add our default group
            ee()->db->insert('publisher_phrase_groups', array(
                'site_id'       => $site_id,
                'group_name'    => 'default',
                'group_label'   => 'Default'
            ));

            return ee()->db->insert_id();
        }

        return FALSE;
    }

    /**
     * Simple check to see if a phrase exists
     * @param  string $phrase
     * @return boolean
     */
    public function exists($phrase)
    {
        $qry = ee()->db->select('id')
                            ->where('phrase_name', $phrase)
                            ->get($this->phrase_table);

        return ($qry->num_rows() == 0 ? FALSE : $qry->row('id'));
    }

    /**
     * Delete a phrase
     * @param  int $phrase_id
     * @return void
     */
    public function delete($phrase_id = FALSE)
    {
        if ($phrase_id)
        {
            ee()->db->where('id', $phrase_id)
                         ->delete($this->phrase_table);

            ee()->db->where('phrase_id', $phrase_id)
                         ->delete($this->phrase_entries_table);

            ee()->publisher_approval->delete($phrase_id, 'phrase');
        }
    }

    /**
     * Delete a phrase group
     * @param  int $group_id
     * @param  int $new_group_id optional
     * @return void
     */
    public function delete_group($group_id = FALSE, $new_group_id =  FALSE)
    {
        if ($group_id)
        {
            ee()->db->where('id', $group_id)
                         ->delete($this->group_table);

            // Reassign
            if ($new_group_id)
            {
                ee()->db->where('group_id', $group_id)
                             ->update($this->phrase_table, array('group_id' => $new_group_id));
            }
            // Delete
            else
            {
                ee()->db->where('group_id', $group_id)
                             ->delete($this->phrase_table);
            }
        }
    }

    /**
     * Save a phrase
     * @param  array  $data
     * @return array
     */
    public function save(Array $data = array())
    {
        // Bust our cache first.
        if ( !ee()->publisher_lib->is_installing())
        {
            ee()->publisher_cache->driver->delete();
        }

        if ( !empty($data))
        {
            $group_id   = isset($data['group_id']) ? $data['group_id'] : 0;
            $phrase_id  = isset($data['phrase_id']) ? $data['phrase_id'] : 0;
            $name       = isset($data['phrase_name']) ? $data['phrase_name'] : '';
            $desc       = isset($data['phrase_desc']) ? $data['phrase_desc'] : '';
            $old_name   = isset($data['old_phrase_name']) ? $data['old_phrase_name'] : '';
        }
        else
        {
            $group_id   = ee()->input->post('group_id');
            $phrase_id  = ee()->input->post('phrase_id');
            $name       = ee()->input->post('phrase_name');
            $desc       = ee()->input->post('phrase_desc');
            $old_name   = ee()->input->post('old_phrase_name');
        }

        // Allow for multiple phrases to be saved at once.
        $names = explode("\n", trim($name));
        $saved_phrases = array();

        foreach ($names as $name)
        {
            $value = isset($data['phrase_value']) ? $data['phrase_value'] : '';

            if (strstr($name, ':'))
            {
                $str = explode(':', $name, 2);

                $name = trim($str[0]);
                $value = trim($str[1]);
            }

            $data = array(
                'site_id'       => ee()->publisher_lib->site_id,
                'group_id'      => $group_id,
                'phrase_name'   => ee()->publisher_helper->create_short_name($name),
                'phrase_desc'   => $desc,
            );

            $where = array(
                'id'            => $phrase_id
            );

            $pid = $this->insert_or_update($this->phrase_table, $data, $where);

            if ( !$old_name)
            {
                // Now insert a row for each language. Uses $cache for install process.
                if (ee()->publisher_lib->is_installing())
                {
                    $languages = ee()->session->cache['publisher']['install']['languages'];
                }
                else
                {
                    $languages = $this->get_enabled_languages();
                }

                foreach ($languages as $lang_id => $language)
                {
                    $data = array(
                        'phrase_id'         => $pid,
                        'publisher_lang_id' => $lang_id,
                        'site_id'           => ee()->publisher_lib->site_id,
                        'publisher_status'  => PUBLISHER_STATUS_OPEN,
                        'edit_date'         => ee()->localize->now,
                        'edit_by'           => ee()->session->userdata['member_id'],
                        'phrase_value'      => ($lang_id == ee()->publisher_lib->default_lang_id ? $value : '')
                    );

                    $where = array(
                        'phrase_id'         => $pid,
                        'publisher_lang_id' => $lang_id
                    );

                    $saved_phrases[] = $this->insert_or_update($this->phrase_entries_table, $data, $where);
                }
            }
        }

        return $saved_phrases;
    }

    /**
     * Save a phrase and all entered translations
     * @return  boolean
     */
    public function save_translation($translation, $status)
    {
        // Bust our cache first.
        ee()->publisher_cache->driver->delete();

        // -------------------------------------------
        //  'publisher_phrase_save_start' hook
        //
            if (ee()->extensions->active_hook('publisher_phrase_save_start'))
            {
                $translation = ee()->extensions->call('publisher_phrase_save_start', $translation, $status);
            }
        //
        // -------------------------------------------

        ee()->load->model('publisher_approval_phrase');

        foreach ($translation as $phrase_id => $data)
        {
            foreach ($data as $lang_id => $phrase_value)
            {
                $data = array(
                    'site_id'           => ee()->publisher_lib->site_id,
                    'phrase_id'         => $phrase_id,
                    'publisher_lang_id' => $lang_id,
                    'publisher_status'  => $status,
                    'edit_date'         => ee()->localize->now,
                    'edit_by'           => ee()->session->userdata['member_id'],
                    'phrase_value'      => $phrase_value
                );

                $where = array(
                    'site_id'           => ee()->publisher_lib->site_id,
                    'phrase_id'         => $phrase_id,
                    'publisher_lang_id' => $lang_id,
                    'publisher_status'  => $status,
                    'site_id'           => ee()->publisher_lib->site_id
                );

                $result = $this->insert_or_update($this->phrase_entries_table, $data, $where);

                if ($lang_id == ee()->publisher_lib->default_lang_id)
                {
                    if ($status == PUBLISHER_STATUS_OPEN)
                    {
                        // Remove an existing approval. If its saved as open, there is nothing to approve.
                        ee()->publisher_approval_phrase->delete($phrase_id, $lang_id);
                    }
                    else
                    {
                        // If the user is not a Publisher, send approvals.
                        ee()->publisher_approval_phrase->save($phrase_id, $data);
                    }
                }

                // Just like entries, save as Draft if we're syncing.
                if ($status == PUBLISHER_STATUS_OPEN AND ee()->publisher_setting->sync_drafts() AND !ee()->publisher_setting->disable_drafts())
                {
                    $where['publisher_status'] = PUBLISHER_STATUS_DRAFT;
                    $data['publisher_status'] = PUBLISHER_STATUS_DRAFT;

                    $this->insert_or_update($this->phrase_entries_table, $data, $where);
                }

                if ( !$result)
                {
                    return $result;
                }
            }
        }

        // -------------------------------------------
        //  'publisher_phrase_save_end' hook
        //
            if (ee()->extensions->active_hook('publisher_phrase_save_end'))
            {
                ee()->extensions->call('publisher_phrase_save_end', $translation, $status);
            }
        //
        // -------------------------------------------

        return TRUE;
    }

    public function is_translated($phrase_id, $detailed = FALSE)
    {
        $languages = ee()->publisher_model->get_enabled_languages();
        $languages_enabled = array_keys($languages);

        $qry = ee()->db->where('phrase_id', $phrase_id)
                ->where('publisher_status', PUBLISHER_STATUS_OPEN)
                ->where_in('publisher_lang_id', $languages_enabled)
                ->where('phrase_value !=', '')
                ->get($this->phrase_entries_table);

        if ( !$detailed && $qry->num_rows() == count($languages_enabled))
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

    public function is_translated_formatted($phrase_id, $detailed = FALSE)
    {
        $status = $this->is_translated($phrase_id, $detailed);

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
     * See if a draft exists for a requested phrase
     * @param  array  $data         Array of data about the phrase
     * @param  boolean $language_id
     * @return boolean
     */
    public function has_draft($phrase_id, $language_id = FALSE)
    {
        // Get by current language or a requested one?
        $language_id = $language_id ?: ee()->publisher_lib->lang_id;

        $where = array(
            'phrase_id'         => $phrase_id,
            'publisher_status'  => PUBLISHER_STATUS_DRAFT,
            'publisher_lang_id' => $language_id
        );

        $qry = ee()->db->select('edit_date')->get_where($this->phrase_entries_table, $where);

        // If we have a draft, see if its newer than the open version.
        if ($qry->num_rows())
        {
            $draft_date = $qry->row('edit_date');

            $where = array(
                'phrase_id'         => $phrase_id,
                'publisher_status'  => PUBLISHER_STATUS_OPEN,
                'publisher_lang_id' => $language_id
            );

            $qry = ee()->db->select('edit_date')->get_where($this->phrase_entries_table, $where);

            $open_date = $qry->row('edit_date') ?: 0;

            if ($draft_date > $open_date)
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * See if a translation exists for the requested phrase
     * @param  array  $data         Array of data about the entry
     * @param  int    $language_id
     * @return boolean
     */
    public function has_translation($data, $language_id = FALSE)
    {
        return TRUE;
    }


}