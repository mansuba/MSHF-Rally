<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Relationship Model Class
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

class Publisher_relationships extends Publisher_model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function migrate_data($entry_ids = array(), $where = array(), $delete_old = FALSE)
    {
        if (version_compare(APP_VER, '2.6', '>=') && ee()->db->table_exists('relationships'))
        {
            // Delete old rows. delete() resets the where(), so reset them.
            if ($delete_old && !empty($where) && !empty($entry_ids))
            {
                ee()->db->where_in('parent_id', $entry_ids)
                    ->delete('publisher_relationships');
            }

            if ( !empty($entry_ids))
            {
                ee()->db->where_in('parent_id', $entry_ids);
            }

            $qry = ee()->db->get('relationships');

            if ($qry->num_rows())
            {
                foreach ($qry->result_array() as $row)
                {
                    $data = $row;
                    $data['publisher_lang_id']   = $this->default_language_id;
                    $data['publisher_status']    = ee()->publisher_setting->get('default_view_status');

                    $this->insert_or_update('publisher_relationships', $data, $row, 'relationship_id');
                }
            }
        }
    }

    public function uninstall_data()
    {
        if (version_compare(APP_VER, '2.6', '>=') &&
            ee()->db->table_exists('publisher_relationships') &&
            ee()->db->field_exists('publisher_lang_id', 'publisher_relationships')
        ){
            // Grab all the rows prior to blowing everything away.
            $qry = ee()->db->where('publisher_lang_id', $this->default_language_id)
                           ->where('publisher_status', PUBLISHER_STATUS_OPEN)
                           ->get('publisher_relationships');

            // Delete all the native values, b/c by now they are probably a mix of translated and/or draft data
            ee()->db->query('delete from '. ee()->db->dbprefix .'relationships');

            $col_id_exists = ee()->db->field_exists('grid_col_id', 'relationships');

            if ($qry->num_rows())
            {
                $ships = array();

                foreach ($qry->result_array() as $row)
                {
                    unset($row['relationship_id']);
                    unset($row['publisher_lang_id']);
                    unset($row['publisher_status']);

                    // If it doesn't exist in the native table, don't try to add it now.
                    if ( !$col_id_exists && isset($row['grid_col_id']))
                    {
                        unset($row['grid_col_id']);
                    }

                    if ($row['field_id'] == '') continue;

                    $ships[] = $row;
                }

                if ( !empty($ships))
                {
                    ee()->db->insert_batch('relationships', $ships);
                }
            }
        }
    }
}