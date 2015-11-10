<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Email Class
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

class Publisher_email {

    private $settings = array();

    public function __construct()
    {
        ee()->load->library('email');
    }

    /**
     * Generic email handler
     * @param  Array  $data
     * @return void
     */
    public function send($data)
    {
        $old_wordwrap = ee()->email->wordwrap;
        $old_mailtype = ee()->email->mailtype;

        ee()->email->wordwrap = true;
        ee()->email->mailtype = 'html';

        if ( !is_array($data['to']))
        {
            $addresses = explode("\n", $data['to']);
        }
        else
        {
            $addresses = $data['to'];
        }

        $to = array();

        foreach ($addresses as $address)
        {
            $to[] = trim($address);
        }

        if ( !isset($data['reply_to']) OR $data['reply_to'] == '')
        {
            $data['reply_to'] = ee()->config->item('webmaster_email');
        }

        if ( !isset($data['reply_name']) OR $data['reply_name'] == '')
        {
            $data['reply_name'] = ee()->config->item('webmaster_name');
        }

        ee()->load->library('Publisher/Publisher_parser');

        $subject = ucfirst(ee()->publisher_parser->parse($data['subject'], $data));
        $message = ee()->publisher_parser->parse($data['template'], $data);

        $send_email = TRUE;

        // -------------------------------------------
        //  'publisher_send_email' hook
        //      - Optionally override the email sending. Return FALSE to stop default sending.
        //
            if (ee()->extensions->active_hook('publisher_send_email'))
            {
                $send_email = ee()->extensions->call('publisher_send_email', $to, $subject, $message, $data);
            }
        //
        // -------------------------------------------

        if ($send_email)
        {
            foreach ($to as $address)
            {
                ee()->email->to($address);
                ee()->email->from($data['reply_to'], $data['reply_name']);
                ee()->email->subject($subject);
                ee()->email->message($message);
                ee()->email->set_alt_message(strip_tags($message));
                ee()->email->send();
            }
        }

        ee()->email->wordwrap = $old_wordwrap;
        ee()->email->mailtype = $old_mailtype;

        // ee()->publisher_log->to_file(ee()->email->print_debugger());
        // var_dump($data); echo ee()->email->print_debugger(); die;
    }

    /**
     * DEPRECATED
     *
     * @param  [type] $template [description]
     * @param  [type] $vars     [description]
     * @return [type]           [description]
     */
    private function _parse_template($template, $vars)
    {
        $old_TMPL = FALSE;

        if( !isset(ee()->TMPL))
        {
            ee()->load->library('template');
            ee()->TMPL = new EE_Template();
        }
        else
        {
            $old_TMPL = ee()->TMPL;
        }

        $template = ee()->functions->prep_conditionals($template, array($vars));
        $template = ee()->TMPL->parse_variables($template, array($vars));

        ee()->TMPL->template = '';
        ee()->TMPL->parse($template);
        ee()->TMPL->template = ee()->TMPL->parse_globals(ee()->TMPL->template);

        $template = ee()->TMPL->template;

        if($old_TMPL)
        {
            ee()->TMPL = $old_TMPL;
        }

        return $template;
    }

}