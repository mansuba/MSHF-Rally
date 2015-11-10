<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Driver Class
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

class Publisher_drivers {

    public $drivers = array();

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        if(count($this->drivers)) return;

        // find installed drivers - the main library of a driver
        // always has the same name as the driver filename
        $drivers = array();
        $fieldtypes = array();

        $drivers_dirs = array(
            PATH_THIRD.'publisher/drivers/',        // Drivers packaged with the add-on
            PATH_THIRD.'publisher_drivers/'         // Third party drivers
        );

        $drivers = array();
        foreach($drivers_dirs as $dir)
        {
            $drivers = $drivers + $this->load_dir($dir);
        }

        // check each driver for a main class
        foreach($drivers as $driver => $info)
        {
            if(file_exists($info['file']))
            {
                include $info['file'];

                if (isset($info['class']))
                {
                    $class = $info['class'];
                }

                if(class_exists($class))
                {
                    $this->drivers[strtolower($driver)] = new $class;
                    $this->drivers[strtolower($driver)]->driver_file = $info['file'];
                }
            }
        }
    }

    public function load_dir($dir)
    {
        $drivers = array();

        if (file_exists($dir) && $dh = opendir($dir))
        {
            while (($file = readdir($dh)) !== false)
            {
                if($file[0] != '.')
                {
                    if(is_dir($dir.$file))
                    {
                        $drivers = $drivers + $this->load_dir($dir.$file);
                    }
                    else
                    {
                        if(substr($file, -11) == '_driver.php')
                        {
                            $drivers[$file] = array(
                                'file' => $dir.$file
                            );

                            $info = pathinfo($drivers[$file]['file']);
                            if(isset($info['extension']))
                            {
                                // remove the extension from the filename, then uppercase the first letter to get the class name
                                $class = ucwords(str_replace('.php', '', $info['basename']));
                                $drivers[$file]['class'] = $class;
                            }
                        }
                    }
                }
            }
        }

        return $drivers;
    }

    public function driver_is_installed($driver)
    {
        return array_key_exists($driver, $this->drivers);
    }

    public function get_drivers($type = FALSE, $as_select_options = FALSE)
    {
        $result = array();

        if ($as_select_options)
        {
            foreach($this->drivers as $driver)
            {
                if($type === FALSE || in_array($type, $driver->type))
                {
                    $result[$driver->meta['key']] = $driver->meta['name'];
                }
            }
        }
        else
        {
            foreach($this->drivers as $driver)
            {
                if($type === FALSE || in_array($type, $driver->type))
                {
                    $result[] = $driver;
                }
            }
        }

        return $result;
    }

    public function get_driver($key)
    {
        $result = FALSE;

        foreach($this->drivers as $driver)
        {
            if(isset($driver->meta['key']) && $driver->meta['key'] == $key)
            {
                $result = $driver;
                break;
            }
        }

        return $result;
    }

    public function lang($key)
    {
        foreach($this->drivers as $driver)
        {
            if(isset($driver->lang) && isset($driver->lang[$key]))
            {
                return $driver->lang[$key];
            }
        }
        return lang($key);
    }

    // handle any call to a method (hook) on this class and try to send it to any drivers
    // that provide a definition for it
    public function __call($method, $params)
    {
        return $this->call(FALSE, $method, $params);
    }

    public function call($driver_key, $method, $params=array())
    {
        $CI =& get_instance();

        // get the default result as the last parameter sent to the hook
        $result = array_pop($params);

        // give core libraries a chance to handle the hook
        foreach($CI as $k => $lib)
        {
            if(substr($k, 0, 3) == 'ce_' && method_exists($lib, $method))
            {
                // add previous result (or FALSE if this is the first method that defines this
                // hook) to the params as the last param
                $params[] = $result;

                // call the hook method
                $result = call_user_func_array(array($lib, $method), $params);

                // remove the old result from the params array so we can add the new one on the
                // next loop
                array_pop($params);
            }
        }

        // now send the hook to installed drivers
        foreach($this->drivers as $driver)
        {
            // if we don't care what driver handles this method, or we found the right one, run the
            // hook
            if(!$driver_key || (isset($driver->meta['key']) && $driver->meta['key'] == $driver_key))
            {
                if(method_exists($driver, $method))
                {
                    // see comments above
                    $params[] = $result;
                    $result = call_user_func_array(array($driver, $method), $params);
                    array_pop($params);
                }
            }
        }

        // return the last (or only) result we got from a hook method
        return $result;
    }
}


class Publisher_base_driver {

    public $driver_file;
    public $lang = array();
    public $type = array();
    public $meta = array();

    public function init()
    {
        $info = pathinfo($this->driver_file);
        $this->view_path = $info['dirname'].'/views/';
    }

    public function load($file)
    {
        $drivers_dirs = array(
            PATH_THIRD.'publisher/drivers/',        // Drivers packaged with the add-on
            PATH_THIRD.'publisher_drivers/'         // Third party drivers
        );

        foreach ($drivers_dirs as $dir)
        {
            if (file_exists($dir.$file))
            {
                require_once $dir.$file;
            }
        }
    }

    public function view($view, $vars)
    {
        if(!file_exists($this->view_path.$view.'.php'))
        {
            return "Driver view file does not exist: ".$view.".php";
        }

        extract($vars);
        ob_start();
        include $this->view_path.$view.'.php';
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }

    public function lang($key)
    {
        // First see if there is a real lang entry for the key, if so return it.
        // This allows users and translators to override the built-in entries very
        // easily by simply providing a lang file.
        if(lang($key) != $key)
        {
            return lang($key);
        }
        else
        {
            if(isset($this->lang[$key]))
            {
                return $this->lang[$key];
            }
            else
            {
                return $key;
            }
        }
    }

    public function call($method, $params)
    {
        if(method_exists($this, $method))
        {
            return call_user_func_array($method, $params);
        }
        return FALSE;
    }

    public function __call($method, $params)
    {
        if(method_exists($this, $method))
        {
            return call_user_func_array($method, $params);
        }
        return FALSE;
    }

}
