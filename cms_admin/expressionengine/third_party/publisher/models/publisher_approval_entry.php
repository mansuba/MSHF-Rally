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

class Publisher_approval_entry extends Publisher_approval {

    public $link_params = array(
        'C'             => 'content_publish',
        'M'             => 'entry_form',
        'channel_id'    => 0,
        'entry_id'      => 0
    );

	public function __construct()
	{
		parent::__construct();
        $this->type = 'entry';
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

        $qry = ee()->db->where('type', $this->type)
                ->order_by('date', 'desc')
                ->limit($limit)
                ->get($this->table);

        $entries = array();

        if ($qry->num_rows())
        {
            foreach ($qry->result() as $row)
            {
                // Get our JSON data into a usable object
                $row->data = json_decode($row->data);

                // @TODO - not liking how specific this is... anyway to revise this?
                if (array_key_exists('zoo_visitor', ee()->addons->get_installed('modules')) && $row->member_id == 0)
                {
                    $member_id = isset($row->data->email) ? $row->data->email : FALSE;
                }
                else
                {
                    $member_id = $row->member_id;
                }

                // Prep some of the vars for the view
                $row->date = date('m/d/Y \a\t h:ia', $row->date);
                $row->data->member_data = ee()->publisher_helper->get_member_data($member_id);
                $row->lang_code = '';

                if (isset($row->publisher_lang_id))
                {
                    $row->lang_code = strtoupper(ee()->publisher_model->get_language($row->publisher_lang_id));
                }

                // Parse the deny approval text in the modal with the member vars
                $row->data->deny_template = ee()->publisher_helper->parse_text(ee()->publisher_setting->get('approval[deny_template]'), $row->data->member_data);

                // Create a link to edit this entry in the CP
                $this->link_params['channel_id'] = $row->data->channel_id;
                $this->link_params['entry_id'] = $row->type_id;
                $this->link_params['lang_id'] = isset($row->publisher_lang_id) ? $row->publisher_lang_id : ee()->publisher_lib->default_lang_id;
                $this->link_params['publisher_status'] = PUBLISHER_STATUS_DRAFT;
                $row->link = ee()->publisher_helper_url->get_cp_url($this->link_params);

                $entries[] = $row;
            }
        }

        if ($id && isset($entries[0]))
        {
            $entries = $entries[0];
        }

        return $entries;
    }

    /**
     * Build the $settings property with the appropriate data
     *
     * @param int $entry_id
     * @param array $meta Array of meta data associated with the Entry
     * @param array $data Array of all the posted publish data with the Entry
     * @return  void
     */
	public function save($entry_id, $meta, $data)
    {
        if ( !$this->validate())
        {
            return;
        }

        if ( !isset($meta['channel_id']) || (isset($meta['channel_id']) && $meta['channel_id'] == ''))
        {
            return;
        }

        // Is it a ZV registration form? @TODO - Decouple this.
        if (array_key_exists('zoo_visitor', ee()->addons->get_installed('modules')) && ee()->session->userdata['member_id'] === 0)
        {
            $member_id = isset($data['email']) ? $data['email'] : FALSE;
        }
        else
        {
            $member_id = ee()->session->userdata['member_id'];
        }

        $member = ee()->publisher_helper->get_member_data($member_id);

        if ( !$member) show_error('Approval email can\'t be sent, no valid member found.');

        if (isset($data['revision_post']['channel_id']))
        {
            $this->link_params['channel_id'] = $data['revision_post']['channel_id'];
            $this->link_params['entry_id'] = $data['revision_post']['entry_id'];
        }
        else
        {
            $this->link_params['channel_id'] = $data['channel_id'];
            $this->link_params['entry_id'] = $data['entry_id'];
        }

        $this->link_params['lang_id'] = ee()->publisher_lib->lang_id;
        $this->link_params['publisher_status'] = ee()->publisher_lib->publisher_save_status;

        $settings = array(
            'approval_type' => $this->type,
            'member_id'     => $member_id,
            'title'         => $meta['title'],
            'date'          => date('m/d/Y h:ia', ee()->localize->now),
            'channel_title' => ee()->publisher_helper->get_channel_data($this->link_params['channel_id'], 'channel_title'),
            'link'          => ee()->publisher_helper_url->get_cp_url($this->link_params),
            'member_name'   => $member->screen_name,
            'member_email'  => $member->email
        );

        $this->settings = array_merge($this->settings, $settings);

        $this->send();
        $this->log('entry', $entry_id, ee()->publisher_lib->lang_id, array_merge($meta, $data));
    }
}