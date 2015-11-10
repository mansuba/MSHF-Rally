<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Role Class
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

class Publisher_role {

    public $current = '';

    private $member_group_id;

    private $roles = array(
        'editor' => array(),
        'publisher' => array()
    );

    private $debug = PUBLISHER_DEBUG_ROLE;
    private $debug_role = ROLE_EDITOR;

    /**
     * Set the current user's role
     * @param object $session reference to the EE session object
     * @return void
     */
    public function set()
    {
        $this->member_group_id = ee()->session->userdata['group_id'];

        // For debugging/testing, fake the current role.
        if ($this->debug === TRUE)
        {
            $this->current = $this->debug_role;
            return;
        }

        // Set Super Admins to highest role
        if ($this->member_group_id == 1)
        {
            $this->current = ROLE_PUBLISHER;
            return;
        }

        $this->get_roles();

        foreach ($this->roles as $role => $groups)
        {
            if (in_array($this->member_group_id, $groups))
            {
                $this->current = $role;
                return;
            }
        }

        // If no role was found, default to editor
        $this->current = ROLE_EDITOR;
    }

    /**
     * Get the possible roles from the settings
     * @return void
     */
    public function get_roles()
    {
        $roles = ee()->publisher_setting->roles();

        foreach ($this->roles as $type => $data)
        {
            if (isset($roles[$type]) AND is_array($roles[$type]))
            {
                $this->roles[$type] = $roles[$type];
            }
        }
    }
}