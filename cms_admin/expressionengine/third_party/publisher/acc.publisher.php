<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Accessory Class
 *
 * @package     ExpressionEngine
 * @subpackage  Accessory
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

class Publisher_acc {

    public $name            = PUBLISHER_NAME;
    public $id              = 'publisher';
    public $version         = PUBLISHER_VERSION;
    public $description     = PUBLISHER_DESC;
    public $sections        = array();
    public $required_by     = array('module');

    /**
     * Constructor
     */
    function __construct()
    {
        ee()->lang->loadfile('publisher');
    }

    function set_sections()
    {
        // Its not installed if this isn't set :/
        if ( !isset(ee()->publisher_helper) || !ee()->publisher_setting->enabled()) return;

        ee()->cp->add_to_head('<link href="'. ee()->publisher_helper->get_theme_url() .'publisher/styles/accessory.css" rel="stylesheet" />');

        $script = '';
        $post_data = isset($_SESSION['publisher_post_data']) ? $_SESSION['publisher_post_data'] : array();

        $entry_id = ee()->input->get('entry_id');
        $channel_id = ee()->input->get('channel_id');

        if (ee()->publisher_setting->draft_previews() && isset($post_data['publisher_save_status']))
        {
            $status = $post_data['publisher_save_status'];

            // Do we have a Structure or Pages entry?
            $url = ee()->publisher_site_pages->get_url($entry_id, $channel_id, $status);

            if ( !$url || is_numeric($url))
            {
                $url = ee()->publisher_template->get_preview($channel_id, TRUE, array(
                    'entry_id' => $entry_id,
                    'url_title' => $post_data['url_title']
                ));
            }

            if ($url)
            {
                if (preg_match('/publisher_status=(\S+)/', $url, $matches))
                {
                    $url = preg_replace('/publisher_status=(\S+)/', 'publisher_status='. $status, $url);
                }
                else
                {
                    if ( !strstr($url, '?'))
                    {
                        $url .= '?publisher_status='. $status;
                    }
                    else
                    {
                        $url .= '&publisher_status='. $status;
                    }
                }

                ee()->cp->add_js_script(array(
                    'plugin' => array('toolbox.expose')
                ));

                $script = '
                    var $links = $("#view_content_entry_links").css({marginTop: 0});
                    var $page = $(".pageContents");
                    var new_html = \'<div class="publisher-preview"> \
                            <div class="publisher-browser-bar"> \
                                <span class="publisher-browser-bar-status">'. lang('publisher_'. $status) .'</span> \
                                <span class="publisher-browser-bar-url"> - <a href="'. $url .'" target="_blank">'. $url .'</a></span> \
                            </div> \
                            <div class="publisher-iframe"> \
                                <iframe src=\"'. $url .'\"></iframe> \
                            </div> \
                        </div>\';

                    $page.html(new_html).prepend($links);
                    $(\'.publisher-preview\').expose({color: \'#000\'});
                ';
            }
        }

        if (isset($post_data['publisher_save_status']))
        {
            $script .= '
                var $edit_link = $("#view_content_entry_links li:first-child a");
                if ($edit_link.length > 0) {
                    var href = $edit_link.attr("href");
                    $edit_link.attr("href", href + "&publisher_status='. $post_data['publisher_save_status'] .'");
                }
            ';
        }

        // Remove it
        unset($_SESSION['publisher_post_data']);

        if ($script != '')
        {
            // Output JS, and remove extra white space and line breaks
            ee()->javascript->output('$(function(){'. preg_replace("/\s+/", " ", $script) .'});');
            ee()->javascript->compile();
        }

        ee()->load->model('publisher_approval_entry');
        ee()->load->model('publisher_approval_phrase');
        ee()->load->model('publisher_approval_category');

        $entries = ee()->publisher_approval_entry->get();
        $phrases = ee()->publisher_approval_phrase->get();
        $categories = ee()->publisher_approval_category->get();

        $vars['total_approvals'] = ee()->publisher_approval->count();

        $vars['sections'][] = ee()->load->view('accessory-section', array(
            'rows'  => $entries,
            'type'  => 'Entries'
        ), TRUE);

        $vars['sections'][] = ee()->load->view('accessory-section', array(
            'rows'  => $phrases,
            'type'  => 'Phrases'
        ), TRUE);

        $vars['sections'][] = ee()->load->view('accessory-section', array(
            'rows'  => $categories,
            'type'  => 'Categories'
        ), TRUE);

        $vars['debug'] = '';

        $this->sections['Pending Approvals'] = ee()->load->view('accessory', $vars, TRUE);

        // Handle the diff template management.
        if(isset(ee()->cp) && ee()->input->get('D') == 'cp' && ee()->input->get('M') == 'field_edit')
        {
            ee()->load->library('Publisher/Publisher_diff');
            ee()->publisher_diff->add_diff_field();
        }
    }
}