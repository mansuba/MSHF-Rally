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

class Publisher_member extends Member_model
{
    /**
     * Get Member Groups
     *
     * Returns only the title and id by default, but additional fields can be passed
     * and automatically added to the query either as a string, or as an array.
     * This allows the same function to be used for "lean" and for larger queries.
     *
     * @access  public
     * @param   array
     * @param   array   array of associative field => value arrays
     * @return  mixed
     */
    function get_member_groups($additional_fields = array(), $additional_where = array(), $limit = '', $offset = '')
    {
        if ( !is_array($additional_fields))
        {
            $additional_fields = array($additional_fields);
        }

        if ( !isset($additional_where[0]))
        {
            $additional_where = array($additional_where);
        }

        if (count($additional_fields) > 0)
        {
            $this->db->select(implode(',', $additional_fields));
        }

        $this->db->select("group_id, group_title");
        $this->db->from("member_groups");
        $this->db->where("site_id", $this->config->item('site_id'));
        $this->db->where('can_access_cp', 'y');
        $this->db->where("group_id != ", 1);

        if ($limit != '')
        {
            $this->db->limit($limit);
        }

        if ($offset !='')
        {
            $this->db->offset($offset);
        }

        foreach ($additional_where as $where)
        {
            foreach ($where as $field => $value)
            {
                if (is_array($value))
                {
                    $this->db->where_in($field, $value);
                }
                else
                {
                    $this->db->where($field, $value);
                }
            }
        }

        $qry = $this->db->order_by('group_id, group_title')->get();

        $list = array();

        foreach ($qry->result() as $row)
        {
            $list[$row->group_id] = $row->group_title;
        }

        return $list;
    }

    /**
     * Get Memmbers
     *
     * Get a collection of members
     *
     * @access  public
     * @param   int
     * @param   int
     * @param   int
     * @param   string
     * @return  mixed
     */
    function get_members($ignore = array())
    {
        $this->db->select('members.member_id, members.group_id, members.username, members.screen_name, members.in_authorlist');
        $this->db->from('members');
        $this->db->join('member_groups', 'member_groups.group_id = members.group_id');

        $this->db->where('member_groups.can_access_cp', 'y');
        $this->db->where('members.group_id = '.$this->db->dbprefix('member_groups').'.group_id');
        $this->db->where('member_groups.site_id', $this->config->item('site_id'));

        $this->db->order_by('members.screen_name', 'ASC');
        $this->db->order_by('members.username', 'ASC');

        if ( !empty($ignore))
        {
            $this->db->where_not_in('members.group_id', $ignore);
        }

        $qry = $this->db->get();

        if ($qry->num_rows() == 0)
        {
            return array();
        }
        else
        {
            $members = array();

            foreach ($qry->result() as $row)
            {
                $members[$row->member_id] = $row->screen_name;
            }

            return $members;
        }
    }
}