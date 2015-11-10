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

class Publisher_playa_hooks extends Publisher_hooks_base
{
	/**
     * Hook for Playa replace_tag call
     *
     * @param  object $playa
     * @param  string $sql
     * @param  array $where
     * @return query result
     */
    public function playa_fetch_rels_query($playa, $sql, $where)
    {
        // Publisher doesn't support Playa inside Low Vars, so
        // return an unmodified query object.
        preg_match("/rel.parent_var_id = (\d+)/", $sql, $var_id_matches);

        if (isset($var_id_matches[1]))
        {
            return ee()->db->query($sql);
        }

        $publisher_status = ee()->publisher_lib->status;
        $publisher_lang_id = ee()->publisher_lib->lang_id;

        $overrides = FALSE;

        if ( !ee()->publisher_setting->enabled())
        {
            $publisher_status = PUBLISHER_STATUS_OPEN;
            $publisher_lang_id = ee()->publisher_lib->default_lang_id;
        }

        // Allow for overrides on the entries tag
        if (isset(ee()->TMPL) && ee()->TMPL->fetch_param('publisher_status'))
        {
            $overrides = TRUE;
            $publisher_status = ee()->TMPL->fetch_param('publisher_status');
        }

        if (isset(ee()->TMPL) && ee()->TMPL->fetch_param('publisher_lang_id'))
        {
            $overrides = TRUE;
            $publisher_lang_id = ee()->TMPL->fetch_param('publisher_lang_id');
        }

        // Grab the field_id so we can test to see if its ignored before continuing
        preg_match("/rel.parent_field_id = (\d+)/", $sql, $field_id_matches);
        preg_match("/rel.parent_entry_id = (\d+)/", $sql, $entry_id_matches);

        // If its an ignored field or an ignored channel
        if ( !$overrides && isset($field_id_matches[1]) && isset($entry_id_matches[1]))
        {
            $field_id = $field_id_matches[1];
            $entry_id = $entry_id_matches[1];

            if (ee()->publisher_entry->is_ignored($entry_id, $field_id, $publisher_lang_id))
            {
                if (strstr($sql, 'GROUP BY'))
                {
                    return ee()->publisher_query->modify(
                        array('HAVING COUNT(', 'GROUP BY'),
                        array('HAVING COUNT(DISTINCT', ' AND rel.publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND rel.publisher_status = "'. PUBLISHER_STATUS_OPEN .'" GROUP BY'),
                        $sql
                    );
                }
                else
                {
                    return ee()->publisher_query->modify(
                        'ORDER BY',
                        ' AND rel.publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND rel.publisher_status = "'. PUBLISHER_STATUS_OPEN .'" ORDER BY',
                        $sql
                    );
                }
            }
        }

        // If Persistent Playa is set, which in most cases it will be, always
        // go to default language, but change the status.
        if (
            ee()->publisher_setting->persistent_relationships() &&
            !ee()->publisher_lib->is_default_language
        ){
            preg_match('/rel.parent_row_id = (\d+)/', $sql, $row_id_matches);

            // Persistence means default
            $publisher_lang_id = ee()->publisher_lib->default_lang_id;

            // If rel.parent_row_id is in the query string, then its a Playa field inside of
            // a Matrix, so we have to alter the query to basically look for the parent_row_id
            // of the default language entry, thus enabling persistent relationships.
            if (isset($row_id_matches[1]) && isset($entry_id_matches[1]) && isset($field_id_matches[1]) &&
                isset(ee()->session->cache['publisher']['ext']['matrix_rows'][$publisher_lang_id])
            ){
                $cache = ee()->session->cache['publisher']['ext']['matrix_rows'][$publisher_lang_id];

                if (isset($cache[$field_id_matches[1]][$publisher_status][$row_id_matches[1]]))
                {
                    $new_row_id = $cache[$field_id_matches[1]][$publisher_status][$row_id_matches[1]];

                    $sql = str_replace('rel.parent_row_id = '. $row_id_matches[1], 'rel.parent_row_id = '. $new_row_id, $sql);
                }
            }
        }

        // Modify offset & limit
        $sql = $this->modify_limit($sql);

        // Croxton pointed out that when entry_id="1&&2&&3" used in the tag it produces a different query.
        // We need to add the following so it properly finds the entries.
        if (strstr($sql, 'GROUP BY'))
        {
            $query = ee()->publisher_query->modify(
                array('HAVING COUNT(', 'GROUP BY'),
                array('HAVING COUNT(DISTINCT', ' AND rel.publisher_lang_id = '. $publisher_lang_id .' AND rel.publisher_status = "'. $publisher_status .'" GROUP BY'),
                $sql
            );
        }
        else
        {
            $query = ee()->publisher_query->modify(
                'WHERE',
                'WHERE rel.publisher_lang_id = '. $publisher_lang_id .' AND rel.publisher_status = "'. $publisher_status .'" AND ',
                $sql
            );
        }

        // Publisher is disabled, but we still need to get default rows,
        // otherwise it'll show duplicate rows.
        if ( !ee()->publisher_setting->enabled())
        {
            return $query;
        }

        // If no rows were found, and fallback is set, then query default language instead.
        // If persistent relationships, then this should never trigger.
        if(ee()->publisher_setting->show_fallback() && $query->num_rows() == 0)
        {
            if (strstr($sql, 'GROUP BY'))
            {
                $query = ee()->publisher_query->modify(
                    array('HAVING COUNT(', 'GROUP BY'),
                    array('HAVING COUNT(DISTINCT', ' AND rel.publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND rel.publisher_status = "'. $publisher_status .'" GROUP BY'),
                    $sql
                );
            }
            else
            {
                $query = ee()->publisher_query->modify(
                    'WHERE',
                    'WHERE rel.publisher_lang_id = '. $publisher_lang_id .' AND rel.publisher_status = "'. $publisher_status .'" AND ',
                    $sql
                );
            }

            // Still no results?? Get the default language and open status as a last resort.
            // Also used if the entry is in an ignored channel.
            // If we don't have a draft then try harder to find fallback content.
            // If we do have a draft assume the draft had its rows explicitly removed if we've made it this far.
            if (
                (REQ == 'CP' && $query->num_rows() == 0 && !ee()->publisher_entry->has_draft($where['parent_entry_id'], $publisher_lang_id)) ||
                (REQ != 'CP' && $query->num_rows() == 0)
            ){
                if (strstr($sql, 'GROUP BY'))
                {
                    $query = ee()->publisher_query->modify(
                        array('HAVING COUNT(', 'GROUP BY'),
                        array('HAVING COUNT(DISTINCT', ' AND rel.publisher_lang_id = '. ee()->publisher_lib->default_lang_id .' AND rel.publisher_status = "'. PUBLISHER_STATUS_OPEN .'" GROUP BY'),
                        $sql
                    );
                }
                else
                {
                    $query = ee()->publisher_query->modify(
                        'WHERE',
                        'WHERE rel.publisher_lang_id = '. $publisher_lang_id .' AND rel.publisher_status = "'. $publisher_status .'" AND ',
                        $sql
                    );
                }
            }
        }

        // Slice the array to return proper offset/limit
        $query = $this->slice_results($query);

        return $query;
    }

    /**
     * Hook for Publish page
     *
     * @param  object $playa
     * @param  array $where
     * @return array
     */
    public function playa_field_selections_query($playa, $where)
    {
        // Publisher doesn't support Playa inside Low Vars.
        if (isset($where['parent_var_id']))
        {
            $rels = ee()->db->select('child_entry_id')
                        ->where($where)
                        ->order_by('rel_order')
                        ->get('playa_relationships');

            return $rels;
        }

        /* This hook is called in the Publish page, but a
        Safecracker form is also considered a Publish page,
        so here we are. Allow for template overrides. */
        $publisher_status  = ee()->publisher_lib->status;
        $publisher_lang_id = ee()->publisher_lib->lang_id;

        // Allow for overrides on the entries tag when used in Safecracker
        $overrides = FALSE;

        // Allow for overrides on the entries tag
        if (isset(ee()->TMPL) && ee()->TMPL->fetch_param('publisher_status'))
        {
            $overrides = TRUE;
            $publisher_status = ee()->TMPL->fetch_param('publisher_status');
        }

        if (isset(ee()->TMPL) && ee()->TMPL->fetch_param('publisher_lang_id'))
        {
            $overrides = TRUE;
            $publisher_lang_id = ee()->TMPL->fetch_param('publisher_lang_id');
        }

        // If its an ignored field or an ignored channel
        if ( !$overrides && isset($where['parent_entry_id']) && isset($where['parent_field_id']))
        {
            $field_id = $where['parent_field_id'];
            $entry_id = $where['parent_entry_id'];

            if (ee()->publisher_entry->is_ignored($entry_id, $field_id, $publisher_lang_id))
            {
                // Try to find the requested language version
                $where['publisher_lang_id'] = ee()->publisher_lib->default_lang_id;
                $where['publisher_status'] = PUBLISHER_STATUS_OPEN;

                $rels = ee()->db->select('child_entry_id')
                                 ->where($where)
                                 ->order_by('rel_order')
                                 ->get('playa_relationships');

                return $rels;
            }
        }

        $where['publisher_status'] = $publisher_status;

        // If Persistent Playa is set, which in most cases it will be, always
        // go to default language, but change the status.
        if (
            ee()->publisher_setting->persistent_relationships() &&
            !ee()->publisher_lib->is_default_language
        ){
            $where['publisher_lang_id'] = ee()->publisher_lib->default_lang_id;

            if (isset($where['parent_row_id']))
            {
                $cache = isset(ee()->session->cache['publisher']['ext']['matrix_rows'][ee()->publisher_lib->default_lang_id]) ?
                            ee()->session->cache['publisher']['ext']['matrix_rows'][ee()->publisher_lib->default_lang_id] :
                            array();

                $status = ee()->publisher_lib->status;

                // If this is set, then we're looking for data via the real row_id of the default language.
                // Currently $where['parent_row_id'] is the ID of the translated entry, not the default.
                if (isset($cache[$where['parent_field_id']][$status]) && !empty($cache[$where['parent_field_id']][$status]))
                {
                    if (isset($cache[$where['parent_field_id']][$status][$where['parent_row_id']]))
                    {
                        $where['parent_row_id'] = $cache[$where['parent_field_id']][$status][$where['parent_row_id']];
                    }
                }
            }

            $rels = ee()->db->select('child_entry_id')
                         ->where($where)
                         ->order_by('rel_order')
                         ->get('playa_relationships');

            if ($rels->num_rows() == 0)
            {
                $where['publisher_status'] = PUBLISHER_STATUS_OPEN;

                if (isset($where['parent_row_id']) &&
                    isset($cache[$where['parent_field_id']][PUBLISHER_STATUS_OPEN][$where['parent_row_id']])
                ){
                    $where['parent_row_id'] = $cache[$where['parent_field_id']][PUBLISHER_STATUS_OPEN][$where['parent_row_id']];

                    // Here is where it gets real fun! If the default language has a draft that is newer than
                    // the currently viewed translations draft, then we need to get the correct rows to display
                    // in the Playa field in the CP, otherwise it might cause confusion to see a different value
                    // Persistence and all that stuff.
                    if (
                        ee()->publisher_lib->status == PUBLISHER_STATUS_DRAFT &&
                        ee()->publisher_entry->has_draft($where['parent_entry_id'], ee()->publisher_lib->default_lang_id)
                    ){
                        $rows = ee()->session->cache['publisher']['ext']['matrix_rows'][ee()->publisher_lib->default_lang_id][$where['parent_field_id']][PUBLISHER_STATUS_DRAFT];

                        // Reindex to zero so we can grab the first key, regardless of the original
                        // row_id b/c it is invalid at this point in the craziness.
                        $rows_reindexed = array_values($rows);

                        $row_id = $rows_reindexed[0];

                        $where['parent_row_id'] = $row_id;
                        $where['publisher_lang_id'] = ee()->publisher_lib->default_lang_id;
                        $where['publisher_status'] = PUBLISHER_STATUS_DRAFT;

                        // Get the real row_id, which is the array key
                        $key = array_search($row_id, $rows);

                        // Unset it, it should be the first one. Next time we get into this loop
                        // the first key will be the value we need, so we keep dropping the first
                        // index off each time through the loop b/c we just used it.
                        unset(ee()->session->cache['publisher']['ext']['matrix_rows'][ee()->publisher_lib->default_lang_id][$where['parent_field_id']][PUBLISHER_STATUS_DRAFT][$key]);
                    }
                }

                $rels = ee()->db->select('child_entry_id')
                         ->where($where)
                         ->order_by('rel_order')
                         ->get('playa_relationships');


                return $rels;
            }

            return $rels;
        }

        // Try to find the requested language version
        $where['publisher_lang_id'] = $publisher_lang_id;

        $rels = ee()->db->select('child_entry_id')
                         ->where($where)
                         ->order_by('rel_order')
                         ->get('playa_relationships');

        // If no rows were found, and fallback is set, then query default language instead.
        if (ee()->publisher_setting->show_fallback() && $rels->num_rows() == 0)
        {
            $where['publisher_lang_id'] = ee()->publisher_lib->default_lang_id;

            $rels = ee()->db->select('child_entry_id')
                         ->where($where)
                         ->order_by('rel_order')
                         ->get('playa_relationships');

            // Still no results?? Get the default language and open status as a last resort.
            // Also used if the entry is in an ignored channel.
            if (
                (REQ == 'CP' && $rels->num_rows() == 0 && !ee()->publisher_entry->has_draft($where['parent_entry_id'], $publisher_lang_id)) ||
                (REQ != 'CP' && $rels->num_rows() == 0)
            ){
                $where['publisher_status'] = PUBLISHER_STATUS_OPEN;

                $rels = ee()->db->select('child_entry_id')
                             ->where($where)
                             ->order_by('rel_order')
                             ->get('playa_relationships');

            }
        }

        return $rels;
    }

    public function playa_save_rels($playa, $selections, $data)
    {
        return $this->save_row($playa, $data);
    }
}