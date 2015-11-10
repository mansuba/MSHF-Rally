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

class Publisher_approval_category extends Publisher_approval {

    public $link_params = array(
        'C'             => 'addons_modules',
        'M'             => 'show_module_cp',
        'module'        => 'publisher',
        'method'        => 'categories',
        'group_id'      => 0
    );

    public function __construct()
    {
        parent::__construct();
        $this->type = 'category';
    }

    /**
     * Fetch the latest approvals
     *
     * @param  integer $limit How many do we want?
     * @return result array
     */
    public function get($limit = 20, $id = FALSE)
    {
        if ($id) ee()->db->where('type_id', $id);

        $qry = ee()->db->where('type', 'category')
                            ->order_by('date', 'desc')
                            ->limit($limit)
                            ->get($this->table);

        $categories = array();

        if ($qry->num_rows())
        {
            foreach ($qry->result() as $row)
            {
                // Get our JSON data into a usable object
                $row->data = json_decode($row->data);

                // Prep some of the vars for the view
                $row->date = date('m/d/Y \a\t h:ia', $row->date);
                $row->data->member_data = ee()->publisher_helper->get_member_data($row->member_id);

                // Create a link to edit this entry in the CP
                $this->link_params['group_id'] = $row->data->group_id .'#'. $row->type_id;
                $this->link_params['publisher_status'] = PUBLISHER_STATUS_DRAFT;
                $row->link = ee()->publisher_helper_url->get_cp_url($this->link_params);

                $categories[] = $row;
            }
        }

        if ($id && isset($categories[0]))
        {
            $categories = $categories[0];
        }

        return $categories;
    }

    /**
     * Build the $settings property with the appropriate data
     *
     * @param int $phrase_id
     * @param array $data Array of all the posted publish data with the Entry
     * @return  void
     */
    public function save($cat_id, $data)
    {
        if ( !$this->validate())
        {
            return;
        }

        $member_id = ee()->session->userdata['member_id'];
        $member = ee()->publisher_helper->get_member_data($member_id);
        $category = ee()->publisher_category->get($cat_id);

        // Create the keys we will need so all types can use the same view file
        $data['title'] = $category->cat_name;
        $data['group_id'] = $category->group_id;

        $settings = array(
            'approval_type' => $this->type,
            'member_id'     => $member_id,
            'title'         => $data['title'],
            'date'          => date('m/d/Y h:ia', ee()->localize->now),
            'link'          => ee()->publisher_helper_url->get_cp_url($this->link_params),
            'member_name'   => $member->screen_name,
            'member_email'  => $member->email
        );

        $this->settings = array_merge($this->settings, $settings);

        $this->send();
        $this->log('category', $cat_id, $data);
    }
}