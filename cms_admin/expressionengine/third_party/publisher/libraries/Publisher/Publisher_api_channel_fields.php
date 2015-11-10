<?php

/**
 * ExpressionEngine Publisher API Channel Fields Class
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

class Publisher_api_channel_fields extends Api_channel_fields
{
    /**
     * Overwrite method. As of 2.7 I could use this hook, but things might be changing
     * again due to the new content_types, so I'm sitting tight until I see how the API
     * evolves before refactoring parts of Publisher.
     */
    function apply($method, $parameters = array())
    {
        $_ft_path = $this->ft_paths[$this->field_type];

        ee()->load->add_package_path($_ft_path, FALSE);

        $ft =& $this->field_types[$this->field_type];

        $enabled = (isset(ee()->TMPL) && ee()->TMPL->fetch_param('disable_publisher') === 'yes') ? FALSE : TRUE;

        // Only if its a CP or ACTION request do we go this route.
        // ACTION is usually a Safecracker submission.
        // show_module_cp is for add-ons, such as Cartthrob, which do custom template parsing via CP actions.
        if (isset(ee()->publisher_lib) && $enabled && REQ != 'PAGE' && !ee()->publisher_router->method_is('show_module_cp'))
        {
            $parameters = ee()->publisher_lib->apply($this, $ft, $method, $parameters);
        }

        // Hook only available in 2.7+
        // Data is universally the first parameter
        if (version_compare(APP_VER, '2.7', '>=') && count($parameters))
        {
            $parameters = $this->custom_field_data_hook($ft, $method, $parameters);
        }

        $res = call_user_func_array(array(&$ft, $method), $parameters);

        ee()->load->remove_package_path($_ft_path);

        return $res;
    }
}
