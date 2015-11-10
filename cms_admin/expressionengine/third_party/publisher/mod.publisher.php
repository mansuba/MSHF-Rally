<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Module Class
 *
 * @package     ExpressionEngine
 * @subpackage  Modules
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

class Publisher {

    public function parse_path_variables()
    {
        $tagdata = ee()->TMPL->tagdata;

        ee()->load->library('Publisher/Publisher_parser');
        $tagdata = ee()->publisher_parser->replace_path_variables($tagdata);

        return $tagdata;
    }

    /**
     * Add a toolbar to the page to toggle view status
     *
     * {exp:publisher:toolbar}
     *
     * @return string
     */
    public function toolbar()
    {
        $out = '';

        $url = ee()->publisher_helper_url->get_action('set_status', 'Publisher');

        preg_match('/status=(\S+)/', $url, $matches);

        /*
        @TODO

        // Do we have a Structure or Pages entry?
        $url = ee()->publisher_site_pages->get_url($entry_id, $channel_id, PUBLISHER_STATUS_DRAFT);

        if ( !$url OR is_numeric($url))
        {
            do preg_replace on current url here
        }
         */

        if (isset($matches[1]) AND in_array($matches[1], array(PUBLISHER_STATUS_DRAFT, PUBLISHER_STATUS_OPEN)))
        {
            $status = $matches[1];

            $draft_url = preg_replace('/publisher_status=(\S+)/', 'publisher_status='. PUBLISHER_STATUS_DRAFT, $url);
            $open_url  = preg_replace('/publisher_status=(\S+)/', 'publisher_status='. PUBLISHER_STATUS_OPEN, $url);
        }
        else
        {
            $separator = strstr($url, '?') ? '&' : '?';

            $draft_url = $url . $separator .'publisher_status='. PUBLISHER_STATUS_DRAFT;
            $open_url  = $url . $separator .'publisher_status='. PUBLISHER_STATUS_OPEN;
        }

        $attributes = $this->_build_attributes(array(
            'class' => 'publisher-toolbar'
        ));

        $status = ee()->TMPL->fetch_param('status', FALSE);
        $active_class = ee()->TMPL->fetch_param('active', 'publisher-active-status');

        if (ee()->session->userdata['group_id'] == 1 OR ee()->session->userdata['can_access_cp'] == 'y')
        {
            ee()->lang->loadfile('publisher');

            if ($status == PUBLISHER_STATUS_DRAFT)
            {
                $active = ee()->input->get('publisher_status') == PUBLISHER_STATUS_DRAFT ? $active_class : '';
                $out .= '<a class="publisher-toolbar-link '. $active .'" href="'. $draft_url .'"><span>'. lang(PUBLISHER_STATUS_DRAFT) .'<span></a>';
            }
            else if ($status == PUBLISHER_STATUS_OPEN)
            {
                $active = ee()->input->get('publisher_status') == PUBLISHER_STATUS_OPEN ? $active_class : '';
                $out .= '<a class="publisher-toolbar-link '. $active .'" href="'. $open_url .'"><span>'. lang(PUBLISHER_STATUS_OPEN) .'<span></a>';
            }
            else
            {
                $active_draft = ee()->input->get('publisher_status') == PUBLISHER_STATUS_DRAFT ? $active_class : '';
                $active_open = (ee()->input->get('publisher_status') == PUBLISHER_STATUS_OPEN || !ee()->input->get('publisher_status')) ? $active_class : '';

                $out .= '<div'. $attributes .'>
                            <div class="publisher-toolbar-header"></div>
                            <ul>
                                <li class="publisher-toolbar-item '. $active_draft .'">
                                    <a class="publisher-toolbar-link" href="'. $draft_url .'"><span>'. ucfirst(lang(PUBLISHER_STATUS_DRAFT)) .'<span></a>
                                </li>
                                <li class="publisher-toolbar-item '. $active_open .'">
                                    <a class="publisher-toolbar-link" href="'. $open_url .'"><span>'. ucfirst(lang(PUBLISHER_STATUS_OPEN)) .'<span></a>
                                </li>
                            </ul>
                            <div class="publisher-toolbar-footer"></div>
                        </div>';
            }

        }

        return $out;
    }

    /**
     * Create a switcher, either <select> or <ul>
     *
     * {exp:publisher:switcher}
     * {exp:publisher:switcher style="links"}
     *
     * @return string
     */
    public function switcher()
    {
        if (PUBLISHER_LITE === TRUE)
        {
            return '';
        }

        // Select or link
        $style      = ee()->TMPL->fetch_param('style', 'select');
        $return     = ee()->TMPL->fetch_param('return', 'long_name');
        $show       = ee()->TMPL->fetch_param('show');
        $active_class = ee()->TMPL->fetch_param('active', 'publisher-active-language');

        $show = $show ? explode('|', $show) : FALSE;

        $attributes = $this->_build_attributes(array(
            'id' => 'publisher-language-selector'
        ));

        $languages  = ee()->publisher_model->languages;
        $phrases    = ee()->publisher_model->get_translated_languages();
        $url        = ee()->publisher_helper_url->get_action('set_language', 'Publisher');
        $current_url= 'url='.base64_encode(ee()->publisher_session->get_current_url());

        $translated = array();

        // Filter the list of languages?
        $this->filter($languages);

        foreach ($languages as $lang_id => $language)
        {
            // Only show it if its enabled or explicitly not set to be shown
            if ($language['is_enabled'] != 'y' OR (is_array($show) AND !in_array($lang_id, $show)))
            {
                continue;
            }

            foreach ($phrases as $phrase_id => $phrase)
            {
                if ($phrase->phrase_name == strtolower('language_'. $language['short_name']))
                {
                    if ($return == 'long_name')
                    {
                        $translated[$lang_id] = $phrase->phrase_value;
                    }
                    else
                    {
                        $translated[$lang_id] = $language[$return];
                    }
                }
            }
        }

        $out = '';

        if ($style == 'links')
        {
            $out = '<ul'. $attributes .'>';

            foreach ($translated as $lang_id => $language)
            {
                $active = ee()->publisher_lib->lang_id == $lang_id ? ' class="'. $active_class .'"' : '';
                $out .= '<li'. $active .'><a href="'. $url .'&lang_id='. $lang_id .'&'. $current_url .'">'. $language .'</a></li>';
            }

            $out .= '</ul>';
        }
        else
        {
            $out = '<select'. $attributes .'>';

            foreach ($translated as $lang_id => $language)
            {
                $active = ee()->publisher_lib->lang_id == $lang_id ? ' selected="selected"' : '';
                $out .= '<option value="'. $url .'&lang_id='. $lang_id .'&'. $current_url .'"'. $active .'>'. $language .'</option>';
            }

            $out .= '</select>';
        }

        return $out;
    }

    /**
     * Turn an array of key/value pairs into an HTML attribute string for an element.
     * @param  array $attr
     * @return string
     */
    private function _build_attributes($default)
    {
        $attr = array(
            'name'  => ee()->TMPL->fetch_param('name', ''),
            'id'    => ee()->TMPL->fetch_param('id', ''),
            'class' => ee()->TMPL->fetch_param('class', ''),
            'data'  => ee()->TMPL->fetch_param('data', ''),
        );

        $attr_str = '';

        foreach ($attr as $name => $value)
        {
            // If user set an attribute in the template
            if ($value)
            {
                $attr_str .= $name .'="'. $value .'"';
            }
            // Use default if no attribute was set in the template
            else if (isset($default[$name]) AND $default[$name] != '')
            {
                $attr_str .= $name .'="'. $default[$name] .'"';
            }
        }

        return ' '.$attr_str;
    }

    /**
     * Loop over the languages and print the vars
     *
     * {exp:publisher:languages}
     *     {short_name}
     *     {long_name}
     *     {id}
     *     {country}
     *     {is_default}
     *     {is_enabled}
     *     {is_active}
     *     {direction}
     *     {latitude}
     *     {longitude}
     *     {switch_language_url}
     *     {alternate_url} - DEPRECATED
     *     {translated_url}
     * {/exp:publisher:languages}
     *
     * @return [type] [description]
     */
    public function languages()
    {
        if (PUBLISHER_LITE === TRUE)
        {
            return '';
        }

        $languages      = ee()->publisher_model->languages;
        $phrases        = ee()->publisher_model->get_translated_languages();

        $prefix         = ee()->TMPL->fetch_param('prefix', '');
        $show           = ee()->TMPL->fetch_param('show');
        $show_current   = ee()->TMPL->fetch_param('show_current', 'yes');
        $entry_id       = ee()->TMPL->fetch_param('entry_id');
        $url            = ee()->publisher_helper_url->get_action('set_language', 'Publisher');
        $current_url    = 'url='.base64_encode(ee()->publisher_session->get_current_url());

        $show = $show ? explode('|', $show) : FALSE;
        $vars = array();

        if ($entry_id)
        {
        }

        // Filter the list of languages?
        $this->filter($languages);

        foreach ($languages as $lang_id => $language)
        {
            // Only show it if its enabled or explicitly not set to be shown
            if ($language['is_enabled'] != 'y' OR
               (is_array($show) AND !in_array($lang_id, $show)) OR
               ($show_current == 'no' AND $language['id'] == ee()->publisher_lib->lang_id)
            ){
                continue;
            }

            if ($entry_id && !ee()->publisher_entry->has_translation($entry_id, $lang_id))
            {
                continue;
            }

            foreach ($phrases as $phrase_id => $phrase)
            {
                // Swap - for _ incase they name their language segments with underscores
                if ($phrase->phrase_name == strtolower('language_'. str_replace('-', '_', $language['short_name'])))
                {
                    // Make sure the name is the translated value
                    $language['long_name'] = $phrase->phrase_value;
                    $language['switch_language_url'] = htmlspecialchars($url.'&lang_id='.$lang_id.'&').$current_url;
                    $language['is_active'] = ee()->publisher_lib->lang_id == $lang_id ? TRUE : FALSE;
                    $language['translated_url'] = ee()->publisher_helper_url->get_translated_url(NULL, ee()->publisher_lib->lang_id, $lang_id);
                    // Deprecated var
                    $language['alternate_url'] = $language['translated_url'];

                    if ($prefix)
                    {
                        $temp = array();

                        foreach ($language as $k => $v)
                        {
                            $temp[$prefix.$k] = $v;
                        }

                        $vars[] = $temp;
                    }
                    else
                    {
                        $vars[] = $language;
                    }
                }
            }
        }

        return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $vars);
    }

    /**
     * Generic function to handle translation of phrases or categories.
     * Requires a type parameter if this tag is used.
     * @return string
     *
     */
    public function translate()
    {
        if (PUBLISHER_LITE === TRUE)
        {
            return '';
        }

        $type = ee()->TMPL->fetch_param('type');

        switch ($type)
        {
            case 'category': return $this->translate_category(); break;
            case 'phrase': return $this->translate_phrase(); break;
            case 'entry': return $this->translate_entry(); break;
        }
    }

    /**
     * Tag for translating categories. Can be called directly or via translate() above.
     * @return string
     *
     * {exp:publisher:translate_category cat_id="1"}
     *     {cat_name}
     *     {cat_url_title}
     *     {cat_description}
     *     {cat_image}
     *     {cat_order}
     *     {cat_id}
     *     {site_id}
     *     {group_id}
     *     {parent_id}
     *     {[custom_category_field]}
     * {/exp:publisher:translate}
     *
     * {exp:publisher:translate type="category" cat_id="1" return="cat_name"}
     *
     */
    public function translate_category()
    {
        if (PUBLISHER_LITE === TRUE)
        {
            return '';
        }

        $cat_id = ee()->TMPL->fetch_param('cat_id');
        $cat_url_title = ee()->TMPL->fetch_param('cat_url_title');
        $return = ee()->TMPL->fetch_param('return');
        $prefix = ee()->TMPL->fetch_param('var_prefix', '');

        $find_by_key = FALSE;
        $find_by_value = FALSE;

        if ($cat_id)
        {
            $find_by_key = 'cat_id';
            $find_by_value = $cat_id;
        }
        else if ($cat_url_title)
        {
            $find_by_key = 'cat_url_title';
            $find_by_value = $cat_url_title;
        }

        if ( !$find_by_key)
        {
            show_error('The {exp:publisher:translate_category} tag requires either an <code>cat_id</code> or <code>cat_url_title</code> parameter.');
        }

        $category = $this->search_categories($find_by_key, $find_by_value);

        if ($prefix)
        {
            foreach ($category as $field => $value)
            {
                $category[$prefix.$field] = $value;
            }
        }

        // Return a single field value
        if ($return)
        {
            return isset($category[$return]) ? $category[$return] : '';
        }
        // Return vars for each field
        else
        {
            return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, array($category));
        }
    }

    /**
     * Tag to translate phrases, just pulls from the global config,
     * but provides an option to get a phrase based on another variable value.
     *
     * {exp:publisher:translate_phrase name="{foo}"}
     *
     * @return string
     */
    public function translate_phrase()
    {
        if (PUBLISHER_LITE === TRUE)
        {
            return '';
        }

        $phrase_name = strtolower(ee()->TMPL->fetch_param('name'));
        $prefix = ee()->publisher_setting->phrase_prefix();

        if (isset(ee()->config->_global_vars[$prefix.$phrase_name]))
        {
            return ee()->config->_global_vars[$prefix.$phrase_name];
        }

        return $phrase_name;
    }

    /**
     * Translate an entry. Use this with 3rd party modules that might not
     * support publisher, or if you don't need the full entries tag parsing.
     *
     * On its own...
     *
     * {exp:publisher:translate_entry entry_id="{entry_id}"}
     *     {title}
     * {/exp:publisher:translate_entry}
     *
     * Nested inside another module...
     *
     * {exp:calendar:cal}
     *     {exp:publisher:translate_entry entry_id="{event_id}"}
     *         {title}
     *     {/exp:publisher:translate_entry}
     * {/exp:calendar:cal}
     *
     * @return [type] [description]
     */
    public function translate_entry()
    {
        if (PUBLISHER_LITE === TRUE)
        {
            return '';
        }

        $entry_id = ee()->TMPL->fetch_param('entry_id');
        $lang_id  = ee()->TMPL->fetch_param('lang_id', ee()->publisher_lib->lang_id);
        $status   = ee()->TMPL->fetch_param('status', ee()->publisher_lib->status);
        $prefix   = ee()->TMPL->fetch_param('var_prefix', '');

        if ( !$entry_id)
        {
            return ee()->TMPL->no_results();
        }

        $entry = ee()->publisher_entry->get($entry_id, $status, $lang_id);

        if ( !$entry)
        {
            return ee()->TMPL->no_results();
        }

        $fields = ee()->publisher_model->get_custom_field_names();

        $entry = (array) $entry;

        foreach ($entry as $column => $value)
        {
            $entry[$prefix.$column] = $value;

            if (isset($fields[$column]))
            {
                $entry[$prefix.$fields[$column]] = $value;
            }
        }

        return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, array($entry));
    }

    public function prev_entry()
    {
        ee()->load->library('Publisher/Publisher_channel');
        return ee()->publisher_channel->prev_entry();
    }

    public function next_entry()
    {
        ee()->load->library('Publisher/Publisher_channel');
        return ee()->publisher_channel->next_entry();
    }

    /**
     * Tag to enable simple searching of custom fields.
     *
     * {exp:publisher:search field_name="some_value|another_value"}
     *     {exp:channel:entries entry_id="{entries}"}
     *          {title}
     *     {/exp:channel:entries}
     * {/exp:publisher:search}
     *
     * @return string
     */
    public function search()
    {
        // Loop through each parameter to this tag and build
        // up a collection of custom field names
        $fields = array();
        $title_search = '';
        $params = array();
        $values = array();

        foreach (ee()->TMPL->tagparams as $param => $value)
        {
            $values[] = $value;
            $params[] = $param;

            if ($param == 'title')
            {
                $title_search = $value;
            }
            elseif (!in_array($param, $fields))
            {
                $fields[] = $param;
            }
        }

        // Get the field IDs
        $searches = array();

        if( !empty($fields))
        {
            $ids = ee()->db->select('field_id, field_name')
                                ->where_in('field_name', $fields)
                                ->get('channel_fields')
                                ->result();

            foreach ($ids as $row)
            {
                $searches['field_id_' . $row->field_id] = ee()->TMPL->tagparams[$row->field_name];
            }
        }

        // Title search?
        if ($title_search)
        {
            $searches['title'] = $title_search;
        }

        ee()->db->_reset_select();

        // Run the search!
        foreach ($searches as $where => $value)
        {
            // null
            if ($value == 'IS_EMPTY')
            {
                ee()->db->like($where, '');
            }
            // exact match
            elseif (substr($value, 0, 2) == '= ')
            {
                ee()->db->where($where, substr($value, 2));
            }
            // exact non-match
            elseif (substr($value, 0, 3) == '!= ' OR substr($value, 0, 4) == 'not ')
            {
                // for "not IS_EMPTY" support
                $value = str_replace('not ', '', $value);
                $value = strstr($value, 'IS_EMPTY') ? str_replace('IS_EMPTY', '', $value) : $value;
                ee()->db->where($where .' !=', $value);
            }
            // wild card
            else
            {
                ee()->db->like($where, $value);
            }
        }

        $query = ee()->db->select('entry_id')
                              ->where('publisher_lang_id', ee()->publisher_lib->lang_id)
                              ->where('publisher_status', ee()->publisher_lib->status)
                              ->get('publisher_data');

        $entries = array();

        foreach ($query->result() as $row)
        {
            $entries[] = (int) $row->entry_id;
        }

        $entries = implode('|', $entries);

        // Parse the variables and return!
        return str_replace(LD.'entries'.RD, $entries, ee()->TMPL->tagdata);
    }

    /**
     * Search the current categories array for the requested category
     * @param  string $key   field name
     * @param  string $value field value
     * @return array
     */
    private function search_categories($key, $value)
    {
        $categories = ee()->publisher_category->get_current();

        foreach ($categories as $group_id => $data)
        {
            foreach ($data as $cat_id => $category)
            {
                if ( !isset($category[$key]))
                {
                    show_error('<code>'. $key .'</code> is not a valid category field.');
                }

                if ($category[$key] == $value)
                {
                    return (array) $category;
                }
            }
        }
    }

    /**
     * Optionally show only specific languages
     * @param  array $languages
     * @return void
     */
    private function filter(&$languages)
    {
        // Limit which languages are available?
        $show = ee()->TMPL->fetch_param('show');

        // Set a specific display order?
        $order = ee()->TMPL->fetch_param('order');

        // Filter the list even further?
        if ($show AND strstr($show, '|'))
        {
            $show = explode('|', $show);

            foreach ($languages as $lang_id => $language)
            {
                if ( !in_array($lang_id, $show))
                {
                    unset($languages[$lang_id]);
                }
            }
        }

        foreach ($languages as $lang_id => $language)
        {
            if (isset($language['sites']) && !empty($language['sites']))
            {
                $sites = json_decode($language['sites']);

                if ( !in_array(ee()->publisher_lib->site_id, $sites))
                {
                    unset($languages[$lang_id]);
                }
            }
        }

        // If the param doesn't contain a delimiter, then we have nothing to sort.
        if ($order && strpos($order, '|') !== FALSE)
        {
            $order = explode('|', $order);
            $languages = ee()->publisher_helper->sort_array_by_array($languages, $order);
        }
    }

    /**
     * Action - Set the site language with ?ACT=
     */
    public function set_language()
    {
        ee()->publisher_session->set_language();
    }

    /**
     * Action - Set the view status with ?ACT=
     */
    public function set_status()
    {
        ee()->publisher_session->set_status();
    }
}
/* End of file mod.publisher.php */
/* Location: /system/expressionengine/third_party/publisher/mod.publisher.php */