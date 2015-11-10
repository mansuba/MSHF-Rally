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

class Publisher_channel
{
	/**
	 * Get all the channels by site_id, and return them in an array
	 * indexed by the channel_id, a bit more helpful than the core api method.
	 * @param  integer $site_id
	 * @return array
	 */
	public function get_all($site_id = NULL)
	{
		ee()->load->library('api');
		ee()->api->instantiate('channel_structure');
        $qry = ee()->api_channel_structure->get_channels($site_id);

        if ( !$qry || !$qry->num_rows())
        {
            show_error('Looks like you need to add some channels before configuring Publisher.');
        }

        $channels = array();

        foreach ($qry->result_array() as $row)
        {
        	$channels[$row['channel_id']] = $row;
        }

        return $channels;
	}

    public function get_all_as_options($site_id = NULL)
    {
        $channels = $this->get_all($site_id);
        $options = array();

        foreach ($channels as $channel_id => $row)
        {
            $options[$channel_id] =$row['channel_title'];
        }

        return $options;
    }
}