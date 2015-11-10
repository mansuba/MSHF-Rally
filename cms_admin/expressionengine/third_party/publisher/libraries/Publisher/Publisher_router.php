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

/**
 * Handle CP requests. In 2.8 the $_GET method was abandoned
 * and it started uses ee->router for CP page actions.
 */
class Publisher_router
{
    /**
     * See if the current Class is the same as the requested
     *
     * @param  string $request
     * @return boolean
     */
    public function class_is($request)
    {
        $class = $this->get_class();

        if ($class == $request)
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * See if the current Method is the same as the requested
     *
     * @param  string $request
     * @return boolean
     */
    public function method_is($request)
    {
        $method = $this->get_method();

        if ($method == $request)
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Grab the requested Class depending on the EE version
     *
     * @return string
     */
    public function get_class()
    {
        if (version_compare(APP_VER, '2.8', '>='))
        {
            return ee()->router->class;
        }
        else
        {
            return ee()->input->get_post('C');
        }
    }

    /**
     * Grab the requested Method depending on the EE version
     *
     * @return string
     */
    public function get_method()
    {
        if (version_compare(APP_VER, '2.8', '>='))
        {
            return ee()->router->method;
        }
        else
        {
            return ee()->input->get_post('M');
        }
    }
}