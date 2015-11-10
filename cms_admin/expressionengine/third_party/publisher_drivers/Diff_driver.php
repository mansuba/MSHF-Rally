<?php

/**
 * ExpressionEngine Publisher Driver Class
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

class Diff_driver extends Publisher_base_driver
{
    public $type = array('diff');
    
    public $meta = array(
        'key'           => 'publisher.diff',
        'name'          => 'Diff - By Chris Boulton',
        'version'       => '1.0',
    );

    public function __construct()
    {
        $this->load('Diff/Diff.php');
        $this->load('Diff/Diff/Renderer/Text/Unified.php');
    }
    
    function get_diff($old, $new)
    {
        $old = str_replace("\n", '', $old);
        $draft = str_replace("\n", '', $new);

        $old = str_replace("&nbsp;", ' ', $old);
        $new = str_replace("&nbsp;", ' ', $new);

        $diff = new Diff(explode(" ", $old), explode(" ", $new));
            
        $renderer = new Diff_Renderer_Text_Unified;
        $str = htmlspecialchars_decode($diff->render($renderer));

        // This library returns a blank string if there is no diff
        $str != '' ? $str : $new;

        return $str;
    }
}