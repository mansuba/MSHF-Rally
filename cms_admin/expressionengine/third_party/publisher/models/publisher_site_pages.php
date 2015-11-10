<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Accessory Class
 *
 * @package     ExpressionEngine
 * @subpackage  Accessory
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

class Publisher_site_pages extends Publisher_model
{
    /**
     * Structure SQL class
     * @var object
     */
    private $sql = NULL;

    /**
     * Structure Module class
     * @var object
     */
    private $structure = NULL;

    /**
     * site_pages array from exp_sites table
     * @var array
     */
    public $core_site_pages = array();


    const STRUCTURE_MODULE = 'structure';
    const PAGES_MODULE = 'pages';

    /**
     * __construct
     */
    public function __construct()
    {
        parent::__construct();

        // Create cache
        if (! isset(ee()->session->cache['publisher']))
        {
            ee()->session->cache['publisher'] = array();
        }
        $this->cache =& ee()->session->cache['publisher'];

        // Make sure Structure is installed first
        if ($this->is_installed(self::STRUCTURE_MODULE))
        {
            require_once PATH_THIRD.'structure/sql.structure.php';
            $this->sql = new Sql_structure();
        }
    }

    /**
     * Make sure the requested module(s) is installed first...
     *
     * @param  string  $module
     * @return boolean
     */
    public function is_installed($module = array(self::STRUCTURE_MODULE, self::PAGES_MODULE))
    {
        // See if either module is installed, doesn't matter which
        if (is_array($module))
        {
            foreach ($module as $mod)
            {
                if (array_key_exists($mod, ee()->addons->get_installed('modules')))
                {
                    return TRUE;
                }
            }
        }

        // Looking for a specific module instead
        if ( !is_array($module) AND array_key_exists($module, ee()->addons->get_installed('modules')))
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Called when pages are reordered in Structure via ajax or an entry is saved.
     * Listing entries only get added to the array if they exist in each language,
     * however, if an entry is created in a non-default language it is added to every
     * pages array, even if it isn't translated or the entry is actually available
     * in the language.
     *
     * @param  array $data nested set data array that Structure creates
     * @param  array $site_pages the default language/core site_pages array as EE sees it
     * @param  array $languages if defined, only build pages for that language
     * @return void
     */
    public function rebuild($data, $site_pages, $languages = NULL)
    {
        // Use a specific set or all of them
        $languages = $languages ? $languages : ee()->publisher_model->languages;

        // Anything to build?
        if (empty($site_pages['uris']))
        {
            return;
        }

        // Module specific URI creation
        if ($this->is_installed(self::STRUCTURE_MODULE))
        {
            list($page_uris, $page_templates) = $this->create_structure_uris($data, $site_pages, $languages);
        }
        else if ($this->is_installed(self::PAGES_MODULE))
        {
            list($page_uris, $page_templates) = $this->create_pages_uris($data, $site_pages, $languages);
        }

        // Regardless of the module, this should be consistent
        foreach ($page_uris as $status => $data)
        {
            foreach ($data as $lang_id => $uris)
            {
                // Get the existing pages array
                $site_pages_translated = $this->get();

                $site_url = ee()->publisher_helper_url->remove_double_codes($site_pages['url'], ee()->publisher_model->language_codes[$lang_id]);

                // Update its values with that of the organized page_uris
                $site_pages_translated[ee()->publisher_lib->site_id]['url'] = $site_url;
                $site_pages_translated[ee()->publisher_lib->site_id]['uris'] = $page_uris[$status][$lang_id];
                $site_pages_translated[ee()->publisher_lib->site_id]['templates'] = $page_templates[$status][$lang_id];

                $insert_data = array(
                    'publisher_lang_id' => $lang_id,
                    'publisher_status'  => $status,
                    'site_id'           => ee()->publisher_lib->site_id,
                    'site_pages'        => $this->json_encode_pages($site_pages_translated)
                );

                $where = array(
                    'publisher_lang_id' => $lang_id,
                    'publisher_status'  => $status,
                    'site_id'           => ee()->publisher_lib->site_id
                );

                $this->insert_or_update('publisher_site_pages', $insert_data, $where);

                if ($status == PUBLISHER_STATUS_OPEN && $lang_id == ee()->publisher_lib->default_lang_id)
                {
                    $insert_data = array('site_pages' => base64_encode(serialize($site_pages_translated)));
                    $where = array('site_id' => ee()->publisher_lib->site_id);

                    // Update our core table
                    $this->insert_or_update('sites', $insert_data, $where, 'site_id');
                }
            }
        }
    }

    /**
     * Encode the JSON array so it works with unicode characters
     *
     * @param  Array $array $site_pages before save
     * @return String
     */
    public function json_encode_pages($array)
    {
        if (version_compare(phpversion(), '5.4', '>='))
        {
            return json_encode($array, JSON_UNESCAPED_UNICODE);
        }
        else
        {
            return preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", json_encode($array));
        }
    }

    /**
     * Get all the URL data from our custom table for each entry
     * organized by status and language so we can use it to rebuild the pages array
     *
     * @param  mixed $data
     * @return array
     */
    private function get_url_titles($entry_id)
    {
        if (is_array($entry_id) && !empty($entry_id))
        {
            $qry = ee()->db->select('page_url, url_title, publisher_lang_id, publisher_status, entry_id')
                            ->where_in('entry_id', $entry_id)
                            ->get('publisher_titles');
        }
        else if($entry_id)
        {
            $qry = ee()->db->select('page_url, url_title, publisher_lang_id, publisher_status, entry_id')
                            ->where('entry_id', $entry_id)
                            ->get('publisher_titles');
        }
        else
        {
            return array();
        }


        $url_titles = array();

        foreach ($qry->result() as $row)
        {
            $url_titles[$row->publisher_status][$row->publisher_lang_id][$row->entry_id] = ($row->page_url != '')
                ? $row->page_url
                : $row->url_title;
        }

        return $url_titles;
    }

    /**
     * Get all the template_id from our custom table for each entry
     * organized by status and language so we can use it to rebuild the pages array
     *
     * @param  mixed $data
     * @return array
     */
    private function get_template_ids($entry_id)
    {
        if (is_array($entry_id) && !empty($entry_id))
        {
            $qry = ee()->db->select('publisher_lang_id, publisher_status, entry_id, template_id')
                            ->where_in('entry_id', $entry_id)
                            ->get('publisher_titles');
        }
        else if($entry_id)
        {
            $qry = ee()->db->select('publisher_lang_id, publisher_status, entry_id, template_id')
                            ->where('entry_id', $entry_id)
                            ->get('publisher_titles');
        }
        else
        {
            return array();
        }


        $template_ids = array();

        foreach ($qry->result() as $row)
        {
            $template_ids[$row->publisher_status][$row->publisher_lang_id][$row->entry_id] = $row->template_id;
        }

        return $template_ids;
    }

    /**
     * Get all the Title data from our custom table for each entry
     * organized by status and language
     *
     * @param  array $data
     * @return array
     */
    private function get_titles($entry_id)
    {
        if (is_array($entry_id))
        {
            $qry = ee()->db->select('title, publisher_lang_id, publisher_status, entry_id')
                            ->where_in('entry_id', $entry_id)
                            ->get('publisher_titles');
        }
        else
        {
            $qry = ee()->db->select('title, publisher_lang_id, publisher_status, entry_id')
                            ->where('entry_id', $entry_id)
                            ->get('publisher_titles');
        }


        $titles = array();

        foreach ($qry->result() as $row)
        {
            $titles[$row->publisher_status][$row->publisher_lang_id][$row->entry_id] = ($row->title != '')
                ? $row->title
                : FALSE;
        }

        return $titles;
    }

    /**
     * Use the nested data set that Structure provides to create all
     * the URIs and Templates for the site_pages array
     *
     * @param  array $data          nested set data
     * @param  array $site_pages    core/default site_pages
     * @param  array $languages     languages to use in creating the array
     * @return array                array($page_uris, $page_templates)
     */
    private function create_structure_uris($data, $site_pages, $languages)
    {
        $page_uris = array();
        $page_templates = array();

        $publisher_titles = $this->get_url_titles(array_keys($data));
        $publisher_template_ids = $this->get_template_ids(array_keys($data));

        foreach (array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT) as $status)
        {
            foreach ($languages as $lang_id => $language)
            {
                // Regardless of language, they all share the same template by default.
                // Will possibly get changed on a per-entry/status/language basis below.
                if (isset($site_pages['templates']))
                {
                    $page_templates[$status][$lang_id] = $site_pages['templates'];
                }

                foreach ($data as $key => $row)
                {
                    // When saving a single entry and using the fudged nested set
                    // array these will be empty for listing entries, so skip or
                    // the URLs will be fubared.
                    if (empty($row['lft']) AND empty($row['rgt']))
                    {
                        continue;
                    }

                    $depth = count($row['crumb']);

                    // build URI path for pages
                    $uri_titles = array();

                    foreach ($data[$key]['crumb'] as $entry_id)
                    {
                        // Get our translated url_titles if possible
                        if (isset($publisher_titles[$status][$lang_id]) AND isset($publisher_titles[$status][$lang_id][$entry_id]))
                        {
                            $uri_titles[] = $publisher_titles[$status][$lang_id][$entry_id];
                        }
                        // If the requested language isn't available, use the default
                        else if (isset($publisher_titles[$status][ee()->publisher_lib->default_lang_id][$entry_id]))
                        {
                            $uri_titles[] = $publisher_titles[$status][ee()->publisher_lib->default_lang_id][$entry_id];
                        }
                        // Just in-case, do a fallback lookup and get the url_title
                        // from the core table so the URLs are generated correctly.
                        else
                        {
                            $qry = ee()->db->select('url_title')
                                    ->where('entry_id', $entry_id)
                                    ->get('channel_titles');

                            if ($qry->num_rows())
                            {
                                $uri_titles[] = $qry->row('url_title');
                            }
                        }

                        // Do a ridiculous number of checks to prevent PHP notices, and if we have
                        // a template_id for the entry by status and language, update array.
                        if (
                            isset($publisher_template_ids[$status][$lang_id]) &&
                            isset($publisher_template_ids[$status][$lang_id][$entry_id]) &&
                            $publisher_template_ids[$status][$lang_id][$entry_id] != ''
                        ){
                            $page_templates[$status][$lang_id][$entry_id] = $publisher_template_ids[$status][$lang_id][$entry_id];
                        }
                        // Make sure we have something for the entry, otherwise the Structure
                        // module page might blow up. This is mostly for pages that are non-persistent,
                        // which means they show in the Structure mod page, but are not in the
                        // site_pages array when the FE is in that language.
                        else if ( !isset($page_templates[$status][$lang_id][$entry_id]))
                        {
                            $page_templates[$status][$lang_id][$entry_id] = NULL;
                        }
                    }

                    if (empty($uri_titles))
                    {
                        $page_uris[$status][$lang_id][$key] = $site_pages['uris'][$key];
                    }
                    else
                    {
                        // Build pages URI
                        $page_uris[$status][$lang_id][$key] = trim(implode('/', $uri_titles), '/');

                        // Account for "/" home page
                        $page_uris[$status][$lang_id][$key] = $page_uris[$status][$lang_id][$key] == '' ? '/' : '/'.$page_uris[$status][$lang_id][$key];
                    }
                }

                // Sorting pages blows away the listing data, so all URLs for listing pages
                // are no longer in the site_pages array... lets fix that.

                // Took this directly from Structure... b/c I originally wrote it ;)
                foreach($page_uris[$status][$lang_id] as $entry_id => $uri)
                {
                    $listing_channel = $this->sql->get_listing_channel($entry_id);

                    if ($listing_channel !== FALSE)
                    {
                        // Retrieve all entries for channel
                        $listing_entries = $this->sql->get_channel_listing_entries($listing_channel);

                        if ( !empty($listing_entries))
                        {
                            $listing_qry = ee()->db->select('page_url, url_title, publisher_lang_id, publisher_status, entry_id')
                                            ->where_in('entry_id', array_unique(array_keys($listing_entries)))
                                            ->where('channel_id', $listing_channel)
                                            ->where('publisher_lang_id', $lang_id)
                                            ->where('publisher_status', $status)
                                            ->get('publisher_titles');


                            $structure_channels = $this->_get_structure_channels();
                            $default_template = $structure_channels[$listing_channel]['template_id'];

                            foreach ($listing_qry->result_array() as $c_entry)
                            {
                                $page_uris[$status][$lang_id][$c_entry['entry_id']] = $this->_create_full_uri($page_uris[$status][$lang_id][$entry_id], $c_entry['page_url']
                                    ? $c_entry['page_url']
                                    : $c_entry['url_title']);

                                // TODO use publisher_template_ids?
                                $page_templates[$status][$lang_id][$c_entry['entry_id']] = $listing_entries[$c_entry['entry_id']]['template_id']
                                    ? $listing_entries[$c_entry['entry_id']]['template_id']
                                    : $default_template;
                            }
                        }
                    }

                    // Recurse again and make sure we have a key for every language,
                    // even if the entry does not actually exist. We'll check for that
                    // later in core_template_route() hook.
                    if ( !ee()->publisher_setting->persistent_entries())
                    {
                        foreach ($languages as $l_lang_id => $l_language)
                        {
                            if ( !isset($page_uris[$status][$l_lang_id][$entry_id]) AND
                                  isset($page_uris[$status][$lang_id][$entry_id]))
                            {
                                $page_uris[$status][$l_lang_id][$entry_id] = $page_uris[$status][$lang_id][$entry_id];
                            }
                        }
                    }
                }
            }

        }

        return array($page_uris, $page_templates);
    }

    /**
     * Pages is simpler by nature. It's on the short bus. It does not have drag
     * and drop re-ordering so we don't have to worry about rebuilding all the
     * URIs. It saves only one at a time, so we loop through and update only
     * the key that we need to update.
     *
     * @param  array $data          $_POST data from saving the entry
     * @param  array $site_pages    core/default site_pages
     * @param  array $languages     languages to use in creating the array
     * @return array                array($page_uris, $page_templates)
     */
    public function create_pages_uris($data, $site_pages, $languages)
    {
        $page_uris = array();
        $page_templates = array();
        $publisher_site_pages = $this->get_all();

        // So we can call rebuild outside of the publish process
        $posted_entry_id = isset($data['entry_id']) ? $data['entry_id'] : FALSE;

        $publisher_titles = $this->get_url_titles(array_keys($site_pages['uris']));

        foreach (array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT) as $status)
        {
            foreach ($languages as $lang_id => $language)
            {
                foreach ($site_pages['uris'] as $entry_id => $uri)
                {
                    $uri_set_to = $uri;

                    // We have posted data from the entry, use it.
                    if ($posted_entry_id == $entry_id &&
                        ee()->publisher_lib->publisher_save_status == $status &&
                        ee()->publisher_lib->lang_id == $lang_id)
                    {
                        $uri_set_to = $data['pages_uri'];
                    }
                    // For the rest of the keys, just set to existing value if present.
                    elseif (isset($publisher_titles[$status][$lang_id][$entry_id]))
                    {
                        $uri_set_to = $publisher_titles[$status][$lang_id][$entry_id];
                    }
                    // Or use default language/status if present.
                    elseif (isset($page_uris[PUBLISHER_STATUS_OPEN][ee()->publisher_lib->default_lang_id][$entry_id]) &&
                            isset($publisher_titles[$status][$lang_id][$entry_id]))
                    {
                        $uri_set_to = $publisher_titles[PUBLISHER_STATUS_OPEN][ee()->publisher_lib->default_lang_id][$entry_id];
                    }

                    $page_uris[$status][$lang_id][$entry_id] = ee()->publisher_helper->add_prefix($uri_set_to, '/');

                    // Set default just incase
                    $page_templates[$status][$lang_id][$entry_id] = isset($site_pages['templates'][$entry_id]) ?
                        $site_pages['templates'][$entry_id] :
                        $data['pages_template_id'];

                    if( $posted_entry_id == $entry_id &&
                        ee()->publisher_lib->publisher_save_status == $status &&
                        ee()->publisher_lib->lang_id == $lang_id)
                    {
                        $page_templates[$status][$lang_id][$entry_id] = $data['pages_template_id'];
                    }
                    // elseif (isset($publisher_site_pages[$status][$lang_id][ee()->publisher_lib->site_id]['templates'][$entry_id]))
                    // {
                    //     $page_templates[$status][$lang_id][$entry_id] = $publisher_site_pages[$status][$lang_id][ee()->publisher_lib->site_id]['templates'][$entry_id];
                    // }
                    // elseif (isset($publisher_site_pages[$status][ee()->publisher_lib->default_lang_id][ee()->publisher_lib->site_id]['templates'][$entry_id]))
                    // {
                    //     $page_templates[$status][$lang_id][$entry_id] = $publisher_site_pages[$status][ee()->publisher_lib->default_lang_id][ee()->publisher_lib->site_id]['templates'][$entry_id];
                    // }
                    // elseif (isset($publisher_site_pages[PUBLISHER_STATUS_OPEN][ee()->publisher_lib->default_lang_id][ee()->publisher_lib->site_id]['templates'][$entry_id]))
                    // {
                    //     $page_templates[$status][$lang_id][$entry_id] = $publisher_site_pages[PUBLISHER_STATUS_OPEN][ee()->publisher_lib->default_lang_id][ee()->publisher_lib->site_id]['templates'][$entry_id];
                    // }
                }

                // Its a newly saved entry which doesn't have an existing key in site_pages
                // so insert new keys for both stauses.
                if ($posted_entry_id && !isset($page_uris[$status][$lang_id][$data['entry_id']]))
                {
                    if (ee()->publisher_lib->publisher_save_status == $status && $status == PUBLISHER_STATUS_DRAFT)
                    {
                        $page_uris[$status][$lang_id][$data['entry_id']] = ee()->publisher_helper->add_prefix($data['pages_uri'], '/');
                        $page_templates[$status][$lang_id][$data['entry_id']] = $data['pages_template_id'];
                    }
                    else if (ee()->publisher_lib->publisher_save_status == $status && $status == PUBLISHER_STATUS_OPEN)
                    {
                        $page_uris[PUBLISHER_STATUS_OPEN][$lang_id][$data['entry_id']] = ee()->publisher_helper->add_prefix($data['pages_uri'], '/');
                        $page_templates[PUBLISHER_STATUS_OPEN][$lang_id][$data['entry_id']] = $data['pages_template_id'];

                        $page_uris[PUBLISHER_STATUS_DRAFT][$lang_id][$data['entry_id']] = ee()->publisher_helper->add_prefix($data['pages_uri'], '/');
                        $page_templates[PUBLISHER_STATUS_DRAFT][$lang_id][$data['entry_id']] = $data['pages_template_id'];
                    }
                }
            }
        }

        return array($page_uris, $page_templates);
    }

    /**
     * Recreate the nestedset array that Structure uses when pages are
     * reordered, then call the reorder method to simulate it, thus
     * creating all the proper page paths.
     *
     * @param  array $post_data
     * @param  array $meta_data
     * @return void
     */
    public function save($post_data = array(), $meta_data = array())
    {
        if ( !$this->is_installed(array(self::STRUCTURE_MODULE, self::PAGES_MODULE)))
        {
            return;
        }

        if ($this->is_installed(self::STRUCTURE_MODULE))
        {
            $this->save_structure($this->get_core_pages(TRUE));
        }
        else if ($this->is_installed(self::PAGES_MODULE))
        {
            $this->save_pages($this->get(FALSE, FALSE, ee()->publisher_lib->publisher_save_status), $post_data);
        }
    }

    /**
     * Get the site_pages array from the exp_sites table
     *
     * @return array
     */
    public function get_core_pages($refresh = FALSE)
    {
        if ($this->is_installed() && (empty($this->core_site_pages) || $refresh))
        {
            $qry = ee()->db->select('site_pages')
                    ->where('site_id', ee()->publisher_lib->site_id)
                    ->get('sites');

            $site_pages = unserialize(base64_decode($qry->row('site_pages')));

            $this->core_site_pages = $site_pages;

            return $site_pages;
        }

        return $this->core_site_pages;
    }

    /**
     * Get the site_pages array from the exp_sites table
     *
     * @return array
     */
    public function set_core_pages()
    {
        if ($this->is_installed())
        {
            $qry = ee()->db->select('site_pages')
                    ->where('site_id', ee()->publisher_lib->site_id)
                    ->get('sites');

            $this->core_site_pages = unserialize(base64_decode($qry->row('site_pages')));
        }
    }

    /**
     * Recreate a nested set data array that Pages doesn't natively
     * use, but we need in order to share the build() method.
     *
     * @param  array $site_pages
     * @return void
     */
    private function save_pages($site_pages, $post_data)
    {
        if ( !empty($site_pages) &&
             isset($post_data['pages_uri']) &&
             $post_data['pages_uri'] != '' &&
             $post_data['pages_uri'] != lang('example_uri')
        ){
            $this->rebuild($post_data, $site_pages[ee()->publisher_lib->site_id]);
        }
    }

    /**
     * Recreate the required nested set data array that Structure
     * is expecting, then pass it along.
     *
     * @param  array $site_pages
     * @return void
     */
    private function save_structure($site_pages)
    {
        if ( !empty($site_pages))
        {
            $nested_set = array();
            $entry_ids = array_keys($site_pages[ee()->publisher_lib->site_id]['uris']);

            foreach ($entry_ids as $entry_id)
            {
                $qry = ee()->db->select('lft, rgt')
                         ->where('entry_id', $entry_id)
                         ->get('structure');

                $this->crumbs = array();

                $this->get_structure_crumbs($entry_id);

                $nested_set[$entry_id] = array(
                    'lft' => $qry->row('lft'),
                    'rft' => $qry->row('rgt'),
                    'crumb' => array_reverse($this->crumbs)
                );
            }

            $this->rebuild($nested_set, $site_pages[ee()->publisher_lib->site_id]);
        }
    }

    /**
     * Recursive - Search for all child pages of the entry
     * @param  int $entry_id
     * @return boolean
     */
    private function get_structure_crumbs($entry_id)
    {
        if (is_numeric($entry_id))
        {
            $qry = ee()->db->select('parent_id')
                    ->where('entry_id', $entry_id)
                    ->where('site_id', ee()->publisher_lib->site_id)
                    ->get('structure');

            // If it has no parent, then it is a root node, so add itself.
            if ($qry->row('parent_id') == 0)
            {
                $this->crumbs[] = (int) $entry_id;
            }
            else
            {
                if ($qry->row('parent_id') AND is_numeric($qry->row('parent_id')))
                {
                    if ( !in_array($entry_id, $this->crumbs))
                    {
                        $this->crumbs[] = (int) $entry_id;
                    }
                    else
                    {
                        $this->crumbs[] = (int) $qry->row('parent_id');
                    }

                    $this->get_structure_crumbs($qry->row('parent_id'));
                }
            }
        }

        return FALSE;
    }

    /**
     * Recursive - Search for all child pages of the entry
     * @param  int $entry_id
     * @return boolean
     */
    private function get_pages_crumbs($entry_id, Array $uris = array())
    {
        if (is_numeric($entry_id))
        {
            $uri = $uris[$entry_id];

            $uri_segments = explode('/', $uri);
            array_pop($uri_segments);

            $parent_uri = $uri_segments;

            $parent_id = array_search($parent_uri, $uris);

            // If it has no parent, then it is a root node, so add itself.
            if ($qry->row('parent_id') == 0)
            {
                $this->crumbs[] = (int) $entry_id;
            }
            else
            {
                if ($qry->row('parent_id') AND is_numeric($qry->row('parent_id')))
                {
                    if ( !in_array($entry_id, $this->crumbs))
                    {
                        $this->crumbs[] = (int) $entry_id;
                    }
                    else
                    {
                        $this->crumbs[] = (int) $qry->row('parent_id');
                    }

                    $this->get_pages_crumbs($qry->row('parent_id'));
                }
            }
        }

        return FALSE;
    }

    /**
     * Get all the site_pages, organized by language id, status, site_id
     *
     * @return array
     */
    public function get_all()
    {
        $all = array();

        foreach (array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT) as $status)
        {
            foreach(ee()->publisher_model->languages as $lang_id => $data)
            {
                $all[$status][$lang_id] = ee()->publisher_site_pages->get($lang_id, FALSE, $status, TRUE);
            }
        }

        return $all;
    }

    /**
     * Get the site_pages array, but by language and/or draft/open status
     *
     * @param  boolean $lang_id
     * @param  boolean $uris_only
     * @param  boolean $status
     * @param  boolean $refresh_cache If calling this for translations we can't use cache.
     * @return array
     */
    public function get($lang_id = FALSE, $uris_only = FALSE, $status = FALSE, $refresh_cache = FALSE)
    {
        // Make sure Pages or Structure is installed before we attempt the following.
        if ( !$this->is_installed())
        {
            return array();
        }

        $site_pages = array();
        $lang_id = $lang_id ?: ee()->publisher_lib->lang_id;

        // This is temporary... 6/5/13
        // cache below is used to optimize this method as much as possible, but
        // translations don't work when we cache the first request and use it repeatedly.
        // Idea here is to go through the rest of Publisher and update calls to this
        // method so we can use the optimized version when possible... if possible.
        $refresh_cache = TRUE;

        if ( !$status)
        {
            if (ee()->input->get('publisher_status'))
            {
                $status = ee()->input->get('publisher_status');
            }
            else
            {
                $status = ee()->publisher_lib->status;
            }
        }

        if ( !isset($this->cache['site_pages']))
        {
            $qry = ee()->db->select('site_pages, publisher_lang_id, publisher_status')
                    ->where('site_id', ee()->publisher_lib->site_id)
                    ->get('publisher_site_pages');

            $this->cache['site_pages'] = array();

            foreach ($qry->result() as $row)
            {
                $this->cache['site_pages'][$row->publisher_lang_id][$row->publisher_status] = json_decode($row->site_pages, TRUE);
            }
        }

        if ( !isset($this->cache['site_pages_current']) || $refresh_cache)
        {
            // Only if we have translations and its enabled
            if (isset($this->cache['site_pages'][$lang_id][$status]))
            {
                $site_pages = $this->cache['site_pages'][$lang_id][$status];

                $default_site_pages = ee()->config->item('site_pages');

                // Make sure that we return an array of the same number of values, could possibly
                // have the default language value in for some pages.
                if (isset($default_site_pages[ee()->publisher_lib->site_id]) && isset($default_site_pages[ee()->publisher_lib->site_id]['uris']))
                {
                    foreach ($default_site_pages[ee()->publisher_lib->site_id]['uris'] as $key => $uri)
                    {
                        if ( !isset($site_pages[ee()->publisher_lib->site_id]['uris'][$key]))
                        {
                            $site_pages[ee()->publisher_lib->site_id]['uris'][$key] = $default_site_pages[ee()->publisher_lib->site_id]['uris'][$key];
                        }
                    }
                }

                $this->cache['site_pages_current'] = $site_pages;
            }
            else if (isset($this->cache['site_pages'][ee()->publisher_lib->default_lang_id][$status]))
            {
                $this->cache['site_pages_current'] = $this->cache['site_pages'][ee()->publisher_lib->default_lang_id][$status];
            }
            else
            {
                $this->cache['site_pages_current'] = ee()->config->item('site_pages');
            }
        }

        $site_pages = $this->cache['site_pages_current'];

        if ($uris_only)
        {
            if (isset($site_pages[ee()->publisher_lib->site_id]['uris']))
            {
                $site_pages = $site_pages[ee()->publisher_lib->site_id]['uris'];
            }
            else
            {
                $site_pages = array();
            }
        }
        else
        {
            // If this is set, then its a FE request, not a CP
            if (ee()->publisher_helper_url->should_add_prefix())
            {
                $code = ee()->publisher_lib->lang_code .'/';
                $site_url = ee()->config->item('site_url');

                $new_site_url = reduce_double_slashes($site_url . $code);

                // Clean up possible double language codes.
                // REMOVED for now in 1.1. site.de/de/ will mess this up.
                // $new_site_url = str_replace($code.$code, $code, $new_site_url);

                $site_pages[ee()->publisher_lib->site_id]['url'] = $new_site_url;
            }
            else if (isset(ee()->publisher_lib->lang_code))
            {
                $site_pages[ee()->publisher_lib->site_id]['url'] = ee()->config->item('site_url');
            }
        }

        return $site_pages;
    }

    /**
     * Get the URL to a page
     *
     * @param  int $entry_id
     * @return string
     */
    public function get_url($entry_id, $channel_id = FALSE, $status = PUBLISHER_STATUS_DRAFT, $prefix = FALSE, $post_data = array())
    {
        if ( !$entry_id) return FALSE;

        $pages = $this->get(FALSE, TRUE, $status);
        $site_url = ee()->publisher_helper_url->get_site_index();

        // If a language prefix was passed, prepend it to the URL
        if (ee()->publisher_setting->url_prefix())
        {
            $prefix = $prefix ?: ee()->publisher_lib->lang_code;
            $site_url = ee()->publisher_helper_url->set_prefix($site_url, FALSE, $prefix);
        }

        // Is it in the site_pages array already?
        if (isset($pages[$entry_id]))
        {
            return reduce_double_slashes($site_url . $pages[$entry_id]);
        }

        return FALSE;
    }

    /**
     * Get the translated url_title field
     *
     * @param  int $entry_id
     * @return string
     */
    public function get_url_title($entry_id)
    {
        if ( !$entry_id) return FALSE;

        $qry = ee()->db->select('url_title')
                            ->where('site_id', ee()->publisher_lib->site_id)
                            ->where('publisher_status', ee()->publisher_lib->status)
                            ->where('publisher_lang_id', ee()->publisher_lib->lang_id)
                            ->where('entry_id', $entry_id)
                            ->get('publisher_titles');

        return $qry->num_rows() ? $qry->row('url_title') : FALSE;
    }

    /**
     * Get the translated page_url field
     *
     * @param  int $entry_id
     * @return string
     */
    public function get_page_url($entry_id, $lang_id = FALSE)
    {
        if ( !$entry_id) return FALSE;

        $lang_id = $lang_id ?: ee()->publisher_lib->lang_id;

        $qry = ee()->db->select('page_url')
                       ->where('site_id', ee()->publisher_lib->site_id)
                       ->where('publisher_status', ee()->publisher_lib->status)
                       ->where('publisher_lang_id', $lang_id)
                       ->where('entry_id', $entry_id)
                       ->get('publisher_titles');

        return $qry->num_rows() ? $qry->row('page_url') : FALSE;
    }

    /**
     * Delete a page from ALL site_pages arrays
     *
     * @param  int $entry_id
     * @return void
     */
    public function delete($entry_id)
    {
        if ( !$this->is_installed())
        {
            return;
        }

        $qry = ee()->db->select('id, site_pages')
                ->where('site_id', ee()->publisher_lib->site_id)
                ->get('publisher_site_pages');

        foreach ($qry->result() as $row)
        {
            if ($row->site_pages != '')
            {
                $site_pages = json_decode($row->site_pages, TRUE);

                foreach ($site_pages as $site_id => $pages)
                {
                    unset($site_pages[$site_id]['uris'][$entry_id]);
                    unset($site_pages[$site_id]['templates'][$entry_id]);
                }

                $data = array(
                    'site_pages' => $this->json_encode_pages($site_pages)
                );

                $where = array(
                    'id' => $row->id
                );

                $this->insert_or_update('publisher_site_pages', $data, $where);
            }
        }
    }

    /**
     * Find the translated or open/draft version of a Page URI from Pages or Structure
     *
     * @param  string $url Referring URL, or specific URL passed to method
     * @param  integer $lang_id_from Find a URL based off this language
     * @param  integer $lang_id_to The language of the URL we want to return
     * @param  string  $original_requested_url When recursing keep track of the original incase we need to return it
     * @return string
     */
    public function find_related($url = NULL, $lang_id_from = NULL, $lang_id_to = NULL, $original_requested_url = NULL, $original_extra_segments = array())
    {
        $url = $url ? $url : ee()->publisher_session->get_referrer_url();

        // If $lang_id_from and _to are not set then we're looking for translations based
        // off the previous lang_id from the set_language() action.
        $lang_id_from = $lang_id_from ?: ee()->publisher_lib->prev_lang_id;
        $lang_id_to   = $lang_id_to   ?: ee()->publisher_lib->lang_id;

        $requested_url = $url;
        $requested_segments = explode('/', ee()->publisher_helper_url->remove_site_index($url));

        // Get only the uri_string from the URL
        $url = $requested_segments;

        // Make sure the first segment is not a language code
        if (isset($url[0]) AND in_array($url[0], $this->language_codes))
        {
            array_shift($url);
        }

        // Format in a way that site_pages will recognize it
        $url = '/'.trim(implode('/', $url), '/');

        $current_status = ee()->publisher_lib->prev_status;
        $new_status = ee()->input->get('publisher_status');

        // Clean up the URL, remove ?status from it
        if (strstr($url, '?'))
        {
            $url = explode('?', $url);
            $url = rtrim($url[0], '/');
        }

        $current_site_pages = $this->get($lang_id_from, TRUE, $current_status, FALSE);

        if ($key = array_search($url, $current_site_pages))
        {
            $lang_code = isset(ee()->publisher_session->requested_language_code)
                         ? ee()->publisher_session->requested_language_code
                         : ee()->publisher_lib->lang_code;

            $new_site_pages = $this->get($lang_id_to, TRUE, $new_status);

            if (ee()->publisher_setting->url_prefix() AND isset($new_site_pages[$key]))
            {
                $url = ee()->publisher_helper_url->get_site_index() . $lang_code .'/'. $new_site_pages[$key];
            }
            else if (isset($new_site_pages[$key]))
            {
                $url = ee()->publisher_helper_url->get_site_index() . $new_site_pages[$key];
            }
            else
            {
                $url = $requested_url;
            }

            if ($new_status)
            {
                // Cleanup the URL, remove stuff Publisher needs so it
                // doesn't redirect loop or do funky stuff, but keep
                // the rest, don't want to blow away anyone's URIs.
                unset($_GET['ACT']);
                unset($_GET['site_id']);

                $_GET['status'] = $new_status;
                $url .= '?'.http_build_query($_GET);
            }

            // $this->get_category_breaking_segment($requested_segments);

            return reduce_double_slashes($url);

            // TODO: chop off last segment and do a URI lookup again, recursive call to this method?
            // Must send original $requested_url as a param and if the lookup fails and no segments
            // are left then return the $requested_url

            // $url_parts = explode('/', $requested_url);
            // $last_segment = array_pop($url_parts);

            // if (count($url_parts) > 0)
            // {
            //     $url = implode('/', $url_parts);
            //     $original_extra_segments[] = $last_segment;
            //     $original_requested_url = $original_requested_url !== NULL ? $original_requested_url : $requested_url;
            //     $requested_url = $this->find_related($url, $lang_id_from, $lang_id_to, $original_requested_url, $original_extra_segments);
            // }

            // $segs = implode('/', array_reverse($original_extra_segments));

            // if ($segs)
            // {
            //     $segs = '/'.$segs;
            // }
        }

        return FALSE;
    }

    private function get_category_breaking_segment($segments)
    {
        // $parts = explode('/', $url);

        // var_dump($segments);
    }

    /**
     * Create {page_uri:XX} variables. Can be used in start_from param
     * in Structure nav, or another module.
     *
     * @return void
     */
    public function create_page_uri_vars()
    {
        $site_pages = $this->get(FALSE, TRUE);

        // Only do this if there is page data, and we're not within the control panel
        if( !empty($site_pages) && REQ == 'PAGE')
        {
            $data = array();

            if (($data = ee()->publisher_cache->driver->get('page_uri_vars')) === FALSE)
            {
                foreach($site_pages as $id => $url)
                {
                    $data['page_uri:'. $id] = $url;

                    // Only if Wyvern isn't installed b/c it already does this.
                    if (!array_key_exists('wyvern', ee()->addons->get_installed('modules')))
                    {
                        $url = ee()->publisher_helper_url->set_prefix($url, $url, ee()->publisher_lib->lang_code);
                        $data['page_url:'. $id] = $url;
                    }
                }

                ee()->publisher_cache->driver->save('page_uri_vars', $data);
            }

            ee()->config->_global_vars = array_merge(ee()->config->_global_vars, $data);
        }
    }

    /**
     * Hi-jack the {structure:x:x} variables and update them to the translated values
     *
     * @return  void
     */
    public function set_structure_global_vars()
    {
        if ( !$this->is_installed(self::STRUCTURE_MODULE))
        {
            return;
        }

        $site_pages = $this->get(FALSE, TRUE);

        // Make sure there is Structure data
        if (is_array($site_pages) && count($site_pages) > 0)
        {
            $segments = ee()->uri->segments;

            // Remove last segment if its a /Px pagination segment.
            if (preg_match('/P\d+/', end($segments)))
            {
                array_pop($segments);
            }

            $uri = '/'.implode('/', $segments);
            $segment_1 = (isset($segments[1]) AND $segments[1] != '') ? '/'.$segments[1] : FALSE;
            $entry_id = array_search($uri, $site_pages);

            // Set defaults
            foreach (array('page', 'parent', 'top') as $type)
            {
                ee()->config->_global_vars['structure:'. $type .':title'] = '';
                ee()->config->_global_vars['structure:'. $type .':slug'] = '';
                ee()->config->_global_vars['structure:'. $type .':uri'] = '';
                ee()->config->_global_vars['structure:'. $type .':url'] = '';
            }

            // If no valid page was found, we can't process Structure data.
            if ( !$entry_id)
            {
                return;
            }

            $parent_id = (int) $this->sql->get_parent_id($entry_id);
            $top_id = array_search($segment_1, $site_pages);

            // If top isn't found, default to current page, most likely they're on the home page.
            $top_id = $top_id ?: $entry_id;

            $titles = $this->get_titles(array($entry_id, $parent_id, $top_id));
            $url_titles = $this->get_url_titles(array($entry_id, $parent_id, $top_id));

            $titles_default = isset($titles[ee()->publisher_lib->status][ee()->publisher_lib->default_lang_id]) ?
                        $titles[ee()->publisher_lib->status][ee()->publisher_lib->default_lang_id] :
                        array();

            $titles = isset($titles[ee()->publisher_lib->status][ee()->publisher_lib->lang_id]) ?
                        $titles[ee()->publisher_lib->status][ee()->publisher_lib->lang_id] :
                        array();

            $url_titles_default = isset($url_titles[ee()->publisher_lib->status][ee()->publisher_lib->default_lang_id]) ?
                        $url_titles[ee()->publisher_lib->status][ee()->publisher_lib->default_lang_id] :
                        array();

            $url_titles = isset($url_titles[ee()->publisher_lib->status][ee()->publisher_lib->lang_id]) ?
                        $url_titles[ee()->publisher_lib->status][ee()->publisher_lib->lang_id] :
                        array();

            $site_url = ee()->config->item('site_url');

            $types = array(
                'page' => $entry_id,
                'parent' => $parent_id,
                'top' => $top_id
            );

            foreach ($types as $type => $id)
            {
                if ( !isset($titles[$id]) AND !isset($titles_default[$id]))
                {
                    continue;
                }

                ee()->config->_global_vars['structure:'. $type .':title'] = $id !== FALSE ?
                    (isset($titles[$id]) ? $titles[$id] : $titles_default[$id]) : FALSE;

                $slug = FALSE;

                if (isset($url_titles[$id]))
                {
                    if ($url_titles[$id] == '/')
                    {
                        $slug = '/';
                    }
                    else
                    {
                        $slug = array_pop(explode('/', $url_titles[$id]));
                    }
                }
                else if (isset($url_titles_default[$id]))
                {
                    if ($url_titles_default[$id] == '/')
                    {
                        $slug = '/';
                    }
                    else
                    {
                        $slug = array_pop(explode('/', $url_titles_default[$id]));
                    }
                }

                ee()->config->_global_vars['structure:'. $type .':slug'] = $id !== FALSE ?
                    (isset($url_titles[$id]) ? $slug : $slug) : FALSE;

                $uri = isset($site_pages[$id]) ? $site_pages[$id] : FALSE;

                ee()->config->_global_vars['structure:'. $type .':uri'] = $uri;
                ee()->config->_global_vars['structure:'. $type .':url'] = reduce_double_slashes($site_url . $uri);
            }
        }
    }

    /**
     * Called via ext.publisher.php->template_post_parse(), replaces Structure
     * specific global variables with translated versions fo them.
     *
     * @param  string $template Template string being parsed
     * @return string
     */
    public function replace_structure_variables($template)
    {
        // url, uri, and slug are handled by Structure
        $variables = array(
            // 'structure:page_url_for:',
            // 'structure:page_uri_for:',
            'structure:page_title_for:',
            // 'structure:page_slug_for:'
        );

        $this->tagdata = $template;

        foreach ($variables as $var)
        {
            $this->structure_variable = $var;

            preg_replace_callback("/".LD. preg_quote($var) ."(.*?)".RD."/", array(&$this, '_replace_structure_variables'), $this->tagdata);
        }

        return $this->tagdata;
    }

    private function _replace_structure_variables($matches)
    {
        if ( !isset($matches[0]))
        {
            return;
        }

        $var = $matches[0];
        $entry_id = $matches[1];
        $var_parts = explode(':', $var);
        $new_variable = FALSE;

        if ( !isset($var_parts[1]))
        {
            return;
        }

        $var_type = $var_parts[1];

        // $site_pages = ee()->publisher_site_pages->get(FALSE, TRUE);
        // $uri = $site_pages[$entry_id];

        $entry = ee()->publisher_entry->get($entry_id);

        switch ($var_type)
        {
            case 'page_title_for':
                if ($entry) {
                    $new_variable = $entry->title;
                }
            break;
        }

        if ($new_variable)
        {
            $this->tagdata = str_replace($var, $new_variable, $this->tagdata);
        }
    }

    /*
    The following is shamelessly stolen from mod.structure.php so I can avoid PHP errors introduced in EE 2.8
    */

    /**
     * Get all data from the exp_structure_channels table
     * @param $type|unmanaged|page|listing|asset
     * @param $channel_id you can pass a channel_id to retreive it's data
     * @param $order pass it 'alpha' to order by channel title
     * @return array An array of channel_ids and it's associated template_id, type and channel_title
     */
    private function _get_structure_channels($type = '', $channel_id = '', $order = '', $allowed = FALSE)
    {
        $site_id = ee()->config->item('site_id');

        $allowed_channels = count(ee()->functions->fetch_assigned_channels()) > 0 ? implode(',', ee()->functions->fetch_assigned_channels()) : FALSE;

        if ($allowed_channels === FALSE)
            return NULL;


        // Get Structure Channel Data
        $sql = "SELECT ec.channel_id, ec.channel_title, esc.template_id, esc.type, ec.site_id
                FROM exp_channels AS ec
                LEFT JOIN exp_structure_channels AS esc ON ec.channel_id = esc.channel_id
                WHERE ec.site_id = '$site_id'";
        if ($allowed === TRUE) $sql .= " AND esc.channel_id IN ($allowed_channels)";
        if ($type != '') $sql .= " AND esc.type = '$type'";
        if ($channel_id != '') $sql .= " AND esc.channel_id = '$channel_id'";
        if ($order == 'alpha') $sql .= " ORDER BY ec.channel_title";

        $results = ee()->db->query($sql);

        // Format the array nicely
        $channel_data = array();
        foreach($results->result_array() as $key => $value)
        {
            $channel_data[$value['channel_id']] = $value;
            unset($channel_data[$value['channel_id']]['channel_id']);
        }

        return $channel_data;
    }

    /**
     * @param parent_uri
     * @param listing_uri/slug
    */
    private function _create_full_uri($parent_uri, $listing_uri)
    {
        $uri = $this->_create_uri($listing_uri);
        // prepend the parent uri
        $uri = $parent_uri . '/' . $uri;
        // ensure beginning and ending slash
        $uri = '/' . trim($uri, '/');
        // if double slash, reduce to one
        return str_replace('//', '/', $uri);
    }

    /**
     * @param submitted_uri
     * @param default_uri
    */
    private function _create_uri($uri, $url_title = '')
    {
        // if structure_uri is not entered use url_title
        $uri = $uri ?: $url_title;
        // Clean it up TODO replace with EE create URL TITLE?
        $uri = preg_replace("#[^a-zA-Z0-9_\-]+#i", '', $uri);
        // Make sure there are no "_" underscores at the beginning or end
        return trim($uri, "_");
    }
}