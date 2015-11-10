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
 */

class Publisher_structure_hooks extends Publisher_hooks_base
{
    /**
     * Unused
     * @param  array $data
     * @return array
     */
    function structure_get_data_end($data)
    {
        return $data;
    }

    function structure_reorder_end($data, $site_pages)
    {
        ee()->publisher_site_pages->rebuild($data, $site_pages);
    }

    /**
     * Get the custom titles for Structure nav
     * @param  array $custom_titles
     * @return array
     */
    public function structure_create_custom_titles($custom_titles)
    {
        if (isset(ee()->session->cache['publisher']['custom_titles']))
        {
            return ee()->session->cache['publisher']['custom_titles'];
        }
        else
        {
            return $this->create_custom_titles($custom_titles);
        }
    }

    /**
     * Structure hook to modify the query used to fetch the overview
     * renaming query if rename_overview="title"
     * @param  string $sql      Original query from Structure
     * @param  int    $entry_id
     * @return string
     */
    public function structure_get_overview_title($sql, $entry_id)
    {
        $sql = str_replace(
            array(ee()->db->dbprefix.'channel_titles', 'expt.status,', 'WHERE'),
            array(ee()->db->dbprefix.'publisher_titles', '', 'WHERE expt.publisher_lang_id = '. ee()->publisher_lib->lang_id .' AND expt.publisher_status = \''. ee()->publisher_lib->status .'\' AND '),
            $sql);

        return $sql;
    }

    /**
     * Structure hook to modify the page titles to show the proper
     * draft or translated version of the Title.
     * @param  array $results Results array of the navigation
     * @return array
     */
    function structure_get_selective_data_results($results)
    {
        // First see if the title field is ignored for whatever reason
        // if so, then we have no need for any of the following.
        if (ee()->publisher_model->is_ignored_field('title'))
        {
            return $results;
        }

        $lang_id = ee()->publisher_lib->lang_id;

        $status = ee()->input->get('publisher_status')
                ? ee()->input->get('publisher_status')
                : ee()->publisher_lib->status;

        $entry_ids = array();

        foreach ($results as $k => $entry)
        {
            // Ignore the root node.
            if ($entry['entry_id'] != 0)
            {
                $entry_ids[] = $entry['entry_id'];
            }
        }

        if ( !empty($entry_ids))
        {
            $qry = ee()->db->select('entry_id, title')
                    ->from('publisher_titles')
                    ->where('publisher_status', $status)
                    ->where('publisher_lang_id', $lang_id)
                    ->where_in('entry_id', $entry_ids)
                    ->get();

            // Update the titles based on the status and or translated version
            foreach ($qry->result() as $row)
            {
                foreach ($results as $k => $result_row)
                {
                    if ($result_row['entry_id'] == $row->entry_id)
                    {
                        $results[$k]['title'] = $row->title;
                    }
                }
            }

            // Take it a step further, if the channel:title param is used to get
            // the title from another custom field instead of Title, go fetch it.
            //
            // @todo - does not work unless a new hook is added to Structure
            // create_custom_titles() just overrides it entirely.

            if ($custom_titles = ee()->TMPL->fetch_param('channel:title', FALSE))
            {
                $page_titles = $this->create_custom_titles($custom_titles);

                foreach ($results as $k => $result_row)
                {
                    if ($page_titles !== FALSE && array_key_exists($result_row['entry_id'], $page_titles))
                    {
                        $results[$k]['title'] = $page_titles[$result_row['entry_id']];
                    }
                }
            }
        }

        $entries = ee()->publisher_entry->get_all_query($entry_ids);

        foreach ($results as $key => $page)
        {
            // The value in Structure's results will be whatever the last
            // saved value was, regardless of the status or language. So
            // default all entries to visible, then below we'll change it
            // on a per-language/status basis.
            $results[$key]['hidden'] = 'n';

            if ($page['entry_id'] != 0 &&
                !ee()->publisher_entry->has_translation($page['entry_id']) &&
                !ee()->publisher_setting->persistent_entries()
            ){
                unset($results[$key]);
            }

            // Turns out this will not work because the setting in Structure
            // trumps anything we can do here. The modified results are already
            // passed to us, so unless we re-instantiate the entire Structure
            // logic we're screwed.
            if (array_key_exists($page['entry_id'], $entries) &&
                isset($entries[$page['entry_id']]['hide_in_nav']) &&
                $entries[$page['entry_id']]['hide_in_nav'] == 'y'
            ){
                $results[$key]['hidden'] = 'y';
            }
        }

        return $results;
    }

    /**
     * Get page titles from arbitrary custom fields.
     * Shamelessly borrowed from Structure and altered slightly.
     * @param  string $custom_titles channel:field combinations
     * @return array
     */
    private function create_custom_titles($custom_titles)
    {
        if ( !is_array($custom_titles))
        {
            $custom_titles = explode('|', $custom_titles);
        }

        // Load the Channel API
        ee()->load->library('api');
        ee()->api->instantiate('channel_fields');

        $title_fields = array();
        $sql_fields = array();

        if ( !isset(ee()->session->cache['structure']['custom_titles']))
        {
            $qry = ee()->db->select('channel_id, channel_name')
                ->where('site_id', ee()->publisher_lib->site_id)
                ->get('channels');

            foreach ($qry->result_array() as $row)
            {
                ee()->session->cache['structure']['custom_titles'][$row['channel_name']] = $row;
            }
        }

        // An actual channel:field_name pair was defined, so figure out the custom fields to get.
        if ($custom_titles[0] != 'publisher')
        {
            foreach ($custom_titles as $pair)
            {
                if (strstr($pair, ':') === FALSE)
                {
                    return FALSE;
                }

                $exploded = explode(':', $pair);

                // This should never run, but keep it here just incase.
                if ( !isset(ee()->session->cache['structure']['custom_titles'][$exploded[0]]))
                {
                    $qry = ee()->db->select('channel_id, channel_name')
                                    ->where('channel_name', $exploded[0])
                                    ->where('site_id', ee()->publisher_lib->site_id)
                                    ->get('channels');

                    // In-case someone enters a channel that does not exist.
                    if ($qry->num_rows())
                    {
                        ee()->session->cache['structure']['custom_titles'][$exploded[0]] = $qry->row();
                    }
                }

                if ( !empty(ee()->session->cache['structure']['custom_titles'][$exploded[0]]))
                {
                    $title_fields[ee()->session->cache['structure']['custom_titles'][$exploded[0]]['channel_id']] = $exploded[1];
                }
            }

            $c_fields = ee()->api_channel_fields->fetch_custom_channel_fields();

            $c_fields = array_key_exists(ee()->publisher_lib->site_id, $c_fields['custom_channel_fields'])
                        ? $c_fields['custom_channel_fields'][ee()->publisher_lib->site_id]
                        : NULL;

            $sql_fields = array();
            foreach ($title_fields as $channel_id => $field)
            {
                if ( !is_array($c_fields))
                {
                    continue;
                }

                $sql_fields[$channel_id]['field_id'] = array_key_exists($field, $c_fields) ? $c_fields[$field] : FALSE;
                $sql_fields[$channel_id]['field_name'] = array_key_exists($field, $c_fields) ? $field : FALSE;
            }
        }

        // Load up structure
        require_once PATH_THIRD .'structure/sql.structure.php';
        $structure = new Sql_structure;

        $structure_channels = $structure->get_structure_channels('page');
        $add_ch_list = $structure->get_structure_channels('listing');

        if (is_array($add_ch_list))
        {
            $structure_channels += $add_ch_list;
        }

        $sql_channels = implode(',', array_keys($structure_channels));

        $select_statement = array();
        $select_statement_str = '';

        foreach ($sql_fields as $channel_id => $field)
        {
            if ($field['field_id'] !== FALSE)
            {
                $select_statement[] = '(d.field_id_' . $field['field_id'] . ') AS `' . $field['field_name'] . '`';
            }
        }

        if (count($select_statement) == 0 AND $custom_titles[0] != 'publisher')
        {
            return FALSE;
        }

        if ( !empty($select_statement))
        {
            $select_statement_str = implode(',', $select_statement) .",";
        }

        $sql = "SELECT d.channel_id, d.entry_id, ". $select_statement_str ." c_titles.title
                FROM ". ee()->db->dbprefix ."channel_data AS d
                    INNER JOIN ". ee()->db->dbprefix ."channel_titles AS c_titles ON d.entry_id = c_titles.entry_id
                WHERE d.channel_id IN ({$sql_channels})
                AND d.site_id = ". ee()->publisher_lib->site_id;

        $result = ee()->db->query($sql);

        $page_titles = array();
        $entry_ids   = array();
        $publisher_titles = array();

        if ($result->num_rows() > 0)
        {
            foreach ($result->result_array() as $page)
            {
                $entry_ids[] = $page['entry_id'];
            }

            $sql = "SELECT ". $select_statement_str ." pt.title AS `publisher_title`, pt.entry_id, pt.channel_id
                     FROM ". ee()->db->dbprefix ."publisher_titles AS pt
                     JOIN ". ee()->db->dbprefix ."publisher_data AS d
                     ON (pt.entry_id = d.entry_id
                         AND d.publisher_lang_id = pt.publisher_lang_id
                         AND d.publisher_status = pt.publisher_status
                         AND d.site_id = pt.site_id
                         AND d.channel_id = pt.channel_id)
                     WHERE pt.entry_id IN (". implode(',', $entry_ids) .")
                         AND pt.publisher_lang_id = ". ee()->publisher_lib->lang_id ."
                         AND pt.publisher_status = '". ee()->publisher_lib->status ."'
                         AND pt.site_id = ". ee()->publisher_lib->site_id ."
                     GROUP BY pt.entry_id";

            $publisher_result = ee()->db->query($sql);

            // First get any translated fields if they exist and have data.
            if ($publisher_result->num_rows() > 0)
            {
                foreach ($publisher_result->result_array() as $page)
                {
                    if (array_key_exists($page['channel_id'], $sql_fields)
                        && $sql_fields[$page['channel_id']]['field_name'] !== FALSE
                        && $page[$sql_fields[$page['channel_id']]['field_name']] !== NULL
                        && $page[$sql_fields[$page['channel_id']]['field_name']] != ''
                    ){
                        $publisher_titles[$page['entry_id']] = $page[$sql_fields[$page['channel_id']]['field_name']];
                    }
                    else
                    {
                        $publisher_titles[$page['entry_id']] = $page['publisher_title'];
                    }
                }
            }

            // Compare the translated version to the default.
            foreach ($result->result_array() as $page)
            {
                // If we have a translated value use it instead of the default
                if (isset($publisher_titles[$page['entry_id']]) AND $publisher_titles[$page['entry_id']] != '')
                {
                    $page_titles[$page['entry_id']] = $publisher_titles[$page['entry_id']];
                }
                else if (array_key_exists($page['channel_id'], $sql_fields)
                    && $sql_fields[$page['channel_id']]['field_name'] !== FALSE
                    && $page[$sql_fields[$page['channel_id']]['field_name']] !== NULL
                    && $page[$sql_fields[$page['channel_id']]['field_name']] != ''
                ){
                    $page_titles[$page['entry_id']] = $page[$sql_fields[$page['channel_id']]['field_name']];
                }
                else
                {
                    $page_titles[$page['entry_id']] = $page['title'];
                }
            }
        }

        $page_titles = empty($page_titles) ? FALSE : $page_titles;

        // Save it
        ee()->session->cache['publisher']['custom_titles'] = $page_titles;

        return $page_titles;
    }
}