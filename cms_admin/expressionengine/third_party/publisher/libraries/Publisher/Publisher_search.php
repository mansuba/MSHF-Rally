<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Search Class
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

if ( !class_exists('Search'))
{
    require_once APPPATH.'modules/search/mod.search.php';
}

class Publisher_search extends Search {

    /**
     * So we can call the mod.search.php->_build_meta_array() function
     *
     * @return string
     */
    public function build_meta_array($meta)
    {
        $default_meta = array(
            'status'                => '',
            'channel'               => '',
            'category'              => '',
            'search_in'             => '',
            'where'                 => '',
            'show_expired'          => '',
            'show_future_entries'   => '',
            'result_page'           => 'search/results',
            'no_results_page'       => ''
        );

        $meta = serialize(array_merge($default_meta, $meta));

        if ( function_exists('mcrypt_encrypt') )
        {
            $init_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
            $init_vect = mcrypt_create_iv($init_size, MCRYPT_RAND);

            $meta = mcrypt_encrypt(
                MCRYPT_RIJNDAEL_256,
                md5(ee()->db->username.ee()->db->password),
                $meta,
                MCRYPT_MODE_ECB,
                $init_vect
            );
        }
        else
        {
            $meta = $meta.md5(ee()->db->username.ee()->db->password.$meta);
        }

        return base64_encode($meta);
    }

    /**
     * So we can call the mod.search.php->_get_meta_vars() function
     *
     * @return array
     */
    public function get_meta_vars()
    {
        $this->_get_meta_vars();
        return $this->_meta;
    }

    /**
     * Modify the query EE uses to perform a search, and what is caced in exp_search
     *
     * @param string $sql
     * @param string $hash
     *
     * @return string
     */
    public function modify_search_query($sql, $hash)
    {
        // Update the query string with our tables
        $sql = str_replace(
            array(
                'exp_channel_titles',
                'exp_channel_data',
                'exp_publisher_titles.expiration_date',
                'exp_publisher_titles.status',
                'LEFT JOIN exp_publisher_data',
                'WHERE'
            ),
            array(
                'exp_publisher_titles',
                'exp_publisher_data',
                'exp_channel_titles.expiration_date', // Swap it back
                'exp_channel_titles.status', // This too
                'LEFT JOIN exp_channel_titles ON exp_channel_titles.channel_id = exp_channels.channel_id LEFT JOIN exp_publisher_data ',
                'WHERE exp_publisher_titles.publisher_lang_id = '. ee()->publisher_lib->lang_id .'
                   AND exp_publisher_titles.publisher_status = "'. ee()->publisher_lib->status .'" AND'
            ),
            $sql
        );

        // Find initial rows
        $query = ee()->db->query($sql);

        // Do more silly stuff so its similar to how the core searching works.
        $order_by = ( !isset($_POST['order_by'])) ? 'date' : $_POST['order_by'];
        $orderby = ( !isset($_POST['orderby'])) ? $order_by : $_POST['orderby'];

        $end = '';

        switch ($orderby)
        {
            case 'most_comments':
                $end .= " ORDER BY t.comment_total ";
                break;
            case 'recent_comment':
                $end .= " ORDER BY t.recent_comment_date ";
                break;
            case 'title':
                $end .= " ORDER BY pt.title ";
                break;
            default:
                $end .= " ORDER BY t.entry_date ";
                break;
        }

        $order = ( !isset($_POST['sort_order'])) ? 'desc' : $_POST['sort_order'];

        if ($order != 'asc' AND $order != 'desc')
        {
            $order = 'desc';
        }

        $end .= " ".$order;

        $lang_id = ee()->publisher_lib->lang_id;
        $status  = ee()->publisher_lib->status;

        $sql = "SELECT DISTINCT(pt.entry_id), pt.entry_id, pt.channel_id, t.forum_topic_id, t.author_id, t.ip_address, pt.title, pt.url_title, t.status, t.view_count_one, t.view_count_two, t.view_count_three, t.view_count_four, t.allow_comments, t.comment_expiration_date, t.sticky, t.entry_date, t.year, t.month, t.day, t.entry_date, t.edit_date, t.expiration_date, t.recent_comment_date, t.comment_total, t.site_id as entry_site_id,
                w.channel_title, w.channel_name, w.search_results_url, w.search_excerpt, w.channel_url, w.comment_url, w.comment_moderate, w.channel_html_formatting, w.channel_allow_img_urls, w.channel_auto_link_urls, w.comment_system_enabled,
                m.username, m.email, m.url, m.screen_name, m.location, m.occupation, m.interests, m.aol_im, m.yahoo_im, m.msn_im, m.icq, m.signature, m.sig_img_filename, m.sig_img_width, m.sig_img_height, m.avatar_filename, m.avatar_width, m.avatar_height, m.photo_filename, m.photo_width, m.photo_height, m.group_id, m.member_id, m.bday_d, m.bday_m, m.bday_y, m.bio,
                md.*,
                pd.*
            FROM exp_channel_titles             AS t
                LEFT JOIN exp_channels          AS w  ON t.channel_id = w.channel_id
                LEFT JOIN exp_publisher_titles  AS pt ON pt.entry_id = t.entry_id AND pt.publisher_lang_id = {$lang_id} AND pt.publisher_status = '{$status}'
                LEFT JOIN exp_publisher_data    AS pd ON pd.entry_id = t.entry_id AND pd.publisher_lang_id = {$lang_id} AND pd.publisher_status = '{$status}'
                LEFT JOIN exp_members           AS m  ON m.member_id = t.author_id
                LEFT JOIN exp_member_data       AS md ON md.member_id = m.member_id
            WHERE t.entry_id IN (";

        if ($query->num_rows() > 0)
        {
            foreach ($query->result_array() as $row)
            {
                $sql .= $row['entry_id'].',';
            }

            $sql = substr($sql, 0, -1).') '.$end;
        }
        else
        {
            $sql .= '0)';
        }

        return $sql;
    }

    /**
     * Update the query on the results page, make sure its hitting the requested language.
     * The query saved in cache might have a diff lang_id in it if another user generated
     * the query, or the current user switched languages.
     *
     * @param string $sql
     * @param string $hash
     *
     * @return string
     */
    public function modify_result_query($sql, $hash)
    {
        $sql = preg_replace('/publisher_lang_id = (\d+)/', 'publisher_lang_id = '. ee()->publisher_lib->lang_id, $sql);
        $sql = preg_replace("/publisher_status = (\S+)/", 'publisher_status = \''. ee()->publisher_lib->status .'\'', $sql);

        // Run the search query
        $query = ee()->db->query(preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT COUNT(*) AS count FROM ', $sql));
        ee()->session->cache['publisher']['search_total_results'] = $query->num_rows();

        return $sql;
    }
}