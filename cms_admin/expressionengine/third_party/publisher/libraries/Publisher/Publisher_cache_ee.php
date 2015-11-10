<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Library Class
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

class Publisher_cache_ee
{
    /**
     * @param  string $key
     * @return string
     */
    public function get($key)
    {
        if (REQ == 'CP')
        {
            return FALSE;
        }

        return ee()->cache->get($this->get_key($key));
    }

    /**
     * Save to cache
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function save($key, $value, $lifetime = NULL)
    {
        $lifetime = $lifetime ? $lifetime : ee()->publisher_setting->cache_time();

        ee()->cache->save($this->get_key($key), $value, $lifetime);
    }

    /**
     * Bust the cache, either by key or all of it if no key is defined
     *
     * @param  string $key
     * @return void
     */
    public function delete($key = NULL)
    {
        if ($key)
        {
            ee()->cache->delete($this->get_key($key));
        }
        else
        {
            ee()->cache->delete('publisher/');
        }
    }

    /**
     * Set the key prefix
     *
     * @param  string $key
     * @return string
     */
    private function get_key($key)
    {
        $prefix = '';

        if (ee()->publisher_lib->lang_id)
        {
            $prefix = 'publisher/lang/'.ee()->publisher_lib->lang_id.'/status/'.ee()->publisher_lib->status.'/';
        }

        return $prefix.$key;
    }
}