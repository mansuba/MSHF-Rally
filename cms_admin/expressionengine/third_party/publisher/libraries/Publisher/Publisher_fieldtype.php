<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Fieldtype Class
 *
 * @package     ExpressionEngine
 * @subpackage  Libraries
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

class Publisher_fieldtype
{
    public $has_preprocessed_data = FALSE;

    /**
     * Make sure the requested fieldtype is installed before doing anything
     * @param  string  $type short name of the fieldtype, e.g. matrix, playa, grid, assets
     * @return boolean
     */
    public function is_installed($type)
    {
        // Make sure this is not run during the install process
        if (ee()->publisher_lib->is_installing())
        {
            return FALSE;
        }

        // Cache the query, it'll be run multiple times on a publish page
        if ( !isset(ee()->session->cache['publisher']['installed_fieldtypes']))
        {
            $field_types = array();

            $qry = ee()->db->select('name')->get_where('fieldtypes');

            foreach ($qry->result() as $row)
            {
                $field_types[] = $row->name;
            }

            ee()->session->cache['publisher']['installed_fieldtypes'] = $field_types;

            // Run all the time, make sure these tables are updated.
            $fieldtypes = array('matrix', 'playa', 'assets');

            // Grid makes its appearance in 2.7
            if (version_compare(APP_VER, '2.7', '>='))
            {
                $fieldtypes[] = 'grid';
            }

            $publisher_path = PATH_THIRD . 'publisher/';
            ee()->load->add_package_path($publisher_path);

            foreach ($fieldtypes as $ft)
            {
                $class_name = 'Publisher_'. $ft;

                ee()->load->library('Publisher/fieldtypes/'. $class_name);
                ee()->$class_name = new $class_name();
                ee()->$class_name->install();
            }
        }

        // So Publisher fieldtype classes work
        $type = str_replace('Publisher_', '' , $type);

        if (in_array($type, ee()->session->cache['publisher']['installed_fieldtypes']))
        {
            return TRUE;
        }

        return FALSE;
    }

    public function pre_process($data)
    {
        // This is actually grabbing the data for replace_tag in most/all
        // cases. It doesnt work otherwise.
        ee()->publisher_lib->has_preprocessed_data = TRUE;
        return $this->get_data($data, 'replace');
    }

    public function display_publish_field($data)
    {
        $data = $this->get_data($data, 'display');
        return array($data);
    }

    public function replace_tag($data, $params = '', $tagdata = '')
    {
        $data = $this->get_data($data, 'replace');
        return array($data, $params, $tagdata);
    }

    public function post_save($data)
    {
        // Return unmodified data
        return array($data);
    }

    public function get_data($data, $action = 'display')
    {
        // @todo - cache
        // @todo - add hook here?

        if(FALSE)
        {

        }
        else
        {
            $alias = $this->field_name == 'title' ? 't.' : 'd.';

            $status  = $this->publisher_view_status;
            $lang_id = $this->publisher_lang_id;
            $site_id = ee()->publisher_lib->site_id;

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

            $where = array(
                't.publisher_status'    => $status,
                'd.publisher_status'    => $status,
                't.publisher_lang_id'   => $lang_id,
                'd.publisher_lang_id'   => $lang_id,
                't.entry_id'            => $this->entry_id,
                't.site_id'             => $site_id
            );

            // Make sure we have a field_id_X formatted field_name. Other 3rd party add-ons
            // such as Cartthrob send in the friendly column names and it jacks up the query.
            if (substr($this->field_name, 0, 9) != 'field_id_')
            {
                $this->field_name = ee()->publisher_model->transpose_column_name($this->field_name);
            }

            $qry = ee()->db->select($alias.$this->field_name)
                    ->from(ee()->publisher_entry->title_table .' AS t')
                    ->join(ee()->publisher_entry->data_table .' AS d', 'd.entry_id = t.entry_id')
                    ->where($where)
                    ->get();

            $row = $qry->row();

            // If we've define a fallback, and the requested field is blank,
            // fetch the data from the default language field instead. Meaning if English
            // is our default language, and the Spanish version of the requested field is
            // blank/null, the load the English content instead.
            if(
                ((ee()->publisher_setting->display_fallback() && $action == 'display') ||
                (ee()->publisher_setting->replace_fallback() && $action == 'replace')) &&
                empty($row)
            ){
                $where['t.publisher_lang_id'] = ee()->publisher_lib->default_lang_id;
                $where['d.publisher_lang_id'] = ee()->publisher_lib->default_lang_id;
                $where['t.publisher_status']  = $status;
                $where['d.publisher_status']  = $status;

                $qry = ee()->db->select($alias.$this->field_name)
                        ->from(ee()->publisher_entry->title_table .' AS t')
                        ->join(ee()->publisher_entry->data_table .' AS d', 'd.entry_id = t.entry_id')
                        ->where($where)
                        ->get();

                $row = $qry->row();
            }

            $field_name = $this->field_name;

            // Still empty? Fall back to the native tables. Shouldn't really get to this point,
            // but its here just incase, more of an "oh shit" moment.
            if (empty($row) && !ee()->publisher_entry->has_draft($this->entry_id, $lang_id))
            {
                $where = array(
                    't.entry_id' => $this->entry_id,
                    't.site_id'  => ee()->publisher_lib->site_id
                );

                $qry = ee()->db->select($alias.$this->field_name)
                        ->from('exp_channel_titles AS t')
                        ->join('exp_channel_data AS d', 'd.entry_id = t.entry_id')
                        ->where($where)
                        ->get();

                $row = $qry->row();
            }

            $return_data = ($row && !empty($row) && isset($row->$field_name)) ? $row->$field_name : '';

            // Do we have a file field? Need to get the file string from publisher_data table, e.g. {filedir_1}somefile.jpg
            // then make sure that file actually exists in exp_files still, then get its data, and run it through
            // the parse_field method so it returns the full array of data that ft.file.php is expecting in replace_tag()
            if ($action == 'replace' &&
                ((substr($return_data, 0, 9) == '{filedir_' && ($this->field_type == 'file' || $this->field_type == 'safecracker_file')) ||
                (is_array($return_data) && is_array($return_data[0]) && isset($return_data[0]['file_id']))))
            {
                $file_name = preg_replace('/\{filedir_(\d+)\}/', '', $return_data);

                $qry = ee()->db->where('title', $file_name)
                                    ->get('files');

                ee()->load->library('file_field');

                // Yes, its still valid and was not removed from exp_since it was saved to publisher_dat
                if ($qry->num_rows())
                {
                    $return_data = ee()->file_field->parse_field($return_data);
                }
                // Invalid, for whatever reason, just return the original data
                else
                {
                    $return_data = $data;
                }
            }

            // Replace any {filedir_N} tags that might be in the middle of a text field before we leave
            if ($action == 'replace' && !is_array($return_data) && strstr($return_data, '{filedir_'))
            {
                preg_match_all('/{filedir_(.*?)}/', $return_data, $matches);

                if ( !empty($matches))
                {
                    $upload_paths = ee()->publisher_helper->get_upload_prefs();

                    foreach ($matches[0] as $key => $token)
                    {
                        $dir_id = $matches[1][$key];

                        $return_data = str_replace($token, $upload_paths[$dir_id]['url'], $return_data);
                    }
                }
            }

            // @todo - save to cache here
            // @todo - add hook here?

            return $return_data;
        }

        return array();
    }
}