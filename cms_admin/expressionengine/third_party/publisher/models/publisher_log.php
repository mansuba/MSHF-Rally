<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Category Model Class
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

require PATH_THIRD.'publisher/config.php';

class Publisher_log
{
    public $log_limit = 2;
    public $log_data = array();
    public $output = '';
    public $log_name = 'Publisher';

    private $row_style = 'color:#000; background-color:#ddd; padding:5px';
    private $inner_style = 'border:1px solid #900; padding:6px 10px 10px 10px; margin:20px 20px 0 20px 0; background-color:#eee';
    private $wrapper_style = 'clear:both; background-color:#fff; padding:10px; padding-bottom: 0; margin-top: 10px';
    private $legend_style = 'color:#900;';

    /**
     * Dump something to a text file. Used when debugging on someone's server,
     * or if I need the full request to finish but still trace the data.
     *
     * @param  mixed $data String or Array of whatever
     * @return void
     */
    public function to_file($data)
    {
        $base = APPPATH.'cache/';
        $dir  = 'publisher/';
        $file = 'log.txt';

        if ( !is_dir($base))
        {
            mkdir($base, DIR_WRITE_MODE);
        }

        if ( !is_dir($base.$dir) || (is_dir($base.$dir) && !is_writable($base.$dir)))
        {
            mkdir($base.$dir, DIR_WRITE_MODE);
        }

        $stream = fopen($base.$dir.$file, 'a+' );
        fwrite( $stream, print_r( $data, TRUE) ."\n" );
        fclose( $stream );
    }

    public function to_template($message = NULL)
    {
        if ( !isset(ee()->TMPL))
        {
            return;
        }

        $backtrace = debug_backtrace();

        if (isset($backtrace[1]))
        {
            $caller = $backtrace[1]['class'] .'->'. $backtrace[1]['function'] .'()';
            $caller_line = $backtrace[0]['line'];
            $method = $backtrace[0]['class'] .'->'. $backtrace[0]['function'] .'()';
            $method_line = $backtrace[0]['line'];

            $data = array(
                'caller' => $caller .' line: '. $caller_line,
                'method' => $method .' line: '. $method_line,
                'message' => $message
            );

            $str = implode(' - ', $data);

            ee()->TMPL->log_item($str);
        }
    }

    /**
     * Log key actions for debugging
     *
     * @access  public
     * @param   mixed      $data           Additional data.
     * @param   string     $message        Descriptive text about the data, variable name or anything else helpful.
     * @return  void
     */
    public function message($data = NULL, $message = '')
    {
        $backtrace = debug_backtrace();

        if (isset($backtrace[1]) AND $data AND PUBLISHER_DEBUG)
        {
            $caller = $backtrace[2]['class'] .'->'. $backtrace[2]['function'] .'()';
            $caller_line = $backtrace[1]['line'];
            $method = $backtrace[1]['class'] .'->'. $backtrace[1]['function'] .'()';
            $method_line = $backtrace[0]['line'];

            $data = array(
                'caller' => $caller .' line: '. $caller_line,
                'method' => $method .' line: '. $method_line,
                'message' => $message,
                'data' => $data
            );

            $key = md5(serialize($data));

            if ( !array_key_exists($key, $this->log_data))
            {
                $this->log_data[$key] = $data;
            }
        }
    }

    public function get_output()
    {
        $this->_format();
        return $this->output;
    }

    private function _format()
    {
        $log_data = array(
            'log' => $this->log_data,
            'settings' => ee()->publisher_setting->settings
        );

        $this->output = '<div class="'. strtolower($this->log_name) .'_log" style="'. $this->wrapper_style .'">
            <fieldset style="'. $this->inner_style .'">
                <legend style="'. $this->legend_style .'">'. strtoupper($this->log_name) .'</legend>
                <table width="100%">';

        foreach ($log_data as $type => $data)
        {
            $this->_format_header($type);
            $this->_format_rows($data);
        }

        $this->_format_header('Site Pages');
        $page_data = array();

        foreach(ee()->publisher_model->languages as $lang_id => $data)
        {
            foreach (array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT) as $status)
            {
                $page_data['language: '.$lang_id .' status: '.$status] = ee()->publisher_site_pages->get($lang_id, FALSE, $status, TRUE);
            }
        }

        $this->_format_rows($page_data);

        $this->output .= '</table></fieldset></div>';
    }

    private function _format_header($header)
    {
        $this->output .= '<tr><td colspan="2" style="'. $this->row_style .'"><b style="font-weight: bold;">'. strtoupper($header) .'</b></td></tr>';
    }

    private function _format_rows($data, $key = '')
    {
        foreach ($data as $k => $v)
        {
            if (is_array($v) AND !empty($v))
            {
                $this->_format_header($k);
                $this->_format_rows($v, $k);
            }
            else
            {
                $k = $key == 'data' ? '['. $k .']' : $k;
                $this->output .= '<tr><td style="width: 25%; '. $this->row_style .'">'. $k .'</td><td style="width: 75%; '. $this->row_style .'">'. htmlentities($v) .'</td></tr>';
            }
        }
    }
}