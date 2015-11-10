<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Query Model Class
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

class Publisher_query
{
    private $cache_key;

    /**
     * Modify a query string and return a query object
     *
     * @param  string $find
     * @param  string $replace
     * @param  string $sql     Original SQL query string to manipulate
     * @return object          CI Query object
     */
    public function modify($find, $replace, $sql, $debug = FALSE)
    {
        $modified_sql = str_replace($find, $replace, $sql);

        if ($debug === TRUE)
        {
            ee()->publisher_log->to_file('=== BEFORE ===');
            ee()->publisher_log->to_file($sql);
            ee()->publisher_log->to_file('=== AFTER ===');
            ee()->publisher_log->to_file($modified_sql);
        }

        $this->_key($modified_sql);

        return $this->query($modified_sql);
    }

    /**
     * Run the modified query string, or return the cached result if it exists
     *
     * @param  string $sql Modified SQL query string
     * @return object      CI Query Object
     */
    public function query($sql)
    {
        $enabled = ee()->publisher_setting->cache_enabled();

        // First level of caching, persistent up to a defined amount of time.
        // Defaults to file cache, or memcache if user defines it.
        // if ($enabled && ($cache_results = ee()->publisher_cache->driver->get($this->cache_key)) !== FALSE)
        // {
        //     if (isset($cache_results->cache_array)) {
        //         $cache_results->result_array = $cache_results->cache_array;
        //     }

        //     if (isset($cache_results->cache_object)) {
        //         $cache_results->result_object = $cache_results->cache_object;
        //     }

        //     return $cache_results;
        // }

        // if ($enabled)
        // {
        //     $qry = ee()->db->query($sql);
        //     ee()->session->cache['publisher'][$this->cache_key] = $qry;
        //     ee()->session->cache['publisher'][$this->cache_key]->cache_array = $qry->result_array();
        //     ee()->session->cache['publisher'][$this->cache_key]->cache_object = $qry->result_object();
        // }
        // If Publisher's cache is not enabled this is saved for
        // each page requests, so it can help prevent duplicate queries per
        // page load, but its per user and per page load. A slight gain.
        // else
        // {
            if ( !isset(ee()->session->cache['publisher'][$this->cache_key]))
            {
                $qry = ee()->db->query($sql);
                ee()->session->cache['publisher'][$this->cache_key] = $qry;
                ee()->session->cache['publisher'][$this->cache_key]->cache_array = $qry->result_array();
                ee()->session->cache['publisher'][$this->cache_key]->cache_object = $qry->result_object();
            }
        // }

        // ee()->publisher_cache->driver->save($this->cache_key, ee()->session->cache['publisher'][$this->cache_key]);

        return ee()->session->cache['publisher'][$this->cache_key];
    }

    /**
     * Create a cache key to use for each request just incase the
     * same query is being run multiple times per page load.
     *
     * @param  string $string Modified SQL query string
     * @return void
     */
    private function _key($sql)
    {
        $backtrace = debug_backtrace();

        if (isset($backtrace[1]))
        {
            $backtrace = $backtrace[1];
            $file = str_replace('.php', '', end(explode('/', $backtrace['file'])));
            $line = $backtrace['line'];
            $method = preg_replace('/[^a-zA-Z0-9_\/]/', '', strtolower($file.'/'.$line));
        }

        $this->cache_key = 'publisher_query/'.$method.'/'. md5($sql);
    }
}