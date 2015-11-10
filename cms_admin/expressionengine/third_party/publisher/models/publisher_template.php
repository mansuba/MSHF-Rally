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

class Publisher_template extends Publisher_model
{
    private $diff_tags = array();

    public function __construct()
    {
        parent::__construct();

        ee()->load->model('design_model');

        // Create cache
        if (! isset(ee()->session->cache['publisher']))
        {
            ee()->session->cache['publisher'] = array();
        }
        $this->cache =& ee()->session->cache['publisher'];
    }

    public function get_template_contents($template)
    {
        if( !isset(ee()->TMPL))
        {
            ee()->load->library('template');
            ee()->TMPL = new EE_Template();
        }

        $template = explode('/', $template);

        return ee()->TMPL->fetch_template($template[0], $template[1], FALSE);
    }

    public function get_snippet_contents($snippet)
    {
        $qry = ee()->db->where('snippet_id', $snippet)
                            ->get('snippets');

        return $qry->row('snippet_contents');
    }

    /**
     * Get all templates
     *
     * @param  boolean $as_options
     * @return array
     */
    public function get_all($as_options = TRUE)
    {
        $templates = ee()->design_model->fetch_templates();

        if ($as_options === TRUE)
        {
            $list = array(
                '' => '- Select -'
            );
        }
        else
        {
            $list = array();
        }

        foreach ($templates->result() as $template)
        {
            $list[$template->template_id] = $template->group_name .'/'. $template->template_name;
        }

        return $list;
    }

    /**
     * Get the default template for a Pages channel
     *
     * @param  integer $channel_id
     * @return integer
     */
    public function get_pages_default_template($channel_id)
    {
        if ( !$channel_id)
        {
            show_error('$channel_id is required by get_pages_default_template()');
        }

        $qry = ee()->db->select('configuration_value')
                       ->where('configuration_name', 'template_channel_'. $channel_id)
                       ->where('site_id', ee()->publisher_lib->site_id)
                       ->get('pages_configuration');

        return $qry->num_rows() ? $qry->row('configuration_value') : 0;
    }

    public function get_snippets($as_options = TRUE)
    {
        // Get the current Site ID
        $site_id = ee()->config->item('site_id');

        $qry = ee()->db->where('site_id', $site_id)
                ->or_where('site_id', 0)
                ->order_by('snippet_name')
                ->get('snippets');

        if ($as_options === TRUE)
        {
            $list = array(
                '' => '- Select -'
            );
        }
        else
        {
            $list = array();
        }

        foreach ($qry->result() as $snippet)
        {
            $list[$snippet->snippet_id] = $snippet->snippet_name;
        }

        return $list;
    }

    /**
     * Get templates organized by group and by template_id
     * @return array
     */
    public function get_by_group()
    {
        $templates = ee()->design_model->fetch_templates();

        $groups = array();

        foreach ($templates->result() as $template)
        {
            $groups[$template->group_name][$template->template_id] = $template->template_name;
        }

        return $groups;
    }

    /**
     * Get a group's ID by the group name
     * @param  string $group_name
     * @return int
     */
    public function get_group_id($group_name)
    {
        if ( !isset($this->cache['get_group_id'][$group_name]))
        {
            $qry = ee()->db->select('group_id')
                    ->where('group_name', $group_name)
                    ->where('site_id', ee()->publisher_lib->site_id)
                    ->get('template_groups');

            $this->cache['get_group_id'][$group_name] = $qry->num_rows() == 1 ? $qry->row('group_id') : FALSE;
        }

        return $this->cache['get_group_id'][$group_name];
    }

    /**
     * Get the group name by the group ID
     * @param  int $group_id
     * @return string
     */
    public function get_group_name($group_id)
    {
        if ( !isset($this->cache['get_group_name'][$group_id]))
        {
            $qry = ee()->db->select('group_name')
                       ->where('group_id', $group_id)
                       ->get('template_groups');

            $this->cache['get_group_name'][$group_id] = $qry->num_rows() == 1 ? $qry->row('group_name') : FALSE;
        }

        return $this->cache['get_group_name'][$group_id];
    }

    /**
     * Get a template's name by template ID
     * @param  int $template_id
     * @return string
     */
    public function get_template_path($template_id)
    {
        $qry = ee()->db->select('group_id, template_name')
                ->where('template_id', $template_id)
                ->get('templates');

        if ( !$qry->num_rows())
        {
            return '';
        }

        $group_name = $this->get_group_name($qry->row('group_id'));
        $template_name = $qry->row('template_name');

        return $group_name .'/'. $template_name;
    }

    /**
     * Get a template's name by template ID
     * @param  int $template_id
     * @return string
     */
    public function get_template_name($template_id)
    {
        if ( !isset($this->cache['get_template_name'][$template_id]))
        {
            $qry = ee()->db->select('template_name')
                    ->where('template_id', $template_id)
                    ->get('templates');

            $this->cache['get_template_name'][$template_id] = $qry->num_rows() == 1 ? $qry->row('template_name') : FALSE;
        }

        return $this->cache['get_template_name'][$template_id];
    }

    /**
     * Delete template translations when a template is removed in the CP
     * @param  integer $id
     * @param  string  $type group or template
     * @return void
     */
    public function delete_translation($id, $type = 'template')
    {
        if ($type == 'group')
        {
            $qry = ee()->db->where('group_id', $id)->get('templates');

            $templates = array();

            foreach ($qry->result() as $row)
            {
                $templates[] = $row->template_id;
            }

            ee()->db->where('type', 'group')
                ->where_in('type_id', $id)
                ->delete('publisher_templates');

            ee()->db->where('type', 'template')
                ->where_in('type_id', $templates)
                ->delete('publisher_templates');
        }
        else
        {
            ee()->db->where('type', 'template')
                ->where('type_id', $id)
                ->delete('publisher_templates');
        }
    }

    /**
     * Called from core_template_route to determine the actual template
     * names to load when using translated URLS. Also sets {publisher:segment_N}
     * variables so url_title="{publisher:segment_N}" can be used in entries
     * and other module tags.
     *
     * Can also be called from anywhere if the 2nd param is set to FALSE
     * to take a translated URL and get the default segments for it.
     *
     * @param  array $parts URL Segments
     * @param  boolean $set_uri_string Set the URI string and POST var on the page request?
     * @param  boolean $force_index    Force index as the 2nd segment if this is called from core_route hook.
     * @param  mixed   $return         Return the url title or boolean if it was found or not.
     * @return array
     */
    public function get_default_segments($segments = FALSE, $set_uri_string = TRUE, $force_index = FALSE, $return = 'url_title')
    {
        $segments = $segments ?: ee()->publisher_helper_url->get_current_segments(FALSE);

        $original_segments = $segments;
        $default_segments = array();
        $entry_id = FALSE;
        $segment_1 = FALSE;
        $segment_2 = FALSE;

        // If the first segment is a valid lang code, remove it. This is generally removed via get_current_segments()
        // but calling it again if a $segments array was passed directly.
        if (isset($segments[1]) AND in_array($segments[1], ee()->publisher_session->language_codes))
        {
            array_shift($segments);
        }

        // re-index it so it always starts at 0 index.
        $segments = array_values($segments);

        // Added $segments[0] != '' b/c the home page, /, was returning a random template.
        if ( !empty($segments) AND $segments[0] != '')
        {
            // Set {publisher:segment_x} variables
            ee()->publisher_helper_url->set_publisher_segments($segments);

            ee()->publisher_log->message($original_segments, '$original_segments');
            ee()->publisher_log->message($segments, '$segments');

            // If its the default language, we already have the default segments, nothing to translate
            if (ee()->publisher_lib->is_default_language AND $set_uri_string === TRUE)
            {
                return FALSE;
            }

            // Its a template_group/template pattern, search by default language first
            // e.g. {path="english/stuff"} Grab these values now and replace them
            // into the array below if they are valid group/template values.
            if ($segment_1 = $this->get_default_group($segments[0]))
            {
                // Remove the template group segment so a url or category
                // translation doesn't possibly override it.
                unset($original_segments[0]);

                // Add the layout_group to the new array.
                $default_segments[0] = $segment_1;

                if (isset($segments[1]))
                {
                    if ($segment_2 = $this->get_default_template($segments[1]))
                    {
                        $default_segments[1] = $segment_2;

                        // Remove the template segment so a url or category
                        // translation doesn't possibly override it.
                        unset($original_segments[1]);
                    }
                }
            }

            // Now look at all the additional segments and see if we have translations.
            // This will also translate category url indicators.
            foreach ($original_segments as $k => $segment)
            {
                if ($seg = ee()->publisher_category->get_default_url_title($segment, $return))
                {
                    $default_segments[$k] = $seg;
                }
            }

            foreach ($original_segments as $k => $segment)
            {
                if ($seg = ee()->publisher_entry->get_default_url_title($segment, $return))
                {
                    $default_segments[$k] = $seg;
                    $entry_id = ee()->publisher_entry->get_default_url_title($segment, 'entry_id');
                }
            }

            // Make sure its sorted the same.
            ksort($default_segments);

            // If no translations were found, make sure the segments exist in the new array
            // so the rest of the code continues to work.
            foreach ($original_segments as $k => $segment)
            {
                if ( !isset($default_segments[$k]) || ($default_segments[$k] == '' && $segment != ''))
                {
                    $default_segments[$k] = $segment;
                }
            }

            // Save as a reference prior to mucking with the default_segments some more.
            $default_uri_segments = $default_segments;

            // DISABLED because of support issue from Peter Michalcik <michalcik@tckompas.sk>
            // This is so the category url trigger work continues to work.
            // If the segment is the category word save it for later b/c we
            // might be setting segment[1] to index in a second, but we still
            // need the category word in the uri_string, otherwise the
            // channe:entries tag won't find the entries at all.
            // $category_segment = FALSE;
            // if (isset($default_segments[1]) && $default_segments[1] == ee()->config->item('reserved_category_word'))
            // {
            //     $category_segment = ee()->config->item('reserved_category_word');
            // }

            // If we don't have a valid 2nd segment, which is a template group then
            // we need to default to index, b/c its loading the index segment
            // of a template group. Push index into our array as the 2nd value.
            if ( !$segment_2 && $force_index == TRUE)
            {
                array_splice($default_segments, 1, 0, 'index');
            }

            ee()->publisher_log->message($default_segments, '$default_segments');
            ee()->publisher_log->message($default_uri_segments, '$default_uri_segments');

            // Since we possibly forced index into the 2nd segment above we might need
            // to remove it before our custom vars are created. If the original segments
            // array did not contain an index template in the 2nd spot, but our new segments
            // array does, then we don't need it, so remove it and reset the array keys
            if (isset($default_uri_segments[1]) && $default_uri_segments[1] == 'index' &&
                isset(ee()->uri->segments[1]) && ee()->uri->segments[1] != 'index'
            ){
                unset($default_uri_segments[1]);
                $default_uri_segments = array_values($default_uri_segments);
            }

            // Set publisher specific segment values. {segment_N} will
            // be the translated version, need these so entries can
            // be queried by url_title, e.g. url_title="{publisher:segment_1}"
            $publisher_segments = array();

            foreach ($default_uri_segments as $k => $v)
            {
                $publisher_segments['publisher:segment_'. ($k + 1)] = $v;
            }

            ee()->config->_global_vars = array_merge(ee()->config->_global_vars, $publisher_segments);

            ee()->publisher_log->message($publisher_segments, '$publisher_segments');

            if (isset($default_segments[0]) AND $segment_1 !== FALSE)
            {
                if ($set_uri_string === TRUE)
                {
                    // Set the entry_id so dynamic_parameters="entry_id" works
                    // and the translated url_title is valid.
                    if ($entry_id)
                    {
                        $_POST['entry_id'] = $entry_id;
                    }

                    ee()->publisher_log->message($entry_id, '$_POST[\'entry_id\']');

                    // DISABLED because of support issue from Peter Michalcik <michalcik@tckompas.sk>
                    // Add this to the segments so EE can do its internal lookups correctly.
                    // if ($category_segment && $default_segments[1] == 'index')
                    // {
                    //     array_splice($default_segments, 2, 0, $category_segment);
                    // }

                    // Set the uri_string so anything referencing it will work
                    // with the default values instead of translated.
                    ee()->uri->uri_string = trim(implode('/', $default_segments), '/');

                    ee()->publisher_log->message(ee()->uri->uri_string, 'ee()->uri->query_string');

                    // Create new segments array to manipulate.
                    $qstring_segments = $default_segments;

                    // Pop off the first 2 segments, which are template_group/template
                    // We're left with category, url_title, or possible date segments.
                    array_shift($qstring_segments);
                    array_shift($qstring_segments);
                    $qstring = implode('/', $qstring_segments);

                    // Set the qstring to the url_title if its found so dynamic="yes" works.
                    // mod.channel.php->build_sql_query() will reference this.
                    ee()->uri->query_string = $qstring;

                    ee()->publisher_log->message(ee()->uri->query_string, 'ee()->uri->query_string');
                }

                ee()->publisher_log->message($default_segments, 'Returning: $publisher_segments');

                return $default_segments;
            }
        }

        ee()->publisher_log->message('FALSE', 'Returning: FALSE');

        return FALSE;
    }

    /**
     * Used when switching languages, take the previous language
     * segments and get the new language translations
     * @param  array $parts Current URI segments, or specific set of segments passed
     * @param  integer $by_default Lookup the translated segments based on the default language value
     * @param  integer $lang_id If we want to translate a segment to a specific language instead of the previous
     * @return array
     */
    public function get_translated_segments($segments = NULL, $lang_id_from = NULL, $lang_id_to = NULL)
    {
        $segments = $segments ?: ee()->publisher_helper_url->get_current_segments(FALSE);
        $lang_id_from = $lang_id_from ?: ee()->publisher_lib->default_lang_id;

        $translated_segments = array();

        // If the first segment is a valid lang code, remove it.
        if (isset($segments[0]) AND in_array($segments[0], ee()->publisher_session->language_codes))
        {
            array_shift($segments);
        }

        if ( !empty($segments))
        {
            // re-index it so it always starts at 0 index.
            $segments = array_values($segments);

            ee()->publisher_log->message($segments, '$segments');

            // Its a template_group/template pattern, search by default language first
            // e.g. {path="english/stuff"}
            if ($segment_1 = $this->get_translated_group($segments[0], $lang_id_from, $lang_id_to))
            {
                // Get rid of the first, its the template group we just translated,
                // don't need to loop over it again and again.
                array_shift($segments);

                $translated_segments = $segments;

                // Loop through the rest of the segments and get the translations, if any
                foreach ($translated_segments as $k => $segment)
                {
                    if ($seg = $this->get_translated_template($segment, $lang_id_from, $lang_id_to))
                    {
                        $translated_segments[$k] = $seg;
                    }
                }

                foreach ($translated_segments as $k => $segment)
                {
                    $translated_segments[$k] = ee()->publisher_category->get_translated_url_title($segment, $lang_id_from, $lang_id_to);
                }

                foreach ($translated_segments as $k => $segment)
                {
                    $translated_segments[$k] = ee()->publisher_entry->get_translated_url_title($segment, $lang_id_from, $lang_id_to);
                }

                array_unshift($translated_segments, $segment_1);

                ee()->publisher_log->message($translated_segments, 'Returning: $translated_segments $lang_id_from: '. $lang_id_from .' => $lang_id_to: '. $lang_id_to);

                return $translated_segments;
            }

            // Still here? Try harder.
            // Its a template_group/template pattern, but a translated one?
            // e.g. {path="spanish/stuff"}, this could happen if user has something
            // like this in their template: {path="{segment_1}/{segment_2}"}
            if ($segment_1 = $this->get_translated_group($segments[0]))
            {
                // Get rid of the first, its the template group we just translated.
                array_shift($segments);

                $translated_segments = $segments;

                // Loop through the rest of the segments and get the translations, if any
                foreach ($translated_segments as $k => $segment)
                {
                    if ($seg = $this->get_translated_template($segment))
                    {
                        $translated_segments[$k] = $seg;
                    }
                }

                foreach ($translated_segments as $k => $segment)
                {
                    $translated_segments[$k] = ee()->publisher_category->get_translated_url_title($segment);
                }

                foreach ($translated_segments as $k => $segment)
                {
                    $translated_segments[$k] = ee()->publisher_entry->get_translated_url_title($segment);
                }

                array_unshift($translated_segments, $segment_1);

                ee()->publisher_log->message($translated_segments, 'Returning: $translated_segments tried harder :(');

                return $translated_segments;
            }

            // Ok, one last chance. If we're this far it means it is not a template_group/template pattern
            // so see if the URI contains url_titles and/or category/category-names.
            $translated_segments = $segments;

            foreach ($translated_segments as $k => $segment)
            {
                $translated_segments[$k] = ee()->publisher_category->get_translated_url_title($segment, $lang_id_from, $lang_id_to);
            }

            foreach ($translated_segments as $k => $segment)
            {
                $translated_segments[$k] = ee()->publisher_entry->get_translated_url_title($segment, $lang_id_from, $lang_id_to);
            }

            // If the array is different it means we have found translations, so return it.
            $diff = array_diff($segments, $translated_segments);
            if ( !empty($diff))
            {
                ee()->publisher_log->message($translated_segments, 'Returning: $translated_segments with no template group.');

                return $translated_segments;
            }
        }

        ee()->publisher_log->message('FALSE', 'Returning: FALSE');

        return FALSE;
    }

    /**
     * Format the input field and language name
     *
     * @param  string  $field_name
     * @param  string  $value       saved field value
     * @param  string  $type        type of field, group or template
     * @param  integer $parent_id   if $type is a template, this will be the parent group_id
     * @return string
     */
    public function create_template_fields($save_name, $save_id, $type, $parent_id = NULL)
    {
        $fields = array();

        $translations = $this->get_translations();
        $routes = $this->get_route_translations();

        foreach (ee()->publisher_model->languages as $lang_id => $language)
        {
            $value = '';

            if (isset($translations[$type][$save_name][$save_id][$lang_id]))
            {
                $value = $translations[$type][$save_name][$save_id][$lang_id];
            }

            if ($lang_id != ee()->publisher_lib->default_lang_id)
            {
                if (version_compare(APP_VER, '2.8', '>=') && $type == 'template')
                {
                    $route = '';

                    if (isset($translations[$type][$save_name][$save_id][$lang_id]))
                    {
                        $value = $translations[$type][$save_name][$save_id][$lang_id];
                    }

                    if (isset($routes['route'][$save_name][$save_id][$lang_id]))
                    {
                        $route = $routes['route'][$save_name][$save_id][$lang_id]['route'];
                    }

                    $field = form_input($type.'['. $save_id .']['. $lang_id .']', $value) .'<span>Route</span> '.
                             form_input('route['. $save_id .']['. $lang_id .']', $route);
                }
                else
                {
                    $field = form_input($type.'['. $save_id .']['. $lang_id .']', $value);
                }

                $field .= form_hidden('parent['. $save_id .']['. $lang_id .']', $parent_id);

                $fields[] = '<p class="publisher-template-field"><span>'. $language['long_name'] .'</span> '. $field .'</p>';
            }
        }

        return implode(' ', $fields);
    }

    /**
     * Save the translated template groups and template names
     *
     * @return void
     */
    public function save_translations()
    {
        $post = ee()->security->xss_clean($_POST);

        $routes = array();
        $parents = array();

        // We don't want route or parent in the post
        // array as we're looping over it, otherwise
        // it'll get added as a new row.
        if (isset($post['route']))
        {
            $routes = $post['route'];
            unset($post['route']);
        }

        if (isset($post['parent']))
        {
            $parents = $post['parent'];
            unset($post['parent']);
        }

        $parent_id = NULL;

        foreach ($post as $template_type => $template_data)
        {
            if ( !is_array($template_data))
            {
                continue;
            }

            foreach ($template_data as $type_id => $type_data)
            {
                foreach ($type_data as $lang_id => $translated_name)
                {
                    $route = '';
                    $parent_id = '';

                    if ($template_type == 'template' && isset($routes[$type_id][$lang_id]))
                    {
                        $route = $routes[$type_id][$lang_id];
                    }

                    if ($template_type == 'template' && isset($parents[$type_id][$lang_id]))
                    {
                        $parent_id = $parents[$type_id][$lang_id];
                    }

                    $data = array(
                        'type'            => $template_type,
                        'type_id'         => $type_id,
                        'lang_id'         => $lang_id,
                        'parent_id'       => $parent_id,
                        'translated_name' => $translated_name,
                        'route'           => $route

                    );

                    $where = $data;
                    unset($where['translated_name']);
                    unset($where['route']);
                    unset($where['parent_id']);

                    $this->insert_or_update('publisher_templates', $data, $where);
                }
            }
        }
    }

    /**
     * Get the saved preview templates
     *
     * @return array
     */
    public function get_all_previews()
    {
        $qry = ee()->db->where('site_id', ee()->publisher_lib->site_id)
                            ->get('publisher_previews');

        $previews = array();

        foreach ($qry->result_array() as $row)
        {
            $previews[$row['channel_id']] = $row;
        }

        return $previews;
    }

    /**
     * Get the preview template data for a requested channel
     *
     * @param  integer $channel_id
     * @return array
     */
    public function get_preview($channel_id = 1, $build_full_url = FALSE, $swaps = array())
    {
        $site_url = ee()->publisher_helper_url->get_site_url();

        $qry = ee()->db
            ->where('site_id', ee()->publisher_lib->site_id)
            ->where('channel_id', $channel_id)
            ->get('publisher_previews');

        $result = $qry->result_array();
        $preview = isset($result[0]) ? $result[0] : FALSE;

        if ( !$preview)
        {
            return FALSE;
        }

        if (ee()->publisher_helper_url->should_add_prefix())
        {
            $prefix = ee()->publisher_lib->lang_code;
            $site_url = ee()->publisher_helper_url->set_prefix($site_url, FALSE, $prefix) . '/';
        }

        if ( !$build_full_url)
        {
            $preview = ee()->publisher_helper_url->set_prefix($preview, FALSE, $prefix);
            return $preview;
        }

        $url = $site_url . $this->get_template_path($preview['template_id']) .'/';

        // If we have a full override prefix it with the site_url and be done with it.
        if (isset($preview['override']) && $preview['override'] != '')
        {
            $url = $site_url . trim($preview['override'], '/');
            $url = $this->swap_vars($url, $swaps);
            return reduce_double_slashes($url);
        }

        // We need to assemble the full URL and parse it.
        if (isset($preview['append']) AND $preview['append'] != '')
        {
            if ($preview['append'] == 'custom')
            {
                $url .= $preview['custom'];
            }
            else
            {
                $url .= '{'. $preview['append'] .'}';
            }

            $url = $this->swap_vars($url, $swaps);
        }

        return reduce_double_slashes($url);
    }

    /**
     * Do a simple var swap on a string
     *
     * @param  string $string
     * @param  array  $swaps
     * @return string
     */
    private function swap_vars($string = '', $swaps = array())
    {
        if ( !empty($swaps))
        {
            foreach ($swaps as $key => $value)
            {
                $string = str_replace('{'. $key .'}', $value, $string);
            }
        }

        return $string;
    }

    /**
     * Save the translated template groups and template names
     *
     * @return void
     */
    public function save_previews()
    {
        $post = ee()->security->xss_clean($_POST);

        foreach ($post['previews'] as $channel_id => $template_id)
        {
            $where = array(
                'channel_id'    => $channel_id,
                'site_id'       => ee()->publisher_lib->site_id
            );

            $append = isset($post['append'][$channel_id]) ? $post['append'][$channel_id] : '';
            $custom = isset($post['custom'][$channel_id]) ? $post['custom'][$channel_id] : '';
            $override = isset($post['override'][$channel_id]) ? $post['override'][$channel_id] : '';

            $data = array(
                'template_id'   => $template_id,
                'append'        => $append,
                'custom'        => $custom,
                'override'      => $override
            );

            $data = array_merge($where, $data);

            $this->insert_or_update('publisher_previews', $data, $where);
        }
    }

    /**
     * Get all the template_group and template translations from the settings.
     *
     * @return array
     */
    public function get_translations()
    {
        $qry = ee()->db->get('publisher_templates');

        $translations = array();

        foreach ($qry->result() as $row)
        {
            if ($row->type == 'group')
            {
                $name = $this->get_group_name($row->type_id);
            }
            else
            {
                $name = $this->get_template_name($row->type_id);
            }

            if ($name)
            {
                $translations[$row->type][$name][$row->type_id][$row->lang_id] = $row->translated_name;
            }
        }

        return $translations;
    }

    /**
     * Get all the native routes
     *
     * @param  boolean $as_options Return as key/value pair or full route row?
     * @return array
     */
    public function get_routes($as_options = TRUE)
    {
        if (version_compare(APP_VER, '2.8', '<'))
        {
            return array();
        }

        $qry = ee()->db->get('template_routes');

        $routes = array();

        if ($as_options)
        {
            $routes[] = '- Select -';
        }

        foreach ($qry->result() as $row)
        {
            if ($as_options)
            {
                $routes[$row->route_id] = $row->route;
            }
            else
            {
                $routes[$row->route_id] = $row;
            }
        }

        return $routes;
    }

    /**
     * Get all the routes for the templates
     *
     * @return array
     */
    public function get_route_translations()
    {
        $qry = ee()->db->get('publisher_templates');

        $routes = array();

        foreach ($qry->result() as $row)
        {
            if ($row->type == 'template')
            {
                $name = $this->get_template_name($row->type_id);

                if ($name && isset($row->route) && isset($row->parent_id))
                {
                    $routes['route'][$name][$row->type_id][$row->lang_id] = array(
                        'route' => $row->route,
                        'parent_id' => $row->parent_id
                    );
                }
            }
        }

        return $routes;
    }

    public function load_routes()
    {
        if (version_compare(APP_VER, '2.8', '<'))
        {
            return;
        }

        $lang_id = ee()->publisher_lib->lang_id;
        $translations = $this->get_translations();
        $routes = $this->get_route_translations();

        ee()->load->library('template_router');

        if (empty($translations))
        {
            return;
        }

        // Organize groups by id so we can reference them with parent_id
        $groups = array();
        foreach ($translations['group'] as $group_name => $group_data)
        {
            foreach ($group_data as $group_id => $translation_data)
            {
                $groups[$group_id] = $group_name;
            }
        }

        foreach ($translations['template'] as $template_name => $template_data)
        {
            foreach ($template_data as $template_id => $translation_data)
            {
                foreach ($translation_data as $lang_id => $translation)
                {
                    // We are only wanting to set translated routes.
                    // Default language routes are handled in Design > Templates > Access
                    if ($lang_id != ee()->publisher_lib->lang_id)
                    {
                        continue;
                    }

                    $route = NULL;
                    $parent_id = NULL;
                    $group_name = NULL;

                    if (isset($routes['route'][$template_name][$template_id][$lang_id]['route']))
                    {
                        $route = $routes['route'][$template_name][$template_id][$lang_id]['route'];
                    }

                    if (isset($routes['route'][$template_name][$template_id][$lang_id]['parent_id']))
                    {
                        $parent_id = $routes['route'][$template_name][$template_id][$lang_id]['parent_id'];
                        $group_name = isset($groups[$parent_id]) ? $groups[$parent_id] : NULL;
                    }

                    if ($route && $group_name && $template_name)
                    {
                        $template_route = ee()->template_router->create_route($route);
                        $route_parsed = $template_route->compile();

                        ee()->template_router->end_points[$route_parsed] = array(
                            'template' => $template_name,
                            'group'    => $group_name
                        );
                    }
                }
            }
        }
    }

    /**
     * Given the current language value of a group name find the default.
     * This is used mostly for the core_template_route hook so when viewing
     * a translated URL EE knows which template to actually load.
     * @param  string $val
     * @return string
     */
    public function get_default_group($val, $return_val = FALSE)
    {
        $this->build_template_collection();

        if (isset($this->cache['templates']['group'][ee()->publisher_lib->lang_id]))
        {
            // Given the translated value, find the default version of the group name.
            if ($key = array_search($val, $this->cache['templates']['group'][ee()->publisher_lib->lang_id]))
            {
                return $this->cache['templates']['group'][ee()->publisher_lib->default_lang_id][$key];
            }
        }

        if (isset($this->cache['templates']['group'][ee()->publisher_lib->default_lang_id]))
        {
            // Given the default value, see if the segment is a group name
            if ($key = array_search($val, $this->cache['templates']['group'][ee()->publisher_lib->default_lang_id]))
            {
                return $this->cache['templates']['group'][ee()->publisher_lib->default_lang_id][$key];
            }
        }

        return $return_val ? $val : FALSE;
    }

    /**
     * Given a group name (translated or default), find the equivelant
     * version in the current language. Default behavior is to use the previous
     * language ID when switching languages to find the new language translation.
     * Optionally always use the default language ID for the lookup, which is
     * used for template path translations.
     * @param  string  $val
     * @param  boolean $by_default
     * @return string
     */
    public function get_translated_group($val, $lang_id_from = NULL, $lang_id_to = NULL)
    {
        $this->build_template_collection();

        // Usually this isn't needed but on some form submits, e.g. Safecracker, it reloads the current page and throws
        // an error b/c publisher_lib->prev_lang_id isn't set. Not sure if there is a better way to approach this?
        $prev_lang_id = isset(ee()->publisher_lib->prev_lang_id) ? ee()->publisher_lib->prev_lang_id : ee()->publisher_lib->default_lang_id;

        $lang_id_from = $lang_id_from ?: $prev_lang_id;
        $lang_id_to   = $lang_id_to   ?: ee()->publisher_lib->lang_id;

        if (isset($this->cache['templates']['group'][$lang_id_from]))
        {
            // var_dump($val, $this->cache['templates']['group'][$lang_id]);
            // Given the default value, find the translated version of the group name.
            if ($key = array_search($val, $this->cache['templates']['group'][$lang_id_from]))
            {
                if (isset($this->cache['templates']['group'][$lang_id_to][$key]))
                {
                    return $this->cache['templates']['group'][$lang_id_to][$key];
                }
            }
        }

        return FALSE;
    }

    /**
     * Given the current language value of a template name find the default.
     * This is used mostly for the core_template_route hook so when viewing
     * a translated URL EE knows which template to actually load.
     * @param  string $val
     * @return string
     */
    public function get_default_template($val, $return_val = FALSE)
    {
        $this->build_template_collection();

        if (isset($this->cache['templates']['template'][ee()->publisher_lib->lang_id]))
        {
            // Given the translated value, find the default version of the group name.
            if ($key = array_search($val, $this->cache['templates']['template'][ee()->publisher_lib->lang_id]))
            {
                if (isset($this->cache['templates']['template'][ee()->publisher_lib->default_lang_id][$key]))
                {
                    return $this->cache['templates']['template'][ee()->publisher_lib->default_lang_id][$key];
                }
            }
        }

        if (isset($this->cache['templates']['template'][ee()->publisher_lib->default_lang_id]))
        {
            // Given the default value, see if the segment is a template name
            if ($key = array_search($val, $this->cache['templates']['template'][ee()->publisher_lib->default_lang_id]))
            {
                if (isset($this->cache['templates']['template'][ee()->publisher_lib->default_lang_id][$key]))
                {
                    return $this->cache['templates']['template'][ee()->publisher_lib->default_lang_id][$key];
                }
            }
        }

        return $return_val ? $val : FALSE;
    }

    /**
     * Given a template name (translated or default), find the equivelant
     * version in the current language. Default behavior is to use the previous
     * language ID when switching languages to find the new language translation.
     * Optionally always use the default language ID for the lookup, which is
     * used for template path translations.
     * @param  string  $val
     * @param  boolean $by_default
     * @return string
     */
    public function get_translated_template($val, $lang_id_from = NULL, $lang_id_to = NULL)
    {
        $this->build_template_collection();

        $lang_id_from = $lang_id_from ?: ee()->publisher_lib->prev_lang_id;
        $lang_id_to   = $lang_id_to   ?: ee()->publisher_lib->lang_id;

        if (isset($this->cache['templates']['template'][$lang_id_from]))
        {
            // Given the default value, find the translated version of the group name.
            if ($key = array_search($val, $this->cache['templates']['template'][$lang_id_from]))
            {
                if (isset($this->cache['templates']['template'][$lang_id_to][$key]))
                {
                    return $this->cache['templates']['template'][$lang_id_to][$key];
                }
            }
        }

        return FALSE;
    }

    /**
     * Create an array of the template groups and templates for later reference
     * to avoid multiple uncessary queries.
     * @return void
     */
    public function build_template_collection()
    {
        if ( !isset($this->cache['templates']))
        {
            $this->cache['templates'] = array();

            // Get our default groups
            $qry = ee()->db->get('template_groups');

            foreach ($qry->result() as $row)
            {
                $this->cache['templates']['group'][ee()->publisher_lib->default_lang_id][$row->group_id] = $row->group_name;
            }

            // Get default templates
            $qry = ee()->db->get('templates');

            foreach ($qry->result() as $row)
            {
                $this->cache['templates']['template'][ee()->publisher_lib->default_lang_id][$row->template_id] = $row->template_name;
            }

            // And finally translated ones
            $qry = ee()->db->get('publisher_templates');

            foreach ($qry->result() as $row)
            {
                $this->cache['templates'][$row->type][$row->lang_id][$row->type_id] = $row->translated_name;
            }
        }
    }
}