<?php

/**
 * ExpressionEngine Publisher Html Diff Driver Class
 *
 * @package     ExpressionEngine
 * @subpackage  Drivers
 * @category    Publisher
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2012, 2013 - Brian Litzinger
 * @link        http://boldminded.com/add-ons/publisher
 * @license 
 *
 * Copyright (c) 2012, 2013. BoldMinded, LLC
 * All rights reserved.
 *
 *
 * This file is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class Htmldiff_driver extends Publisher_base_driver
{
    public $type = array('diff');
    
    public $meta = array(
        'key'           => 'publisher.html_diff',
        'name'          => 'Html Diff - By Candid Dauth',
        'version'       => '1.0',
    );

    public function __construct()
    {
        $this->load('htmldiff/html_diff.php');
    }
    
    public function get_diff($old, $new)
    {   
        if (function_exists('html_diff'))
        {
            return html_diff($old, $new);
        }
        else
        {
            show_error($this->meta['name'] .' needs to be <a href="http://boldminded.com/add-ons/publisher/diffs">downloaded and installed</a>.');
        }
    }
}