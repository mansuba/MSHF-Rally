<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Session Class
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

class Publisher_session
{
    /**
     * An array of language short_name codes
     * @var array
     */
    public $language_codes = array();

    /**
     * When switching languages, makes sure requests to these filetypes are ignored
     * otherwise the switcher can get stuck on the current language.
     * @var array
     */
    private $ignore_file_types = array(
        '.ico',
        '.css',
        '.js',
        '.html',
        '.json',
        '.xml',
        '.png',
        '.jpg',
        '.jpeg',
        '.gif',
        '.zip',
        '.pdf',
        '.tar',
        '.htc'
    );

    /**
     * EE Cookie prefix
     * @var string
     */
    private $prefix = '';

    /**
     * Language code of the default language
     * @var string
     */
    private $default_language_code = '';

    /**
     * Language ID for the current user's sesion
     * @var integer
     */
    private $session_language_id = 1;

    /**
     * The name of the cookie to save the current language in.
     * @var string
     */
    public $cookie_name = 'site_language';

    /**
     * Set our class properties and session cookies
     */
    public function __construct()
    {
        // Figure out which cookie to request
        $this->prefix = !ee()->config->item('cookie_prefix') ?
            'exp_publisher_' :
            ee()->config->item('cookie_prefix').'_publisher_';

        $this->cookie_name = REQ == 'CP' ?
            $this->prefix.$this->cookie_name .'_cp' :
            $this->prefix.$this->cookie_name;

        // Grab our codes and language IDs
        $this->language_codes = ee()->publisher_model->get_language_codes(TRUE);

        if ( !isset($this->language_codes[ee()->publisher_lib->default_lang_id]))
        {
            show_error('The default language was not found. Clear your cookies and reload the page.');
        }

        $this->default_language_code = $this->language_codes[ee()->publisher_lib->default_lang_id];
        $this->session_language_id   = $this->get('site_language');

        // Set default value for when Publisher is disabled.
        ee()->publisher_lib->lang_code = $this->default_language_code;

        if (REQ == 'CP' AND ee()->publisher_setting->force_default_language_cp())
        {
            $this->session_language_id = ee()->publisher_lib->default_lang_id;
            $this->set_cookie($this->session_language_id);
        }

        if (REQ == 'PAGE' AND ee()->publisher_setting->force_default_language())
        {
            $this->session_language_id = ee()->publisher_lib->default_lang_id;
            $this->set_cookie($this->session_language_id);
        }

        if (REQ != 'CP' && ($lang_code = ee()->config->item('publisher_lang_override')))
        {
            if ($lang_id = array_search($lang_code, $this->language_codes))
            {
                $this->session_language_id = $lang_id;
                $this->set_cookie($this->session_language_id);
            }
        }
    }

    /**
     * Kick it off, handle session/cookies, url redirections and more
     * @return void
     */
    public function go()
    {
        // -------------------------------------------
        //  'publisher_session_start' hook
        //      - No parameters. Modify the ee()->publisher_lib properties if needed.
        //
            if (ee()->extensions->active_hook('publisher_session_start'))
            {
                ee()->extensions->call('publisher_session_start');
            }
        //
        // -------------------------------------------

        $this->set_constants();

        // Get the installed version of Publisher
        $addons = ee()->addons->get_installed();
        define('PUBLISHER_VERSION_INSTALLED', $addons['publisher']['module_version']);

        // Set this before site_url is updated, thus breaking a lot of things.
        ee()->config->_global_vars['root_url'] = ee()->config->item('site_url');

        // Its an ?ACT=X&lang_id=X request.
        if (($lang_id = ee()->input->get_post('lang_id')) && ee()->publisher_helper_url->is_switch_url($this->get_current_url()))
        {
            // So set_language() knows what to do.
            ee()->publisher_lib->lang_id = $lang_id;
            return;
        }

        // We need to stop here if its a member or forum trigger word.
        if (ee()->publisher_helper_url->is_trigger_word())
        {
            return;
        }

        // EE does a redirect after the post before showing the View Entry page.
        if(REQ == 'CP' && ee()->publisher_setting->enabled())
        {
            $this->set_properties('CP');

            // Set the site_pages array b/c Structure references it.
            // This is the only/best way to get the proper template value loaded.
            $this->set_site_pages();

            $lang_id  = ee()->input->get('lang_id');
            $entry_id = ee()->input->get('entry_id');
            $publisher_save_status = ee()->input->post('publisher_save_status');
            $publisher_view_status = ee()->publisher_lib->status;

            // Make sure we have an open entry before trying to view it. If not, redirect to the draft
            // If a draft doesn't exist, well, they're in the right place b/c they have no content, drafts come first
            if ($entry_id &&
                ee()->input->get('M') == 'entry_form' &&
                $publisher_view_status == PUBLISHER_STATUS_OPEN &&
                ee()->publisher_setting->disable_drafts() == FALSE &&
                empty($_POST)
            ){
                $has_open = ee()->publisher_entry->has_open($entry_id);
                $url = $this->get_current_url();
                $request_url = $url;

                if ( !$has_open)
                {
                    if (preg_match('/publisher_status=(\S+)/', $url, $matches))
                    {
                        $url = preg_replace('/publisher_status=(\S+)/', 'publisher_status='. PUBLISHER_STATUS_DRAFT, $url);
                    }
                    else
                    {
                        $url .= '&publisher_status='. PUBLISHER_STATUS_DRAFT;
                    }
                }

                if ($lang_id && strpos($url, 'lang_id') === FALSE)
                {
                    $url .= '&lang_id='. $lang_id;
                }

                if ($url != $request_url)
                {
                    ee()->publisher_helper_url->redirect($url);
                }
            }

            $this->load_lang_keys();

            if ($publisher_save_status)
            {
                $this->set('publisher_save_status', $publisher_save_status);
            }

            // Set site_language_cp so when editing content it defaults
            // to that language until otherwise changed.
            if ($lang_id)
            {
                $this->set($this->cookie_name .'_cp', $lang_id);
            }
        }
        elseif (REQ == 'PAGE' OR REQ == 'ACTION')
        {
            // Set this before site_url is updated, thus breaking a lot of things.
            ee()->config->_global_vars['root_url'] = ee()->config->item('site_url');

            // Stop here if its not enabled. We need root_url set incase someone is using it,
            // but no prefixes or other variables should be set. No redirection etc.
            if ( !ee()->publisher_setting->enabled() || !ee()->publisher_helper->cookies_allowed())
            {
                return;
            }

            $this->set_properties();
            $this->set_tracker();

            // ACTIONs are redirects, so segments would be set to null if we set it on an ACTION.
            // Also make sure its not a member or forum trigger word, we don't want to set the
            // prefix on those requests.
            if (REQ == 'PAGE' && !ee()->publisher_helper_url->is_trigger_word())
            {
                // First make sure we've requested a page with a valid language segment, if prefixing is on.
                $this->set_url_prefix();
            }
        }

        // -------------------------------------------
        //  'publisher_session_end' hook
        //      - No parameters. Modify the ee()->publisher_lib properties if needed.
        //      - Can modify global_vars from the set_properties method below for example.
        //
            if (ee()->extensions->active_hook('publisher_session_end'))
            {
                ee()->extensions->call('publisher_session_end');
            }
        //
        // -------------------------------------------
    }

    /**
     * These used to be constants, but I need to update them
     * so they are now class properties. Used to save some base/important
     * modes that Publisher should use to operate in.
     */
    private function set_constants()
    {
        // If requesting open/published data in the default language
        if (REQ != 'CP' AND
            (ee()->publisher_lib->status == PUBLISHER_STATUS_OPEN &&
             ee()->publisher_lib->lang_id == ee()->publisher_lib->default_lang_id)
        ){
            ee()->publisher_lib->is_default_mode = TRUE;
        }
        else
        {
            ee()->publisher_lib->is_default_mode = FALSE;
        }

        // Doesn't matter what mode we're in, just need to know if viewing the default language
        if (ee()->publisher_lib->lang_id == ee()->publisher_lib->default_lang_id)
        {
            ee()->publisher_lib->is_default_language = TRUE;
        }
        else
        {
            ee()->publisher_lib->is_default_language = FALSE;
        }

        // Should we show diffs?
        if (ee()->publisher_lib->status == PUBLISHER_STATUS_DRAFT AND
            ee()->input->get('diff') != 'n'
        ){
            if (REQ == 'CP' AND ee()->publisher_setting->diff_enabled_cp())
            {
                ee()->publisher_lib->is_diff_enabled = TRUE;
            }
            elseif (REQ == 'PAGE' AND ee()->publisher_setting->diff_enabled())
            {
                ee()->publisher_lib->is_diff_enabled = TRUE;
            }
        }
    }

    /**
     * Set local class properties
     */
    private function set_properties($req = 'PAGE')
    {
        // This is all we need in the CP
        if ($req == 'CP')
        {
            ee()->publisher_lib->lang_code = $this->language_codes[ee()->publisher_lib->lang_id];
            $this->requested_language_code = ee()->publisher_lib->lang_code;
            $this->requested_language_id   = ee()->publisher_lib->lang_id;

            return;
        }

        $lang_id = FALSE;
        $segment_lang_id = FALSE;
        $segments = ee()->uri->segments;

        // Is the first segment a valid language code?
        if (isset($segments[1]))
        {
            $segment_lang_id = array_search($segments[1], $this->language_codes);
        }

        // Returning user? Has valid cookie. Default to said cookie.
        if ($this->session_language_id)
        {
            $lang_id = $this->session_language_id;
        }

        // Should we override it with the lang segment?
        if ($segment_lang_id !== FALSE)
        {
            $lang_id = $segment_lang_id;
        }
        // No segment, should we use default?
        elseif (ee()->publisher_setting->hide_prefix_on_default_language())
        {
            $lang_id = ee()->publisher_lib->default_lang_id;
        }

        // No segment or session? Try from the browser.
        if ($lang_id === FALSE && ee()->publisher_setting->get_language_from_browser())
        {
            // Sets $this->browser_lang_code
            $this->find_language();

            // See if the browser's language code is in our language list
            $lang_id = array_search($this->browser_lang_code, $this->language_codes);
        }

        // If we have a valid session, and we're not using prefixes, then
        // stick to the same language. The switcher is the only way to change
        // it in this case.
        if ($this->session_language_id && !ee()->publisher_setting->url_prefix())
        {
            $lang_id = $this->session_language_id;
        }

        // Oh noes!
        if ($lang_id === FALSE)
        {
            $lang_id = ee()->publisher_lib->default_lang_id;
        }

        // Change everything
        $this->set('site_language', $lang_id);
        $this->session_language_id     = $lang_id;
        ee()->publisher_lib->lang_id   = $lang_id;
        ee()->publisher_lib->lang_code = $this->language_codes[$lang_id];

        // Update our constants after lang_id has been determined.
        $this->set_constants();

        // Set this as the default.
        $this->requested_language_code = ee()->publisher_lib->lang_code;
        $this->requested_language_id   = ee()->publisher_lib->lang_id;

        // Make sure this is set so it doesn't throw errors.
        if ( !ee()->publisher_lib->switching && !isset(ee()->publisher_lib->prev_lang_id))
        {
            ee()->publisher_lib->prev_lang_id = ee()->publisher_lib->lang_id;
        }

        ee()->publisher_model->current_language = ee()->publisher_model->languages[ee()->publisher_lib->lang_id];

        // Set early parsed global vars to be used in template
        ee()->config->_global_vars['publisher:current_language_code'] = ee()->publisher_lib->lang_code;
        ee()->config->_global_vars['publisher:current_language_id']   = ee()->publisher_lib->lang_id;
        ee()->config->_global_vars['publisher:default_language_code'] = ee()->publisher_model->languages[ee()->publisher_lib->default_lang_id]['short_name'];
        ee()->config->_global_vars['publisher:default_language_id']   = ee()->publisher_lib->default_lang_id;
        ee()->config->_global_vars['publisher:current_language_prefix'] = ee()->publisher_helper_url->get_prefix();
        ee()->config->_global_vars['publisher:reserved_category_word'] = ee()->publisher_category->get_cat_url_indicator();
        ee()->config->_global_vars['publisher:entry_status'] = '';
        ee()->config->_global_vars['publisher:current_url'] = ee()->publisher_helper_url->get_translated_url();
        ee()->config->_global_vars['publisher:current_uri'] = ee()->publisher_helper_url->translated_uri;

        if (ee()->input->get('publisher_status') == PUBLISHER_STATUS_DRAFT)
        {
            ee()->config->_global_vars['publisher:entry_status'] = '|'.PUBLISHER_STATUS_OPEN.'|'.ucwords(PUBLISHER_STATUS_DRAFT);
        }

        // Create {page_uri:XX} vars
        ee()->publisher_site_pages->create_page_uri_vars();
    }

    /**
     * Tell EE that we're using a different language.
     * This will use installed language packs to display
     * error messages and such.
     */
    public function set_core_language()
    {
        // Just an insurance policy, no telling what a 3rd party add-on might do to this object.
        if (isset(ee()->session->userdata))
        {
            ee()->session->userdata['language'] = ee()->publisher_model->get_language(ee()->publisher_lib->lang_id, 'language_pack');
        }
    }

    /**
     * On each page load if URL Prefixing is enabled then
     * make sure the URL has a prefix and redirect to it.
     * @return  void
     */
    public function set_url_prefix()
    {
        // If prefixing is disable, nothing else to do. Set the site pages array and return.
        if ( !ee()->publisher_setting->url_prefix())
        {
            $this->set_site_pages();
            return;
        }

        $segments   = ee()->uri->segments;
        $url        = $this->get_current_url();
        $redirect   = TRUE;

        // If language code prefixing is set to yes, thus site.com/en/template_group/template
        if(ee()->publisher_helper_url->should_add_prefix() && isset($segments[1]))
        {
            // If its a valid template group or URI in the site_pages array
            // prepend the language code instead, otherwise the first segment will
            // get stripped and the page request won't be valid.
            if (ee()->publisher_helper_url->is_valid_url($segments[1]) && !ee()->publisher_setting->force_prefix())
            {
                $redirect = FALSE;
            }

            $site_index = ee()->publisher_helper_url->get_site_index();

            // If the site_url config var does not have the full domain path in it, then
            // redirects will get fubared. Compile the FULL domain and site index.
            if ( !strpos(ee()->publisher_helper_url->get_site_index(), $this->get_current_url(FALSE)))
            {
                $site_index = $this->get_current_url(FALSE) . ee()->publisher_helper_url->get_site_index();
            }

            // Remove the full base URL so we don't accidently replace part of it with a language code
            $url = str_replace($site_index, '', $url);

            $segments_before = implode('/', $segments);

            // If the first segment is not a valid language code
            if ($redirect && !in_array($segments[1], $this->language_codes))
            {
                $this->set_language(ee()->publisher_lib->lang_id);
            }

            // Requested language does not match the current, so a change was requested
            if ($redirect && ee()->publisher_lib->lang_code != $segments[1])
            {
                $lang_id = array_search($segments[1], $this->language_codes);

                $this->set_language($lang_id);
            }

            // Everything is good, just remove the lang code from the uri_string.
            // Static pages do not work without this, then set the site_pages array.
            $this->set_uri_string();
        }
        // If we shouldn't have a prefix, but we do and its a valid language code, remove it.
        elseif ( !ee()->publisher_helper_url->should_add_prefix() && isset($segments[1]))
        {
            if (in_array($segments[1], $this->language_codes))
            {
                $this->set_language(ee()->publisher_lib->default_lang_id);
            }
        }

        if(ee()->publisher_helper_url->should_add_prefix() && empty($segments))
        {
            // If the site_url config var does not have the full domain path in it, then
            // redirects will get fubared. Compile the FULL domain and site index.
            $site_index = ee()->publisher_helper_url->get_site_index();

            if (strstr($url, '?') !== FALSE)
            {
                $url = str_replace('?', $this->language_codes[ee()->publisher_lib->lang_id] .'?', $site_index);
            }
            else
            {
                $url = $site_index . $this->language_codes[ee()->publisher_lib->lang_id];
            }

            $this->set_language(ee()->publisher_lib->lang_id, $url, TRUE);
        }

        $this->set_site_pages();
    }

    /**
     * Search the template groups and site_pages array to see if the url is valid
     * @param  string  $segment segment_1
     * @return boolean
     */
    public function set_uri_string()
    {
        $uri_string = explode('/', ee()->uri->uri_string);

        if ($uri_string[0] != ee()->publisher_lib->lang_code)
        {
            return;
        }

        array_shift($uri_string);

        ee()->uri->uri_string = implode('/', $uri_string);

        // Re-index our segments so {segment_N} vars continue to work
        array_shift(ee()->uri->segments);

        $new_segments = array();
        $i = 1;

        foreach(ee()->uri->segments as $segment)
        {
            $new_segments[$i] = $segment;
            $i++;
        }

        // Update so it does not include the language code
        ee()->uri->segments = $new_segments;
    }

    /**
     * Update the site_pages array accordingly
     * @return  void
     */
    public function set_site_pages()
    {
        $site_url     = ee()->publisher_helper_url->get_site_index();
        $site_pages   = ee()->publisher_site_pages->get();
        $lang_segment = ee()->publisher_lib->lang_code;

        if (ee()->publisher_helper_url->should_add_prefix())
        {
            $site_url .= $lang_segment .'/';
        }

        // Now set the updated site_url and root_url vars
        if (REQ != 'CP')
        {
            ee()->config->set_item('site_url', reduce_double_slashes($site_url));
        }

        // Form declaration tags are not generated properly without this.
        // functions->fetch_site_index() would create index.php/fr/index.php
        ee()->config->set_item('site_index', '');

        // Make sure the config item is updated so everything is using the same site_pages array.
        // Also if its an empty array, set the value to blank just incase other devs don't
        // validate the array format before using it.
        ee()->config->set_item('site_pages', (empty($site_pages) ? FALSE : $site_pages));
    }

    /**
     * Handle the language switching and redirection
     * @param int $lang_id optional
     */
    public function set_language($lang_id = FALSE, $url = FALSE, $skip_url_prefix_check = FALSE, $type = 302)
    {
        // No language switching allowed if its Publisher Lite
        if (PUBLISHER_LITE)
        {
            return;
        }

        ee()->publisher_lib->switching = TRUE;

        // If no language is passed, use the currrent one, which will be set in sessions_end()
        $lang_id = $lang_id ? (int) $lang_id : (int) ee()->publisher_lib->lang_id;

        // Override the redirect type? 301 or 302?
        $type = ee()->publisher_setting->redirect_type() ?: $type;

        // Hold on! Not so fast there champ.
        if (ee()->publisher_setting->force_default_language())
        {
            $lang_id = ee()->publisher_lib->default_lang_id;
        }

        // Make sure our cookie & session is set
        $this->set('site_language', $lang_id);

        // -------------------------------------------
        //  'publisher_switch_language' hook
        //   - Allow 3rd party devs do some extra processing when the language is switched
        //
            if (ee()->extensions->active_hook('publisher_set_language'))
            {
                ee()->extensions->call('publisher_set_language', $this, $lang_id);

                if (ee()->extensions->end_script === TRUE)
                {
                    return;
                }
            }
        //
        // -------------------------------------------
        //

        $this->requested_language_code = $this->language_codes[$lang_id];
        $this->requested_language_id = $lang_id;

        if ($url === FALSE)
        {
            // If we have $_GET['url'] it should be set to the previous
            // url the user was on prior to clicking change language.
            if ($url = ee()->input->get('url'))
            {
                $url = base64_decode($url);

                // We're doing an internal redirect, so a 302 is proper.
                // Disregard the user's settings at this point.
                $type = 302;
            }
            // Otherwise, try to get it from the tracker history.
            // This can cause issues if Ajax requests are made, or
            // a CSS/JS file is embedded via EE's tags.
            else
            {
                // Get the last page from the tracker
                $tracker_url = $this->get_tracker('url');

                $url = $url ?: $tracker_url;
            }
        }

        $url = ee()->publisher_helper_url->add_trailing_slash($url);

        // Stop here. If language is forced, we don't alter the URL anymore.
        // Also if viewing a draft definitely don't redirect or we'll get into an infinite loop.
        if (ee()->publisher_setting->force_default_language() && ee()->publisher_lib->status != PUBLISHER_STATUS_DRAFT)
        {
            ee()->publisher_helper_url->redirect($url, $type);
        }

        if ($this->is_ignored_file_type())
        {
            return;
        }

        // Save this for later...
        ee()->publisher_lib->prev_lang_id = $this->session_language_id;

        // Only if URL Translations are enabled. Prefixing is handled by get_translated_url()
        if (ee()->publisher_setting->url_translations())
        {
            $current_url = $this->get_current_url();

            // If they are the same then the user already requested a valid translated
            // url, most likely from a link on the page. So don't bother getting
            // the alternate_url, b/c we already have it. We still need the redirect
            // though in order to change the session and cookie values.
            // If the current URL is the same as the base_url/site_url it means we're
            // on the home page and the new URL has already been created.
            if ($url != $current_url)
            {
                $url = ee()->publisher_helper_url->get_translated_url($url, ee()->publisher_lib->prev_lang_id, $lang_id);
            }
            else
            {
                $url = ee()->publisher_helper_url->set_prefix($url);
            }
        }
        // If URL prefixing is enabled, and we have a URL, fix it to include the new language.
        elseif (ee()->publisher_setting->url_prefix() && !$skip_url_prefix_check)
        {
            $url = ee()->publisher_helper_url->set_prefix($url);
        }

        ee()->publisher_helper_url->redirect($url, $type);
    }

    public function set_status()
    {
        if ($requested_status = ee()->input->get('publisher_status'))
        {
            $url = $this->get_referrer_url();

            // Make sure we don't get more than 1 status param
            preg_match('/status=(\S+)/', $url, $matches);

            if (isset($matches[1]) AND in_array($matches[1], array(PUBLISHER_STATUS_DRAFT, PUBLISHER_STATUS_OPEN)))
            {
                $url = preg_replace('/status=(\S+)/', 'status='. $requested_status, $url);
            }
            else
            {
                $separator = strstr($url, '?') ? '&' : '?';

                $url = $url . $separator .'status='. $requested_status;
            }

            // If it returns something, use it, otherwise use non-translated version.
            if ($url = ee()->publisher_site_pages->find_related($url))
            {
                ee()->publisher_helper_url->redirect($url);
            }
            else
            {
                ee()->publisher_helper_url->redirect($url);
            }
        }
    }

    public function set($name, $value)
    {
        $_SESSION[$this->prefix.$name] = $value;
        $this->set_cookie($value, $this->prefix.$name);
    }

    public function get($name, $default = FALSE)
    {
        return (isset($_SESSION[$this->prefix.$name])) ? $_SESSION[$this->prefix.$name] : $default;
    }

    /**
     * Thanks Dan Vandermeer for the added security suggestions.
     *
     * @param  boolean $name
     * @param  boolean $clean
     * @return mixed
     */
    public function get_cookie($name = FALSE, $clean = FALSE)
    {
        $name = $name ? $this->prefix.$name : $this->cookie_name;

        if(isset($_COOKIE[$name]))
        {
            // if it's a serialized array, unserialize it
            $cookie = (substr($_COOKIE[$name], 0, 2) == 'a:') ? unserialize($_COOKIE[$name]) : $_COOKIE[$name];
            $cleaned = strip_tags($cookie);

            if($cookie != $cleaned)
            {
                $chars = str_split($cookie);
                $a = 0;
                $start =0;
                $end = 0;

                foreach($chars as $char)
                {
                    if($char == "<")
                    {
                        if($start == 0)
                        {
                            $start = $a;
                        }
                    }

                    if($char == ">")
                    {
                        $end = $a;
                    }

                    $a++;
                }

                $bad = substr($cookie, $start, $end);
                $cookie = str_replace($bad, "", $cookie);
            }

            return $clean ? ee()->security->xss_clean($cookie) : $cookie;
        }
        else
        {
            return FALSE;
        }
    }

    public function set_cookie($value = '', $name = FALSE, $expire = 604800)
    {
        if ( !ee()->publisher_helper->cookies_allowed()) return;

        $name = $name ?: $this->cookie_name;

        $expire = ee()->publisher_setting->cookie_lifetime();

        if ( !is_numeric($expire))
        {
            $expire = time() - 86500;
        }
        else
        {
            if ($expire > 0)
            {
                $expire = time() + $expire;
            }
            else
            {
                $expire = 0;
            }
        }

        $path = ( !ee()->config->item('cookie_path')) ? '/' : ee()->config->item('cookie_path');

        if (REQ == 'CP' && ee()->config->item('multiple_sites_enabled') == 'y')
        {
            $domain = ee()->config->cp_cookie_domain;
        }
        else
        {
            $domain = ( !ee()->config->item('cookie_domain')) ? '' : ee()->config->item('cookie_domain');
        }

        $value = is_array($value) ? serialize($value) : stripslashes($value);

        setcookie($name, $value, $expire, $path, $domain, 0);
    }

    /**
     * Load our module lang file and set the key/value pairs as usuable JS vars
     *
     * @todo - causes a 404 request for site.com/js/ - wtf?
     * @return void
     */
    public function load_lang_keys()
    {
        ee()->load->library('javascript', array('autoload' => FALSE));

        if (REQ == 'CP')
        {
            // Default to English if config isn't set. This wasn't required until EE 2.8?
            $language = ee()->config->item('language') ? ee()->config->item('language') : 'english';

            require_once ee()->publisher_lib->path .'language/'. $language .'/lang.publisher.php';

            foreach ($lang as $key => $value)
            {
                ee()->javascript->set_global('publisher.'.$key, $value);
            }
        }
        else if (REQ == 'PAGE')
        {
            // foreach ($lang as $key => $value)
            // {
            //     ee()->javascript->set_global('publisher.'.$key, $value);
            // }
        }
    }

    /**
     * Get the full previous URL the user was at. Don't rely
     * on HTTP_REFERER, try our tracker array first.
     *
     * @return string
     */
    public function get_referrer_url()
    {
        if (isset($this->tracker) AND isset($this->tracker[0]))
        {
            foreach ($this->tracker as $step => $tracks)
            {
                if ($tracks->is_ajax == 'n')
                {
                    return $tracks->uri;
                }
            }
        }
        else
        {
            $site_url = ee()->config->item('site_url');
            return ( !isset($_SERVER['HTTP_REFERER'])) ? $site_url : $_SERVER['HTTP_REFERER'];
        }
    }

    /**
     * Tracker
     *
     * This functions lets us store the visitor's last five pages viewed
     * in a cookie.  We use this to facilitate redirection after logging-in,
     * or other form submissions
     */
    public function set_tracker()
    {
        $this->tracker = ee()->input->cookie('publisher_tracker');

        if ($this->tracker != FALSE)
        {
            $this->tracker = json_decode($this->tracker);
        }

        if ( !is_array($this->tracker))
        {
            $this->tracker = array();
        }

        $uri = $this->get_current_url();

        $is_ajax = AJAX_REQUEST ? 'y' : 'n';

        if ( !isset($_GET['ACT']) AND !$this->is_ignored_file_type($uri))
        {
            if ( !isset($this->tracker['0']))
            {
                $this->tracker[] = (object) array('uri' => $uri, 'is_ajax' => $is_ajax);
            }
            else
            {
                if (count($this->tracker) == 20)
                {
                    array_pop($this->tracker);
                }

                if ($this->tracker['0'] != $uri)
                {
                    array_unshift($this->tracker, (object) array('uri' => $uri, 'is_ajax' => $is_ajax));
                }
            }
        }

        if (REQ == 'PAGE')
        {
            if (version_compare(APP_VER, '2.8', '>='))
            {
                ee()->input->set_cookie('publisher_tracker', json_encode($this->tracker), '0');
            }
            else
            {
                ee()->functions->set_cookie('publisher_tracker', json_encode($this->tracker), '0');
            }
        }
    }

    /**
     * Pluck a URL from the tracker history
     *
     * @param  integer $history Which index to return? 0 is immediate last.
     * @param  string  $return  url, uri, or segments array
     * @return mixed
     */
    public function get_tracker($return = 'full', $history = 0)
    {
        $tracker = '';
        $referrer_url = $this->get_referrer_url();

        if (isset($this->tracker) AND isset($this->tracker[$history]))
        {
            $tracker = $this->tracker[$history];
        }

        if ($return == 'full')
        {
            return $tracker;
        }

        // Use the URI in the tracker if it exists, fall back to referrer if it does not.
        $uri = isset($tracker->uri) ? $tracker->uri : $referrer_url;

        if ($return == 'segments' || $return == 'uri')
        {
            $uri = ee()->publisher_helper_url->remove_site_index($uri);

            if ($return == 'segments')
            {
                $segments = explode('/', $uri);

                $tracker = array();

                foreach ($segments as $seg)
                {
                    // Make sure no blank segments get sent back.
                    if ($seg != '')
                    {
                        $tracker[] = $seg;
                    }
                }
            }

            if ($return == 'uri')
            {
                $tracker = $uri;
            }
        }

        if ($return == 'url')
        {
            $tracker = $uri;
        }

        return $tracker;
    }

    /**
     * Get the full current URL, query string and all
     * @param  boolean $include_uri Do we want everything after and including the ?
     * @return string
     */
    public function get_current_url($include_uri = TRUE)
    {
        $site_url = ee()->config->item('site_url');
        $lookup_type = ee()->publisher_setting->host_lookup_type();

        $url = (@$_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';

        if ($_SERVER['SERVER_PORT'] != "80")
        {
            $url .= $_SERVER[$lookup_type].':'.$_SERVER['SERVER_PORT'].'/';
        }
        else
        {
            $url .= $_SERVER[$lookup_type].'/';
        }

        if ($include_uri)
        {
            $url .= $_SERVER['REQUEST_URI'];
        }

        return reduce_double_slashes($url);
    }

    public function set_canonical_url($uri_string, $segments = FALSE)
    {
        $canonical_url = '';

        if (REQ == 'PAGE' AND ee()->publisher_setting->url_translations())
        {
            ee()->load->model('publisher_template');
            ee()->publisher_lib->prev_lang_id = $this->session_language_id;

            // If we have segments, we have a template_group/template based URL
            if ( !empty($segments) AND isset($segments[0]))
            {
                $canonical_uri_string = implode('/', $segments);
                $canonical_url = ee()->publisher_helper_url->get_site_index() . $canonical_uri_string;
            }
            // We have a page
            else if ($uri_string)
            {
                $uri_string = '/'. $uri_string;

                $default_site_pages = ee()->publisher_site_pages->get(ee()->publisher_lib->default_lang_id, TRUE);
                $translated_site_pages = ee()->publisher_site_pages->get(ee()->publisher_lib->lang_id, TRUE);

                if ( !empty($translated_site_pages))
                {
                    if ($key = array_search($uri_string, $translated_site_pages))
                    {
                        if (isset($default_site_pages[$key]))
                        {
                            $canonical_url = ee()->publisher_helper_url->get_site_index() . $default_site_pages[$key];
                        }
                    }
                }
            }
        }

        ee()->config->_global_vars['canonical_url'] = rtrim(reduce_double_slashes($canonical_url), '/');
        ee()->config->_global_vars['publisher:canonical_url'] = ee()->config->_global_vars['canonical_url'];
    }

    /**
     * Find the language code of the user's browser
     * @return void
     */
    public function find_language()
    {
        $accepted = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $this->parseLanguageList($_SERVER['HTTP_ACCEPT_LANGUAGE']) : array();
        $available = $this->parseLanguageList(implode(',', ee()->publisher_model->get_language_codes()));
        $matches = $this->findMatches($accepted, $available);

        // Get the default language code first
        $this->browser_lang_code = ee()->publisher_model->get_default_language('short_name');

        // See if we found something from the browser headers
        foreach($matches as $priority => $lang_code)
        {
            $this->browser_lang_code = $lang_code[0];

            // Find the first only and stop searching
            break;
        }
    }

    /**
     * See if the first segment contains an ignored file type
     * @return boolean
     */
    public function is_ignored_file_type($url = FALSE)
    {
        $url = $url ?: $this->get_current_url();

        // So any direct requests to theme files are ignored.
        $this->ignore_file_types[] = ee()->publisher_helper->get_theme_url();

        if ($url)
        {
            foreach ($this->ignore_file_types as $ext)
            {
                if (strpos($url, $ext) !== FALSE)
                {
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    /**
     * Better language detection courtesy of http://stackoverflow.com/questions/3770513/detect-browser-language-in-php
     *
     * parse list of comma separated language tags and sort it by the quality value
    */
    private function parseLanguageList($languageList) {
        if (is_null($languageList)) {
            if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                return array();
            }
            $languageList = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        }
        $languages = array();
        $languageRanges = explode(',', trim($languageList));
        foreach ($languageRanges as $languageRange) {
            if (preg_match('/(\*|[a-zA-Z0-9]{1,8}(?:-[a-zA-Z0-9]{1,8})*)(?:\s*;\s*q\s*=\s*(0(?:\.\d{0,3})|1(?:\.0{0,3})))?/', trim($languageRange), $match)) {
                if (!isset($match[2])) {
                    $match[2] = '1.0';
                } else {
                    $match[2] = (string) floatval($match[2]);
                }
                if (!isset($languages[$match[2]])) {
                    $languages[$match[2]] = array();
                }
                $languages[$match[2]][] = strtolower($match[1]);
            }
        }
        krsort($languages);
        return $languages;
    }

    // compare two parsed arrays of language tags and find the matches
    private function findMatches($accepted, $available) {
        $matches = array();
        $any = false;
        foreach ($accepted as $acceptedQuality => $acceptedValues) {
            $acceptedQuality = floatval($acceptedQuality);
            if ($acceptedQuality === 0.0) continue;
            foreach ($available as $availableQuality => $availableValues) {
                $availableQuality = floatval($availableQuality);
                if ($availableQuality === 0.0) continue;
                foreach ($acceptedValues as $acceptedValue) {
                    if ($acceptedValue === '*') {
                        $any = true;
                    }
                    foreach ($availableValues as $availableValue) {
                        $matchingGrade = $this->matchLanguage($acceptedValue, $availableValue);
                        if ($matchingGrade > 0) {
                            $q = (string) ($acceptedQuality * $availableQuality * $matchingGrade);
                            if (!isset($matches[$q])) {
                                $matches[$q] = array();
                            }
                            if (!in_array($availableValue, $matches[$q])) {
                                $matches[$q][] = $availableValue;
                            }
                        }
                    }
                }
            }
        }
        if (count($matches) === 0 && $any) {
            $matches = $available;
        }
        krsort($matches);
        return $matches;
    }

    // compare two language tags and distinguish the degree of matching
    private function matchLanguage($a, $b) {
        $a = explode('-', $a);
        $b = explode('-', $b);
        for ($i=0, $n=min(count($a), count($b)); $i<$n; $i++) {
            if ($a[$i] !== $b[$i]) break;
        }
        return $i === 0 ? 0 : (float) $i / count($a);
    }

}