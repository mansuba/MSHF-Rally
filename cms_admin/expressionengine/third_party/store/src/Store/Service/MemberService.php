<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Member_register;
use Store\Model\Member;
use Store\Model\Order;
use Store\StubOutput;

class MemberService extends AbstractService
{
    protected $member_register;
    protected $old_post;
    protected $old_output;

    public function __construct($ee, $member_register = null)
    {
        parent::__construct($ee);

        $this->member_register = $member_register ?: new Member_register;
    }

    /**
     * Process member registration using cart data
     */
    public function register(Order $order)
    {
        if (!$order->member_id && $order->password_hash) {
            $this->fake_post($order);
            $this->fake_output();
            $this->register_member_from_post();
            $this->restore_output();
            $this->restore_post();
            $this->update_member($order);
        }
    }

    /**
     * Fake POST data so we can re-use the Member_register class methods
     */
    public function fake_post(Order $order)
    {
        // save existing post data so we can put it back after
        $this->old_post = $_POST;

        $_POST = array();
        $_POST['email'] = $order->order_email;
        $_POST['username'] = $order->username ?: $order->order_email;
        $_POST['screen_name'] = $order->screen_name ?: $order->order_email;

        // generate random password, we will update it after member is created
        $_POST['password'] = md5(uniqid(mt_rand(), true));
        $_POST['password_confirm'] = $_POST['password'];
    }

    public function restore_post()
    {
        // restore POST data
        $_POST = $this->old_post;
    }

    public function fake_output()
    {
        // fake EE_Output library to prevent rending user message
        $this->old_output = $this->ee->output;
        $this->ee->output = new StubOutput($this->old_output);
    }

    public function restore_output()
    {
        // restore EE_Output library
        $this->ee->output = $this->old_output;
    }

    public function register_member_from_post()
    {
        // skip some of the boring stuff
        $this->ee->config->set_item('use_membership_captcha', 'n');
        $this->ee->config->set_item('require_terms_of_service', 'n');
        $this->ee->config->set_item('secure_forms', 'n');

        // run the member registration process
        $this->member_register->register_member();
    }

    public function update_member(Order $order)
    {
        // find newly created member
        $member = Member::where('email', $order->order_email)->first();
        if ($member) {
            // set member password from hash
            $member->password = $order->password_hash;
            $member->salt = $order->password_salt;
            $member->save();

            // assign order to new member
            $order->member_id = $member->member_id;
            $order->save();
        }
    }

    public function get_member_fields_select()
    {
        $query = $this->ee->db->select('m_field_id, m_field_name, m_field_label')
            ->from('member_fields')->get()->result_array();

        $member_fields = array();
        foreach ($query as $row) {
            $member_fields['m_field_id_'.$row['m_field_id']] = $row['m_field_label'];
        }

        // find Zoo Visitor fields
        if ( ! empty($this->ee->config->_global_vars['zoo_visitor_channel_name'])) {
            $query = $this->ee->db->select('cf.field_id, cf.field_label')
                ->from('exp_channels c')
                ->join('exp_channel_fields cf', 'cf.group_id = c.field_group')
                ->where('channel_name', $this->ee->config->_global_vars['zoo_visitor_channel_name'])
                ->where_not_in('cf.field_type', array('zoo_visitor', 'zoo_plus', 'playa', 'matrix', 'channel_images', 'channel_files'))
                ->get()->result_array();

            $zoo_fields = array();
            foreach ($query as $row) {
                $zoo_fields['field_id_'.$row['field_id']] = $row['field_label'];
            }

            // add zoo optgroup
            if ( ! empty($zoo_fields)) {
                $member_fields = array(lang('store.optgroup_member_fields') => $member_fields);
                $member_fields[lang('store.optgroup_zoo_fields')] = $zoo_fields;
            }
        }

        return array_merge(array('' => ''), array_filter($member_fields));
    }

    public function load_member_data($member_id)
    {
        // Standard member fields
        $member_data = $this->ee->db->where('member_id', $member_id)
            ->get('member_data')->row_array();

        // Zoo Visitor fields
        if ( ! empty($this->ee->config->_global_vars['zoo_visitor_id'])) {
            foreach ($this->ee->config->_global_vars as $key => $value) {
                if (strpos($key, 'visitor:global:field_id_') === 0) {
                    $member_data[str_replace('visitor:global:', '', $key)] = $value;
                }
            }
        }

        return $member_data;
    }

    public function save_member_data($member_id, $data)
    {
        $order_fields = $this->ee->store->config->order_fields();

        // split out standard & channel member fields
        $member_fields = array();
        $channel_fields = array();

        foreach ($order_fields as $field_name => $field) {
            $member_field = $field['member_field'];
            if (strpos($member_field, 'm_field_id_') === 0) {
                $member_fields[$member_field] = $data[$field_name];
            } elseif (strpos($member_field, 'field_id_') === 0) {
                $channel_fields[$member_field] = $data[$field_name];
            }
        }

        // update standard member fields
        if ( ! empty($member_fields)) {
            $this->ee->db->where('member_id', $member_id)
                ->update('member_data', $member_fields);
        }

        // update Zoo Visitor fields
        if ( ! empty($channel_fields) AND ! empty($this->ee->config->_global_vars['zoo_visitor_id']) AND
            $this->ee->config->_global_vars['zoo_member_id'] == $member_id)
        {
            $this->ee->db->where('entry_id', $this->ee->config->_global_vars['zoo_visitor_id'])
                ->update('channel_data', $channel_fields);
        }
    }

}
