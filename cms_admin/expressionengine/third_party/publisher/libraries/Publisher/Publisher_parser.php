<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Parser Class
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

class Publisher_parser {

	private $entry_path_types = array();
	private $global_path_types = array();
	private $single_path_types = array();
	private $path_types = array();
	private $all_path_types = array();
	private $tagdata = '';
	private $entry_row;

	    /**
     * Parse a string as a template file
     *
     * @param  string $template
     * @param  array $vars
     * @return string
     */
    public function parse($template, $vars)
    {
        $old_TMPL = FALSE;

        if( !isset(ee()->TMPL))
        {
            ee()->load->library('template');
            ee()->TMPL = new EE_Template();
        }
        else
        {
            $old_TMPL = ee()->TMPL;
        }

        $template = ee()->functions->prep_conditionals($template, array($vars));
        $template = ee()->TMPL->parse_variables($template, array($vars));

        ee()->TMPL->template = '';
        ee()->TMPL->parse($template);
        ee()->TMPL->template = ee()->TMPL->parse_globals(ee()->TMPL->template);

        $template = ee()->TMPL->template;

        if($old_TMPL)
        {
            ee()->TMPL = $old_TMPL;
        }

        return $template;
    }

	/**
     * Parse {url_title_path=""}, {entry_id_path=""}, and {path=""} variables.
     * If $row is empty, then its not called from an entries tag.
     *
     * @param  string  $tagdata
     * @param  array   $row     entry data array
     * @return string
     */
    public function replace_path_variables($tagdata, $row = FALSE)
    {
        // Only if URL Translations are enabled
        if ( !ee()->publisher_setting->url_translations())
        {
            return $tagdata;
        }

        // If URL Translations are enabled, but we're in production mode
        // and viewing the default language, no path translations necessary.
        if (ee()->publisher_lib->is_default_mode)
        {
            return $tagdata;
        }

        $this->entry_path_types = array(
            'url_title_path',
            'entry_id_path'
        );

        $this->global_path_types = array(
            'path'
        );

        $this->single_path_types = array(
            'comment_url_title_auto_path',
            'comment_entry_id_auto_path',
            'comment_auto_path',
            'title_permalink'
        );

        $this->path_types = array_merge($this->entry_path_types, $this->global_path_types);
        $this->all_path_types = array_merge($this->path_types, $this->single_path_types);
        $this->tagdata = $tagdata;
        $this->entry_row = $row;

        foreach ($this->path_types as $path_type)
        {
            $this->path_type = $path_type;

            preg_replace_callback("/".LD."\s*". preg_quote($path_type) ."=(.*?)".RD."/", array(&$this, '_replace_path_variables'), $this->tagdata);
        }

        foreach ($this->single_path_types as $path_type)
        {
            $this->path_type = $path_type;

            preg_replace_callback("/".LD."\s*". preg_quote($path_type) ."\s*".RD."/", array(&$this, '_replace_path_variables'), $this->tagdata);
        }

        return $this->tagdata;
    }

    private function _replace_path_variables($matches)
    {
        if ( !isset($matches[0]))
        {
            return;
        }

        $path_var = $matches[0];
        $old_segments = isset($matches[1]) ? $matches[1] : '';

        // Its a var with a path value
        if (isset($matches[1]))
        {
            $old_segments = $matches[1];
        }
        // Its a single path variable, no parameter
        else
        {
            switch ($this->path_type)
            {
                case 'comment_url_title_auto_path':
                case 'comment_entry_id_auto_path':
                case 'comment_auto_path':
                    $old_segments = $this->entry_row['comment_url'] != '' ? $this->entry_row['comment_url'] : $this->entry_row['channel_url'];
                break;
                case 'title_permalink':
                    $old_segments = $this->entry_row['channel_url'];
                break;
            }

            $old_segments = str_replace(array(ee()->publisher_helper_url->get_site_index(), '{site_url}'), '', $old_segments);
        }

        $segment_path = trim(str_replace(array('"', '\''), '', $old_segments), "/");

        if ($new_segments = ee()->publisher_template->get_translated_segments(explode('/', $segment_path)))
        {
            // Make sure the trailing slash is not present, otherwise the replace does not work
            $new_segments = rtrim(implode('/', $new_segments), '/');

            // Prefix with {site_url}, its a late parsed variable and the easiest way
            // to print the correct translated URL back to the page.
            $new_path_variable = '{site_url}'.$new_segments;

            // If its an entries row we potentially have more swapping to do.
            // Other path variables are swapped automagically.
            if ($this->entry_row AND in_array($this->path_type, $this->all_path_types))
            {
                switch ($this->path_type)
                {
                    case 'url_title_path':
                    case 'comment_url_title_auto_path':
                    case 'title_permalink':
                        // This shouldn't add any extra queries and should get it from cache
                        // get_transltaed_segments above should have queried it and put into cache.
                        $new_path_variable .= '/'. ee()->publisher_entry->get_translated_url_title($this->entry_row['url_title']);
                    break;
                    case 'entry_id_path':
                    case 'comment_entry_id_auto_path':
                        $new_path_variable .= '/'. $this->entry_row['entry_id'];
                    break;
                    case 'comment_auto_path':
                        // no changes necessary, no url_title or entry_id to be appended
                    break;
                }

                // Did someone try something like {path="foo/bar/{entry_id}"}?
                $new_path_variable = str_replace('{entry_id}', $this->entry_row['entry_id'], $new_path_variable);
            }

            // Replace the {site_url} now instead of later by EE.
            $new_path_variable = str_replace('{site_url}', ee()->publisher_helper_url->get_site_url(), $new_path_variable);
            $new_path_variable = rtrim(reduce_double_slashes($new_path_variable), '/');

            $this->tagdata = str_replace($path_var, $new_path_variable, $this->tagdata);
        }
    }

    /**
     * Get attr="value" pairs from a string. Very similar to how EE's core
     * gets tag parameters from the templates.
     *
     * @param  string $string
     * @return array
     */
    private function _get_args($string)
    {
        // Clean up the arguments
        $raw_tag = str_replace(array("\r\n", "\r", "\n", "\t"), " ", $string);
        $args = trim((preg_match("/\s+.*/", $raw_tag, $arg_matches))) ? $arg_matches[0] : '';
        return ee()->functions->assign_parameters($args);
    }

    /**
     * Take an array of arguments and turn it into an attributes string for a tag
     *
     * @param  array $args
     * @return string
     */
    private function _create_args($args)
    {
        $formatted_args = array();

        foreach ($args as $k => $v)
        {
            $formatted_args[] = $k.'="'. $v .'"';
        }

        return implode(' ', $formatted_args);
    }

    /**
     * Find all <form> fields on a page, update its action="" parameter with a translated
     * value, and update certain hidden inputs with translated values.
     *
     * @param  string $tagdata
     * @return string
     */
    public function replace_form_variables($tagdata)
    {
        preg_match_all('/(<form(.*?)>)(.*?)<\/form>/s', $tagdata, $matches);

        if (empty($matches) || !ee()->publisher_helper_url->should_add_prefix())
        {
            return $tagdata;
        }

        /*
        matches[0] == full <form> tag
        matches[1] = opening <form> tag only
        matches[2] = <form> tag attributes only
        matches[3] = inner contents of <form> tag
        */

        foreach ($matches[0] as $match_key => $match_data)
        {
            $form_tag_full = $matches[0][$match_key];
            $form_tag_full_original = $form_tag_full;

            $form_tag_open = $matches[1][$match_key];
            $form_tag_open_original = $form_tag_open;

            $form_attributes = $matches[2][$match_key];
            $form_contents = $matches[3][$match_key];

            $args = $this->_get_args($form_attributes);

            // Have we already parsed this tag?
            if (isset($args['data-publisher-updated']))
            {
                continue;
            }

            // Create this so we know the tag has been parsed and don't parse it again.
            $args['data-publisher-updated'] = 'true';

            // Find all our hidden inputs to update the values if need be. This regex isn't the most solid.
            preg_match_all('/<input type="hidden" name="(\S+)" value="(\S+)" \/>/', $form_contents, $content_matches);

            if ( !empty($content_matches[1]))
            {
                // Hidden input names to look for that we need to update.
                $find = array('RET', 'URI', 'return_url', 'return', 'params', 'meta');

                foreach ($content_matches[1] as $k => $name)
                {
                    if (in_array($name, $find))
                    {
                        $value = $content_matches[2][$k];

                        // Really don't like doing this. Abstract it out later?
                        // Ideally there would be a hook in the form method in EE.

                        if ($name == 'RET')
                        {
                            $str = '<input type="hidden" name="'. $name .'" value="'. ee()->publisher_helper_url->swap_url($value, FALSE) .'" />';
                            $form_tag_full = str_replace($content_matches[0][$k], $str, $form_tag_full);
                        }
                        // Native EE Search
                        elseif ($name == 'meta' && class_exists('Search'))
                        {
                            // Load our class that extends the Search module.
                            // Enables us to call protected methods.
                            ee()->load->library('Publisher/Publisher_search');

                            // The get_meta_vars method looks in the $_POST
                            // array. We're fudging the array so we don't have
                            // to copy the entire get_meta_vars method from the core.
                            $_POST['meta'] = $value;
                            $meta = ee()->publisher_search->get_meta_vars();

                            // Update the paths to the translated ones.
                            if (isset($meta['result_page']))
                            {
                                $meta['result_page'] = ee()->publisher_helper_url->swap_url($meta['result_page'], FALSE);
                            }

                            if (isset($meta['no_result_page']))
                            {
                                $meta['no_result_page'] = ee()->publisher_helper_url->swap_url($meta['no_result_page'], FALSE);
                            }

                            $value = ee()->publisher_search->build_meta_array($meta);

                            // To test the array...
                            // $_POST['meta'] = $value;
                            // $meta = ee()->publisher_search->get_meta_vars();
                            // var_dump($meta); die;

                            // Replace the contents with our newly translated value.
                            $str = '<input type="hidden" name="'. $name .'" value="'. $value .'" />';
                            $form_tag_full = str_replace($content_matches[0][$k], $str, $form_tag_full);
                        }
                        // Low Search
                        elseif ($name == 'params' && array_key_exists('low_search', ee()->addons->get_installed('modules')))
                        {
                            $params = json_decode(base64_decode($value));

                            if (is_object($params) && isset($params->result_page))
                            {
                                $result_page = ee()->publisher_helper_url->swap_url($params->result_page);

                                $args['action'] = $result_page;

                                // Update the params field and cast it back to an array.
                                $params->result_page = $result_page;

                                if (function_exists('low_search_encode'))
                                {
                                    $value = low_search_encode((array)$params);
                                }
                                else
                                {
                                    require_once PATH_THIRD.'low_search/base.low_search.php';
                                    $low = new Low_search_base();
                                    $value = $low->encode((array)$params);
                                }

                                // Replace the contents with our newly translated value.
                                $str = '<input type="hidden" name="'. $name .'" value="'. $value .'" />';
                                $form_tag_full = str_replace($content_matches[0][$k], $str, $form_tag_full);
                            }
                        }
                        // Only update URL based fields
                        elseif ($name != 'params' && $name != 'meta')
                        {
                            $value = ee()->publisher_helper_url->remove_double_codes(ee()->publisher_lib->lang_code.'/'.$value, ee()->publisher_lib->lang_code);
                            $str = '<input type="hidden" name="'. $name .'" value="'. $value .'" />';
                            $form_tag_full = str_replace($content_matches[0][$k], $str, $form_tag_full);
                        }
                    }
                }
            }

            // Update the opening <form> tag with our new param
            $form_tag_open = '<form '. $this->_create_args($args) .'>';
            $form_tag_full = str_replace($form_tag_open_original, $form_tag_open, $form_tag_full);

            $tagdata = str_replace($form_tag_full_original, $form_tag_full, $tagdata);
        }

        return $tagdata;
    }
}