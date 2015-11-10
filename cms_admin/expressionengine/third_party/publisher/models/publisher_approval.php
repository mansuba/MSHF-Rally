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

class Publisher_approval extends Publisher_model {

    protected $settings = array();
    protected $table = 'publisher_approvals';
    protected $type = 'entry';

    public function __construct()
    {
        parent::__construct();

        ee()->load->library('Publisher/Publisher_email');
        $this->settings = ee()->publisher_setting->approval();
    }

    public function count()
    {
        return ee()->db->count_all_results($this->table);
    }

    /**
     * See if an approval exists, don't need data, just confirmation
     *
     * @param  int $id entry_id
     * @return boolean
     */
    public function exists($id, $lang_id = FALSE)
    {
        $lang_id = $lang_id ?: ee()->publisher_lib->lang_id;

        // If someone tries to edit an entry prior to running the update.
        if (version_compare(PUBLISHER_VERSION_INSTALLED, '1.0.10', '<='))
        {
            $qry = ee()->db->where('type', $this->type)
                ->where('type_id', $id)
                ->get($this->table);
        }
        else
        {
            $qry = ee()->db->where('type', $this->type)
                ->where('type_id', $id)
                ->where('publisher_lang_id', $lang_id)
                ->get($this->table);
        }

        if ($qry->num_rows() > 0)
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Mark the approval denied, and send the email
     *
     * @return void
     */
    public function deny()
    {
        $data = array_merge($this->settings, array(
            'to'        => ee()->input->post('email_to', TRUE),
            'reply_to'  => ee()->session->userdata['email'],
            'reply_name'=> ee()->session->userdata['screen_name'],
            'subject'   => ee()->input->post('title', TRUE) .' was denied approval by '. ee()->session->userdata['screen_name'],
            'template'  => ee()->input->post('notes', TRUE),
        ));

        // Send the email
        ee()->publisher_email->send($data);

        // Add the notes to the approval
        ee()->db->where('type_id', ee()->input->post('type_id', TRUE))
            ->where('type', ee()->input->post('type', TRUE))
            ->where('publisher_lang_id', ee()->publisher_lib->lang_id)
            ->update('publisher_approvals', array(
                'notes' => ee()->input->post('notes', TRUE)
            ));
    }

    /**
     * Delete an existing approval
     *
     * @param  int $entry_id
     * @return void
     */
    public function delete($id, $type = FALSE)
    {
        $type = $type ?: $this->type;

        ee()->db->where('type', $type)
            ->where('type_id', $id)
            ->where('publisher_lang_id', ee()->publisher_lib->lang_id)
            ->delete($this->table);
    }

    /**
     * Validate that the current user needs to send approvals, and the approval flag is checked.
     *
     * @return  void
     */
    protected function validate()
    {
        if (ee()->publisher_role->current != ROLE_PUBLISHER && (ee()->input->post('publisher_flag') || ee()->input->post('send_approval')))
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Send the email if they pass validation.
     *
     * @return  void
     */
    protected function send()
    {
        if ($this->validate() == TRUE)
        {
            $send_email = TRUE;
            $save_to = '';

            if (isset($this->settings['to']) && $this->settings['to'] != '')
            {
                $addresses = array();

                foreach ($this->settings['to'] as $k => $v)
                {
                    // Assume its a member_id
                    if (is_numeric($v))
                    {
                        $member = ee()->publisher_helper->get_member_data($v);

                        if (isset($member->email))
                        {
                            $v = $member->email;
                        }
                    }

                    $addresses[] = $v;
                }

                $this->settings['to'] = $addresses;
                $save_to = $this->settings['to'];
            }

            // Remove this or the swap won't work
            unset($this->settings['to']);

            $this->settings['template'] = ee()->functions->var_swap($this->settings['template'], $this->settings);

            // Add it back, email sending needs it, duh.
            $this->settings['to'] = $save_to;

            // -------------------------------------------
            //  'publisher_approval_send' hook
            //      - Optionally override the email sending. Return FALSE to stop default sending.
            //
                if (ee()->extensions->active_hook('publisher_approval_send'))
                {
                    $send_email = ee()->extensions->call('publisher_approval_send', $this->settings);
                }
            //
            // -------------------------------------------

            if ($send_email)
            {
                ee()->publisher_email->send($this->settings);
            }
        }
    }

    /**
     * Log the approval so it can be retrieved in the CP
     *
     * @param  string $type    entry, phrase, category
     * @param  int    $type_id
     * @param  array  $data    Array of data to be logged
     * @return void
     */
    protected function log($type, $type_id, $lang_id, $data)
    {
        // Make sure we have the data we need
        if ($this->validate() AND $type AND $type_id AND is_array($data) AND !empty($data))
        {
            $data = array(
                'type_id'   => $type_id,
                'type'      => $type,
                'publisher_lang_id' => $lang_id,
                'data'      => json_encode($data),
                'date'      => ee()->localize->now,
                'member_id' => ee()->session->userdata['member_id']
            );

            $where = array(
                'type_id'   => $type_id,
                'type'      => $type,
                'publisher_lang_id' => $lang_id
            );

            ee()->publisher_model->insert_or_update('publisher_approvals', $data, $where);
        }
    }
}