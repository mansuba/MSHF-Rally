<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher URL Model Class
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

class Publisher_helper_url
{
    public $translated_uri = '';
    public $wtf = FALSE;

    public function __construct()
    {
        // Create cache
        if (! isset(ee()->session->cache['publisher']))
        {
            ee()->session->cache['publisher'] = array();
        }
        $this->cache =& ee()->session->cache['publisher'];
    }

    /**
     * Handle browser redirects. Can't use EE's b/c the session
     * object has not been created yet when I need it.
     * @param  string $url  destination
     * @param  string $type
     * @return void
     */
    public function redirect($location, $type = 302)
    {
        // Remove hard line breaks and carriage returns
        $location = str_replace(array("\n", "\r"), '', $location);

        // Remove any and all line breaks
        while (stripos($location, '%0d') !== FALSE OR stripos($location, '%0a') !== FALSE)
        {
            $location = str_ireplace(array('%0d', '%0a'), '', $location);
        }

        $location = str_replace('&amp;', '&', $location);

        if ($type === 301)
        {
            header ('HTTP/1.1 301 Moved Permanently');
        }

        header("Location: $location", TRUE, $type);
        exit;
    }

    /**
     * Generate a link to the CP and a specific page based on the params
     * @param Array
    */
    public function get_cp_url(Array $params = array())
    {
        $query = '';

        if ( !empty($params))
        {
            // Make sure this is set, otherwise it can log the user out.
            if (FALSE !== $session_id = ee()->input->get('S'))
            {
                $params['S'] = $session_id;
            }

             // Zenbu support - redirect to Zenbu edit screen - thanks Mark Croxton
            if (isset($params['C']) && $params['C'] === 'content_edit' && array_key_exists('zenbu', ee()->addons->get_installed('modules')))
            {
                $params['C']      = 'addons_modules';
                $params['M']      = 'show_module_cp';
                $params['module'] = 'zenbu';
            }

            $query = '&'.http_build_query($params);
        }

        return ee()->config->item('cp_url').'?D=cp'. str_replace('%23', '#', $query);
    }

    /**
     * Get the EE home page
     * @return string
     */
    public function get_site_index()
    {
        $site_index = ee()->config->item('site_index');
        $index_page = ee()->config->item('index_page');

        $index = ($site_index != '') ? $site_index : (($index_page != '') ? $index_page : '');

        if ($index != '' && APP_VER < '2.5.1' OR substr($index, -1) !== '/')
        {
            $index .= '/';
        }

        if (isset(ee()->config->_global_vars['root_url']))
        {
            $site_url = ee()->config->_global_vars['root_url'];
        }
        else
        {
            $site_url = ee()->config->slash_item('site_url');
        }

        // Language switching works better if we have a full domain.
        if ($site_url == '/')
        {
            $site_url = ee()->publisher_session->get_current_url(FALSE);
        }

        if (substr($site_url, -1) !== '/')
        {
            $site_url .= '/';
        }

        return reduce_double_slashes($site_url . $index);
    }

    /**
     * Sometimes we need to remove the full base/index url and just get the uri/segments
     *
     * @param  string $url
     * @return string
     */
    public function remove_site_index($url)
    {
        // Make sure it has a trailing slash before replace otherwise the replace won't work.
        if (substr($url, -1, 1) !== '/') $url .= '/';

        // First try to remove the full index if its present, we just want segments.
        $url = str_replace($this->get_site_index(), '', $url);

        // site_index may contain index.php, so try to find url without out.
        if ($this->get_site_url() != '/')
        {
            $url = str_replace($this->get_site_url(), '', $url);
        }

        // Finally, remove the current base url from the string if it exists.
        return str_replace(ee()->publisher_session->get_current_url(FALSE), '', $url);
    }

    /**
     * Translate a URL or just set the prefix depending on the settings
     *
     * @param  string $url
     * @return string
     */
    public function swap_url($url, $add_site_url = TRUE)
    {
        // If we are translating urls then no need for most of this nonsense.
        if (ee()->publisher_setting->url_translations())
        {
            $url = $this->get_translated_url($url);
        }
        elseif ($this->should_add_prefix())
        {
            // We have a full URL, so don't pass segments
            if (strpos($url, 'http') !== FALSE)
            {
                $url = $this->set_prefix($url);
            }
            // Yes, sending $url twice, 2nd param is expecting a segments array.
            // set_prefix creates the array out of the string value.
            else
            {
                $url = $this->set_prefix($url, $url);
            }
        }

        if ( !$add_site_url)
        {
            $url = $this->remove_site_index($url);
        }

        return $url;
    }

    /**
     * Get the site_url
     * @return  string
     */
    public function get_site_url()
    {
        return ee()->config->item('site_url');
    }

    /**
     * Fetch an ACT id, mostly used in CP for Ajax requests.
     * @param  string $method
     * @param  string $class
     * @param  array  $params
     * @return string
     */
    public function get_action($method, $class = 'Publisher', $params = array())
    {
        if ( !isset($this->cache['action'][$method]))
        {
            $qry = ee()->db->select('action_id')
                                ->where('class', $class)
                                ->where('method', $method)
                                ->get('actions')
                                ->row();

            if ( !$qry)
            {
                return '';
            }
            else
            {
                if ( !empty($params))
                {
                    return $this->get_site_index() . '?ACT='. $qry->action_id .'&'. http_build_query($params);
                }
                else
                {
                    return $this->get_site_index() . '?ACT='. $qry->action_id .'&site_id='. ee()->publisher_lib->site_id;
                }
            }
        }

        return $this->cache['action'][$method];
    }

    /**
     * Get the ACT ID for a requested method
     * @param  string $method The MCP/MOD method
     * @param  string $class
     * @return int
     */
    public function get_action_id($method, $class = 'Publisher')
    {
        $qry = ee()->db->select('action_id')
                            ->where('class', $class)
                            ->where('method', $method)
                            ->get('actions')
                            ->row();

        return $qry->action_id;
    }

    /**
     * Takes what were the untranslated/default segments in EE
     * and creates new variables for them so they can still
     * be referenced in templates and used in tags, e.g.
     *
     * {exp:channel:entries url_title="{publisher:segment_3}"}
     *
     * @param array $segments
     */
    public function set_publisher_segments($segments = array())
    {
        $last_segment = '';

        // re-index it so it always starts at 0 index.
        $segments = array_values($segments);

        // Remove lang code if its in the array
        if (isset($segments[0]) AND in_array($segments[0], ee()->publisher_session->language_codes))
        {
            array_shift($segments);
        }

        // Set all 9 defaults so they render properly in templates.
        for ($i = 1; $i <= 9; $i++)
        {
            $v = isset($segments[$i-1]) ? $segments[$i-1] : '';
            ee()->config->_global_vars['publisher:segment_'. $i] = $v;

            if ($v != '') $last_segment = $v;
        }

        // And set the last one
        ee()->config->_global_vars['publisher:last_segment'] = $last_segment;
    }

    /**
     * Get the current URL segments. This is really only used in
     * language switching, so we need tracker b/c that actually saves
     * the previous page segments. In the middle of the switch action
     * we need the previous segments b/c the current ones won't be valid.
     * @param boolean $use_tracker Option to skip to segments array.
     * @return array
     */
    public function get_current_segments($use_tracker = TRUE, $remove_segments = TRUE)
    {
        // This is only called if the site_pages->get_url() method
        // is called from within the CP. We don't have any segments
        // then, so just return an empty array.
        if (REQ == 'CP')
        {
            return array();
        }

        if ($use_tracker && isset(ee()->session->tracker) && isset(ee()->session->tracker[0]))
        {
            $segments = explode('/', ee()->session->tracker[0]);

            // The tracker sets the current page to index if its the home page.
            if (isset($segments[0]) && $segments[0] == 'index')
            {
                $segments = array();
            }

            return $segments;
        }
        else
        {
            if (ee()->publisher_lib->switching)
            {
                $segments = ee()->publisher_session->get_tracker('segments');
            }
            else
            {
                $segments = ee()->uri->segments;
            }

            // Make sure we're using a zero indexed array to match what tracker returns
            $segments = array_values($segments);

            // Remove the langauge prefix if its already in the segments
            if ($remove_segments && !empty($segments) && ee()->publisher_setting->url_prefix() && in_array($segments[0], ee()->publisher_session->language_codes))
            {
                array_shift($segments);
            }

            return $segments;
        }
    }

    /**
     * Make sure double language codes don't happen
     * @param  string $url
     * @return string
     */
    public function remove_double_codes($url, $code = NULL)
    {
        $code = $code !== NULL ? $code : ee()->publisher_session->requested_language_code;

        // Did we somehow end up with a double language code in the URL? Kind of a janky fix if you ask me :/
        // This seemed to only happen when force_default_language is true.
        if (strstr($url, $code.'/'.$code))
        {
            $url = str_replace(
                $code.'/'.$code,
                $code,
                $url
            );
        }

        return $url;
    }

    /**
     * Search the template groups and site_pages array to see if the url is valid
     * @param  string  $segment segment_1
     * @return boolean
     */
    public function is_valid_url($segment)
    {
        if ($segment)
        {
            // See if its a valid template group first. Easiest lookup.
            ee()->publisher_template->build_template_collection();

            if (isset($this->cache['templates']['group']) &&
                in_array($segment, $this->cache['templates']['group'][ee()->publisher_lib->default_lang_id])
            ){
                return TRUE;
            }
        }

        // See if its in the site_pages array
        $site_pages = ee()->publisher_site_pages->get();

        if ( !empty($site_pages[ee()->publisher_lib->site_id]['uris']))
        {
            $site_pages = $site_pages[ee()->publisher_lib->site_id]['uris'];

            $uri_string = explode('/', ee()->uri->uri_string);

            // Our first segment is a valid template group or page url title
            if (isset(ee()->publisher_lib->lang_code) AND $uri_string[0] != ee()->publisher_lib->lang_code)
            {
                $page_url = '/'. ee()->uri->uri_string;
            }
            else
            {
                array_shift($uri_string);

                $uri_string = implode('/', $uri_string);

                $page_url = '/'. $uri_string;
            }

            // Aside from the incorrect language code the rest of the URL is valid.
            if (array_search($page_url, $site_pages))
            {
                return TRUE;
            }

            // Last chance, to redeem, if the first segment is a valid page.
            if (array_search('/'.$segment, $site_pages))
            {
                return TRUE;
            }
        }

        // @todo - look into publisher_templates for translated templates next

        return FALSE;
    }

    /**
     * See if its our ?ACT=x url when switching languages
     *
     * @param  string  $url
     * @return boolean
     */
    public function is_switch_url($url = NULL)
    {
        // Check the referrer against our language switch URL
        $url = $url ? $url : ee()->publisher_session->get_referrer_url();
        $action_id = '?ACT='. ee()->publisher_model->fetch_action_id('set_language');

        if ($url && strstr($url, $action_id) !== FALSE)
        {
            return TRUE;
        }

        return FALSE;
    }

    /*
     * Check the segments for the 32 string md5 hash. If one is found then it's a search results page.
     * Optionally return the new URI, which is everything including and after the hash.
     * For some reason EE doesn't want the segments prior to the hash. See mod.search.php ~ln 1281.
     *
     * @param  array   $segments
     * @param  boolean $return_uri
     * @return mixed
     */
    public function is_search_url($segments = array(), $return_uri = FALSE)
    {
        $segments = !empty($segments) ? $segments : ee()->uri->segments;

        $hash_segment = 0;
        $is_search_url = FALSE;

        foreach ($segments as $k => $segment)
        {
            if (preg_match('/^[a-f0-9]{32}$/', $segment))
            {
                $hash_segment = $k;
                $is_search_url = TRUE;
            }
        }

        $sliced_segments = array_slice($segments, $hash_segment);
        $new_segments = implode('/', $sliced_segments);

        return $return_uri ? $new_segments : $is_search_url;
    }

    /**
     *  If not removing index.php from the URL the redirection can get
     *  fubared if it is not followed by a trailing slash.
     *
     * @param   string $url
     * @return  string
     */
    public function add_trailing_slash($url)
    {
        $site_index = ee()->config->item('site_index');

        if ($site_index && strpos($url, $site_index) !== FALSE && strpos($url, '.php/') === FALSE)
        {
            $url = str_replace($site_index, $site_index.'/', $url);
        }

        return $url;
    }

    /**
     * Decide if we should add the url prefix
     *
     * @param   integer $lang_id_to
     * @return  boolean
     */
    public function should_add_prefix($lang_id_to = NULL)
    {
        if (PUBLISHER_LITE)
        {
            return FALSE;
        }

        $add_prefix = TRUE;
        $lang_id = $lang_id_to ? (int) $lang_id_to : (int) ee()->publisher_lib->lang_id;

        if (ee()->publisher_setting->hide_prefix_on_default_language() &&
            (int) ee()->publisher_lib->default_lang_id === $lang_id
        ){
            $add_prefix = FALSE;
        }

        if (ee()->publisher_setting->force_prefix() && ee()->publisher_setting->url_prefix() && $add_prefix)
        {
            return TRUE;
        }

        if (ee()->publisher_setting->url_prefix() && $add_prefix)
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Remove a language prefix from a URL
     *
     * @param  string $url
     * @return string
     */
    public function remove_prefix($url)
    {
        $requested_url = $url;
        $site_index = $this->get_site_index();

        // Remove the full base URL so we don't accidently replace part of it
        $url = str_replace($site_index, '', $url);

        $segments = explode('/', trim($url, '/'));

        if (isset($segments[0]) && in_array($segments[0], ee()->publisher_session->language_codes))
        {
            array_shift($segments);

            return reduce_double_slashes($site_index . implode('/', $segments));
        }
        else
        {
            return $requested_url;
        }
    }

    /**
     * Send an array of segments, return the language prefix if it exists
     *
     * @param array $segments [description]
     * @return string
     */
    public function get_prefix($segments = array())
    {
        $segments = !empty($segments) ? $segments : ee()->uri->segments;

        if ( !is_array($segments))
        {
            $segments = explode('/', trim($segments, '/'));
        }

        // Make it a 0 indexed array.
        $segments = array_values($segments);

        if (isset($segments[0]) && in_array($segments[0], ee()->publisher_session->language_codes))
        {
            return $segments[0];
        }

        return '';
    }

    /**
     * Set the proper language prefix in the URL
     *
     * @param string $url      Current URL, or desired URL string to fix
     * @param array  $segments Current EE URL segments, or set array from cookie
     * @return string New URL
     */
    public function set_prefix($url, $segments = FALSE, $prefix = FALSE)
    {
        $requested_url = $url;

        $site_index = $this->get_site_index();

        $segments = $segments ? $segments : $this->get_current_segments(FALSE);

        if ( !is_array($segments))
        {
            $segments = explode('/', trim($segments, '/'));
        }

        if ( !$this->should_add_prefix(ee()->publisher_session->requested_language_id))
        {
            if (ee()->publisher_lib->default_lang_id == ee()->publisher_session->requested_language_id)
            {
                $requested_url = $this->remove_prefix($requested_url);
            }

            return $requested_url;
        }

        // Push the current language code onto the beginning of the segments array
        array_unshift($segments, ee()->publisher_lib->lang_code);

        $prefix = $prefix ? $prefix : ee()->publisher_session->requested_language_code;

        // Remove the full base URL so we don't accidently replace part of it with a language code
        $url = str_replace($site_index, '', $url);

        if ( !empty($segments))
        {
            $segments_before = implode('/', $segments);

            // Is it a valid site_pages URL or a template_group/template URL?
            if ($this->is_valid_url($segments[0]) AND !in_array($segments[0], ee()->publisher_session->language_codes))
            {
                $segments[0] = $prefix .'/'. $segments[0];
            }
            else
            {
                $segments[0] = $prefix;
            }

            $segments_after = implode('/', $segments);

            $url = str_replace($segments_before, $segments_after, $url);

            // Make sure the segments we're looking to replace even exist
            if (strstr($url, $segments_before))
            {
                $url = str_replace($segments_before, $segments_after, $url);
            }
            // If not, just set the full URL to the segments.
            else
            {
                $url = $segments_after;
            }
        }
        else
        {
            $url = $url .'/'. $prefix;
        }

        $url = $site_index . $url;

        return reduce_double_slashes($url);
    }

    /**
     * Legacy, just incase any devs were using this
     */
    public function get_alternate_url($url = NULL, $lang_id_from = NULL, $lang_id_to = NULL, $default_segments = NULL)
    {
        return $this->get_translated_url($url, $lang_id_from, $lang_id_to, $default_segments);
    }

    /**
     * Take the existing URL and translate it from one language to another.
     * @param  string   $url              URL to translate
     * @param  integer  $lang_id_from
     * @param  integer  $lang_id_to
     * @param  string   $default_segments If passed overrides the default_segments lookup
     * @return string
     */
    public function get_translated_url($url = NULL, $lang_id_from = NULL, $lang_id_to = NULL, $default_segments = NULL)
    {
        $is_page = FALSE;

        // If no lang_ids are defined, assume translating from default to current.
        $lang_id_from = $lang_id_from ? $lang_id_from : ee()->publisher_lib->default_lang_id;
        $lang_id_to   = $lang_id_to   ? $lang_id_to   : ee()->publisher_lib->lang_id;

        // Was a URL requested? Create for reference later.
        $requested_url = $url;
        $this->translated_uri = '';

        $url = $url ? $url : ee()->publisher_session->get_current_url();

        $segments = $this->get_current_segments(FALSE, FALSE);

        // Don't try to translate Action urls
        if (strpos($url, '?ACT=') !== FALSE)
        {
            return $url;
        }

        $cache_key = 'get_translated_url/'. $lang_id_from .'/'. $lang_id_to .'/'. md5($url);

        // Using Cache?
        if (($cache_results = ee()->publisher_cache->driver->get($cache_key)) !== FALSE)
        {
            return $cache_results;
        }

        // Decode the multi-byte encharacters so it matches what is in the site_pages
        // array or a template file name. I have no idea what this says, but it
        // turns a URL like this %E5%85%B3%E4%BA%8E into this 关于
        $url = $this->decode_url($url);

        // First look to see if its a static page and we have a translation for it.
        $page_url = ee()->publisher_site_pages->find_related($url, $lang_id_from, $lang_id_to);

        // If different than current URL we can assume its a translation.
        if ($page_url && ee()->publisher_lib->switching)
        {
            if ( !$this->should_add_prefix())
            {
                $page_url = $this->remove_prefix($page_url);
            }

            return $page_url;
        }
        else if ($page_url && !ee()->publisher_lib->switching)
        {
            $is_page = TRUE;

            $url = $page_url;
        }

        $site_index = $this->get_site_index();

        // Remove the full base URL so we don't accidently replace part of it with a language code
        $url = $this->remove_site_index($url);

        if (ee()->publisher_setting->url_translations() && !$is_page)
        {
            $return = (ee()->publisher_lib->lang_id != ee()->publisher_lib->default_lang_id) ? FALSE : 'url_title';

            $segs = FALSE;

            // If a specific URL was sent to this method, instead of
            // grabbing the current, we need to send it as a segments array.
            if ($requested_url)
            {
                $segs = explode('/', trim($url, '/'));
            }

            $default_segments = $default_segments ? $default_segments : ee()->publisher_template->get_default_segments($segs, FALSE, FALSE, $return);

            // And again, make sure the segs are decoded.
            $default_segments = $this->decode_url($default_segments);

            if ($default_segments)
            {
                $segments = ee()->publisher_template->get_translated_segments($default_segments, ee()->publisher_lib->default_lang_id, $lang_id_to);

                // No translations found? Return the default.
                if ( !$segments)
                {
                    $segments = $default_segments;
                }
            }
            else
            {
                // get_translated_segments needs a 0 indexed array
                if ($segs)
                {
                    $segs = array_values($segs);
                }

                $segments = ee()->publisher_template->get_translated_segments($segs, $lang_id_from, $lang_id_to);

                // No translations found? Return the current.
                if ( !$segments)
                {
                    // Get from EE, and re-index so it starts at 0
                    $segments = array_values(ee()->uri->segments);
                }
            }
        }

        // If its a page, we already have a translation,
        // so explode it into usable segments.
        if ($is_page)
        {
            $segments = explode('/', $url);
        }

        if ($this->should_add_prefix($lang_id_to))
        {
            $segments_before = $url;
            $segments_after = '';

            $language_code = ee()->publisher_model->languages[$lang_id_to]['short_name_segment'];

            // Remove a language prefix if it exists, which it should, and replace it with the requested code.
            if (isset($segments[0]) && in_array($segments[0], ee()->publisher_session->language_codes))
            {
                $segments[0] = $language_code;
            }
            // If we don't have a language prefix, push it into the array
            else if (is_array($segments))
            {
                array_unshift($segments, $language_code);
            }

            if (is_array($segments))
            {
                $segments_after = '/'. implode('/', $segments);
            }

            $url = str_replace($segments_before, $segments_after, $url);
        }
        else if (is_array($segments))
        {
            if (isset($segments[0]) && in_array($segments[0], ee()->publisher_session->language_codes))
            {
                array_shift($segments);
            }

            $url = '/'. implode('/', $segments);
        }

        $url = $this->clean($url);

        // Save just the URI
        $this->translated_uri = $url;

        // Reassemble to a full URL
        $url = reduce_double_slashes($site_index . $url);

        ee()->publisher_cache->driver->save($cache_key, $url);

        return $url;
    }

    /**
     * Remove trailing slash if its EE 2.5+
     * @param  string $url
     * @return string
     */
    public function clean($url)
    {
        if (version_compare(APP_VER, '2.4', '>='))
        {
            return rtrim($url, '/');
        }

        return $url;
    }

    public function decode_url($url)
    {
        if (is_array($url))
        {
            foreach ($url as &$val)
            {
                $val = $this->_decode_url($val);
            }

            return $url;
        }
        else
        {
            return $this->_decode_url($url);
        }
    }

    private function _decode_url($url)
    {
        $parts = explode('/', $url);

        foreach ($parts as &$part)
        {
            $part = rawurldecode($part);
        }

        return implode('/', $parts);
    }

    /**
     * Given a string URL value, remove possible language code from it
     *
     * @param  String $url URL with language code (maybe)
     * @return String      Clean URL without language code
     */
    public function remove_language_code($url, $remove_site_index = FALSE)
    {
        $site_index = $this->get_site_index();

        // Remove the full base URL so we don't accidently replace part of it with a language code
        $url = str_replace($site_index, '', $url);
        $segs = explode('/', trim($url, '/'));

        // Remove the language code from the segments
        if (isset($segs[0]) && in_array($segs[0], ee()->publisher_session->language_codes))
        {
            array_shift($segs);
        }

        // If we have http in the first segment, then its a fully qualified domain, no need
        // to reassemble our URL, we actually return the $url value from above.
        if (isset($segs[0]) && substr($segs[0], 0, 4) != 'http')
        {
            $url = !$remove_site_index ? $site_index.implode('/', $segs) : implode('/', $segs);
        }

        return $url;
    }

    /**
     * Check to see if its a member or forum trigger word request
     * @return boolean
     */
    public function is_trigger_word()
    {
        $forum_trigger = (ee()->config->item('forum_is_installed') == "y") ? ee()->config->item('forum_trigger') : '';
        $profile_trigger = ee()->config->item('profile_trigger');

        if ($forum_trigger && in_array(ee()->uri->segment(1), preg_split('/\|/', $forum_trigger, -1, PREG_SPLIT_NO_EMPTY)))
        {
            return TRUE;
        }

        if ($profile_trigger && $profile_trigger == ee()->uri->segment(1))
        {
            return TRUE;
        }

        return FALSE;
    }
}