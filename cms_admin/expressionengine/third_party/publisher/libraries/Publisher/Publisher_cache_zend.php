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

class Publisher_cache_zend
{
    public $zend_cache;

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

        $cache = $this->get_cache();

        return $cache->load($this->get_key($key));
    }

    /**
     * Save to cache
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function save($key, $value)
    {
        $cache = $this->get_cache();

        $cache->save($value, $this->get_key($key));
    }

    /**
     * Bust the cache, either by key or all of it if no key is defined
     *
     * @param  string $key
     * @return void
     */
    public function delete($key = NULL)
    {
        $cache = $this->get_cache();

        if ($key)
        {
            $cache->remove($this->get_key($key));
        }
        else
        {
            $cache->clean(Zend_Cache::CLEANING_MODE_ALL);
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
        $status = ee()->input->post('publisher_save_status') ?
                   ee()->input->post('publisher_save_status') :
                   ee()->publisher_lib->status;

        $lang_id = ee()->input->post('site_language') ?
                   ee()->input->post('site_language') :
                   ee()->publisher_lib->lang_id;

        $prefix = 'lang_'.$lang_id.'_status_'.$status.'_';

        return str_replace('/', '_', $prefix.$key);
    }

    /**
     * Get our Zend Cache object, or create it if doesn't exist.
     *
     * @param  string $type
     * @return object
     */
    public function get_cache()
    {
        if (empty($this->zend_cache))
        {
            if (ee()->publisher_lib->is_installing())
            {
                $cache_type = 'File';
                $cache_time = 0;
                $cache_enabled = FALSE;
            }
            else
            {
                $cache_type = ee()->publisher_setting->cache_type();
                $cache_time = ee()->publisher_setting->cache_time();
                $cache_enabled = ee()->publisher_setting->cache_enabled();
            }

            $this->init_cache($cache_type, $cache_time, $cache_enabled);
        }

        $prefix = '';

        if (ee()->publisher_lib->lang_id)
        {
            $prefix = 'lang_'.ee()->publisher_lib->lang_id.'_status_'.ee()->publisher_lib->status.'_';
        }

        $this->zend_cache->setOption('cache_id_prefix', $prefix);
        // $this->zend_cache->setOption('caching', false);

        return $this->zend_cache;
    }

    /**
     * Initialize our Zend Cache object and set our cache dir.
     * Create the dir if it doesn't exist.
     *
     * @param  string $type
     * @return object
     */
    public function init_cache($cache_type, $cache_time, $cache_enabled)
    {
        set_include_path(
            '.' . PATH_SEPARATOR . PATH_THIRD . 'publisher/libraries'
             . PATH_SEPARATOR . get_include_path()
        );

        require_once PATH_THIRD . 'publisher/libraries/Zend/Cache.php';
        $cache_path = APPPATH . 'cache';
        $publisher_cache_path = $cache_path . '/publisher';

        // Make sure our cache folder exists.
        if ( !is_dir($publisher_cache_path))
        {
            if ( !is_writable($cache_path))
            {
                show_error('The following directory must be writable: '.$cache_path);
            }
            else
            {
                try {
                    mkdir($publisher_cache_path, DIR_WRITE_MODE, TRUE);
                } catch (Exception $e) {
                    show_error('The following directory must be writable: '.$cache_path);
                }
            }
        }

        $this->zend_cache = Zend_Cache::factory(
            'Core',
            $cache_type,
            // Frontend
            array(
                'automatic_serialization' => true,
                'lifetime' => $cache_time
            ),
            // Backend
            array(
                'cache_dir' => $publisher_cache_path,
            )
        );

        if ( !$cache_enabled)
        {
            $this->zend_cache->setOption('caching', false);
        }

        return $this;
    }
}