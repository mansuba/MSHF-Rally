<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Phrase Model Class
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

class Publisher_language extends Publisher_model
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Get a specific language
     *
	 * @param  boolean $lang_id
	 * @return object
	 */
	public function get($lang_id = FALSE)
    {
        if ( !$lang_id)
        {
            show_error('$lang_id is required. publisher_language.php->get()');
        }

        $qry = ee()->db->get_where('publisher_languages', array('id' => $lang_id));

        $row = FALSE;

        if ($row = $qry->row())
        {
            $row->sites = json_decode($row->sites);
        }

        return $row;
    }

    /**
     * Get all languages
     *
     * @return object
     */
    public function get_all()
    {
        $qry = ee()->db->get('publisher_languages');

        return $qry->num_rows() ? $qry->result() : FALSE;
    }

    /**
     * Save the language
     *
     * @return void
     */
    public function save()
    {
        // category URL indicator must have a value, if blank use default
        $category_url_indicator = ee()->input->post('cat_url_indicator') ?: ee()->config->item('reserved_category_word');

        $data = array(
            'long_name' => ee()->input->post('long_name'),
            'short_name' => ee()->input->post('short_name'),
            'language_pack' => ee()->input->post('language_pack'),
            'cat_url_indicator' => $category_url_indicator,
            'is_default' => (ee()->input->post('is_default') ? 'y' : 'n'),
            'is_enabled' => ee()->input->post('is_enabled'),
            'direction' => ee()->input->post('direction'),
            'sites' => json_encode(ee()->input->post('sites'))
        );

        $where = array(
            'id' => ee()->input->post('language_id')
        );

        // Make sure no other languages are set as default too
        if ($data['is_default'] == 'y')
        {
            ee()->db->update('publisher_languages', array(
                'is_default' => 'n'
            ));

            $sites = ee()->publisher_model->get_sites();
            $site_options = array();

            foreach ($sites as $site_id => $site)
            {
                $site_options[] = (string) $site_id;
            }

            $data['sites'] = json_encode($site_options);
        }

        $insert_id = $this->insert_or_update('publisher_languages', $data, $where);

        // Also save it as a phrase, so the language name itself can be translated
        if ( !ee()->publisher_phrase->exists('language_'. $data['short_name']))
        {
            ee()->publisher_phrase->save(array(
                'group_id'      => 2,
                'phrase_name'   => 'language_'. $data['short_name'],
                'phrase_value'  => $data['long_name']
            ));
        }

        // One more check to make sure we have a default language
        // its possible to accidently not have a defealt lang
        $qry = ee()->db->where('is_default', 'y')
                       ->get('publisher_languages');

        // We have no default languages, lets fix that
        if ( !$qry->num_rows())
        {
            // Get the top one
            $qry = ee()->db->limit(1)
                           ->order_by('id', 'asc')
                           ->get('publisher_languages');

            $this->insert_or_update('publisher_languages', array('is_default' => 'y'), array('id' => $qry->row('id')));
        }

        ee()->publisher_cache->driver->delete('languages');
    }

    /**
     * Delete a language
     *
     * @param  int $language_id
     * @return void
     */
    public function delete($language_id = FALSE)
    {
        if ($language_id)
        {
            ee()->db->where('id', $language_id)
                    ->delete('publisher_languages');
        }

        ee()->publisher_cache->driver->delete('languages');
    }
}