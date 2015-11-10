<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Extension Class
 *
 * @package     ExpressionEngine
 * @subpackage  Extensions
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

class Publisher_ext {

    /**
     * Enable/Disable local debugging, dumps site_pages arrays if true
     * @var boolean
     */
    private $debug = FALSE;

    /**
     * Extension settings - empty, Publisher doesn't use it
     * @var array
     */
    public $settings = array();

    /**
     * Extension description
     * @var string
     */
    public $description = PUBLISHER_DESC;

    /**
     * Extension documentation URL
     * @var string
     */
    public $docs_url = PUBLISHER_DOCS;

    /**
     * Extension Name
     * @var string
     */
    public $name = PUBLISHER_NAME;

    /**
     * Does the extension have its own settings?
     * @var string
     */
    public $settings_exist = 'n';

    /**
     * Add-on version
     * @var integer
     */
    public $version = PUBLISHER_VERSION;

    /**
     * Will auto install when module is installed
     * @var array
     */
    public $required_by = array('module');

    /**
     * Constructor
     *
     * @param   mixed   Settings array or empty string if none exist.
     */
    public function __construct($settings = '') {}

    /**
     * Called at the very beginning of all EE requests
     *
     * @return void
     */
    public function sessions_start()
    {
        if ( !session_id())
        {
            @session_start();
        }

        $publisher_path = PATH_THIRD . 'publisher/';
        ee()->load->add_package_path($publisher_path);

        ee()->load->library('Publisher/Publisher_lib');
        ee()->publisher_lib->path = $publisher_path;

        ee()->load->model('publisher_setting');
        ee()->publisher_setting->load();

        ee()->publisher_lib->entry_id = NULL; // Will be set later in Publisher_lib
        ee()->publisher_lib->site_id  = ee()->config->item('site_id');

        // Are we in the middle of a language switch request?
        ee()->publisher_lib->switching = FALSE;

        // Determine the default view status of entries from inside the control panel.
        $default_status = (REQ == 'CP') ? ee()->publisher_setting->default_view_status() : PUBLISHER_STATUS_OPEN;

        // Search for post b/c it may be posted in a Safecracker form.
        if ($view_status = ee()->input->post('publisher_view_status'))
        {
            ee()->publisher_lib->status = $view_status;
        }
        else
        {
            ee()->publisher_lib->status = ee()->input->get_post('publisher_status') ? ee()->input->get_post('publisher_status') : $default_status;
        }

        // Duh, if 3rd party add-ons are referencing ee()->publisher_lib->status
        // on save actions, then I need to actually update that value.
        if (ee()->input->get_post('publisher_save_status'))
        {
            ee()->publisher_lib->publisher_save_status = ee()->input->get_post('publisher_save_status');
        }
        else
        {
            ee()->publisher_lib->publisher_save_status = PUBLISHER_STATUS_OPEN;
        }

        // If drafts are disabled, status is always Open
        if (ee()->publisher_setting->disable_drafts())
        {
            ee()->publisher_lib->status = PUBLISHER_STATUS_OPEN;
        }

        // Make sure the requested status is even valid, if not, use the default
        if ( !in_array(ee()->publisher_lib->status, array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT)))
        {
            ee()->publisher_lib->status = $default_status;
        }

        // Load these first, models use it
        ee()->load->library('Publisher/helpers/Publisher_helper');
        ee()->load->library('Publisher/Publisher_cache');

        // Load our models
        ee()->load->model('publisher_log');
        ee()->load->model('publisher_model');
        ee()->load->model('publisher_entry');
        ee()->load->model('publisher_phrase');
        ee()->load->model('publisher_category');
        ee()->load->model('publisher_relationships');
        ee()->load->model('publisher_approval');
        ee()->load->model('publisher_template');
        ee()->load->model('publisher_site_pages');
        ee()->load->model('publisher_query');

        $this->_register_hooks(array(
            'relationship',
            'assets',
            'matrix',
            'playa',
            'structure',
            'zenbu',
            'grid'
        ));

        // Set default language on the main object for easier reference
        ee()->publisher_lib->default_lang_id = ee()->publisher_model->default_language_id;

        // Load the rest of the libraries/helpers
        ee()->load->library('Publisher/Publisher_session');
        ee()->load->library('Publisher/Publisher_router');
        ee()->load->library('Publisher/helpers/Publisher_helper_url');

        // Save the status so switching between the two is accurate
        ee()->publisher_lib->prev_status = ee()->publisher_session->get('site_status');
        ee()->publisher_session->set('site_status', ee()->publisher_lib->status);

        // Are we posting or getting data in an ignored channel?
        if (ee()->publisher_model->is_ignored_channel(ee()->input->get_post('channel_id')))
        {
            ee()->publisher_lib->is_ignored_channel = TRUE;
        }
        else
        {
            ee()->publisher_lib->is_ignored_channel = FALSE;
        }

        // Make sure our language ID is correct
        if ( !ee()->publisher_helper->cookies_allowed())
        {
            ee()->publisher_lib->lang_id = ee()->publisher_lib->default_lang_id;
        }
        elseif (REQ != 'CP' && ($lang_code = ee()->config->item('publisher_lang_override')))
        {
            $lang_id = array_search($lang_code, ee()->publisher_model->language_codes);
            ee()->publisher_lib->lang_id = $lang_id;
        }
        // Saving an entry in the CP. If opening new tabs make sure
        // it doesn't save incorrectly by using the session/cookie instead.
        elseif (REQ == 'CP' && $lang_id = ee()->input->post('site_language'))
        {
            ee()->publisher_lib->lang_id = $lang_id;
        }
        // GET used in CP, and POST used in Channel:Form
        elseif ($lang_id = ee()->input->get_post('lang_id'))
        {
            ee()->publisher_lib->lang_id = $lang_id;
        }
        elseif ($lang_id = ee()->publisher_session->get_cookie())
        {
            ee()->publisher_lib->lang_id = $lang_id;
        }
        else
        {
            ee()->publisher_lib->lang_id = ee()->publisher_lib->default_lang_id;
        }

        // Is Publisher even enabled? Or is it loaded from command line?
        if (PHP_SAPI !== 'cli')
        {
            // Set URL prefix, language, $_SESSION, $_COOKIE etc
            ee()->publisher_session->go();
        }
    }

    /**
     * Called after the session object has been created
     *
     * @param   object $session
     * @return  $session
     */
    public function sessions_end($session)
    {
        if (ee()->publisher_setting->enabled())
        {
            // Extend and modify the Api, crucial to making all this work.
            ee()->load->library('api');
            ee()->api->instantiate('channel_fields');
            ee()->load->library('Publisher/Publisher_api_channel_fields', NULL, 'api_channel_fields');
        }

        // Make sure the session is in the EE global object
        ee()->session = $session;

        if ( !isset(ee()->session->cache['publisher']))
        {
            ee()->session->cache['publisher'] = array();
        }

        ee()->load->library('Publisher/Publisher_role');

        if (REQ == 'CP')
        {
            // Load some stuffs
            ee()->load->library('Publisher/Publisher_email');
            ee()->load->library('javascript');
            ee()->load->library('Publisher/helpers/Publisher_helper_cp');

            // Set some base URLs used to create module page and ajax links
            ee()->publisher_helper_cp->set_cp_base_url();

            // Add our action URLs to the global EE JS object.
            ee()->publisher_helper_cp->set_js();
        }

        // Stop here, don't need anything else from this method.
        if ( !ee()->publisher_setting->enabled())
        {
            // If disabling on a site that is using root_url links to asset files
            // might break, so set it to the same as site_url so it can be disabled gracefully.
            ee()->config->_global_vars['root_url'] = ee()->config->item('site_url');

            return $session;
        }

        if (REQ == 'PAGE' OR REQ == 'ACTION')
        {
            // So all phrases are accessible as early parsed global vars
            ee()->publisher_phrase->set_globals();

            // Create early parsed global var of the current languages text direction
            ee()->publisher_helper->set_text_direction();

            // Make sure the core user_data object has the correct language set
            // so the lang.whatever.php files are used for error messages and such.
            ee()->publisher_session->set_core_language();

            // Hi-jack Structures global variables
            ee()->publisher_site_pages->set_structure_global_vars();
        }

        // Set the current role of the user: ee()->publisher_role->current
        ee()->publisher_role->set();

        // If its a CP request, do a few more things. Listen for POST/GET data to
        // perform certain actions we can't handle via hooks.
        if (REQ == 'CP')
        {
            ee()->load->library('Publisher/publisher_cp_events');
            ee()->publisher_cp_events->handler($session);
        }

        // Call fieldtype hooks if they exist.
        ee()->publisher_lib->call('sessions_end');

        $this->debug();

        return $session;
    }

    /**
     * After form validation occurs but before submission.
     * Is call during autosave and normal save proceedure.
     *
     * @param array $meta Meta data such as author, categories, status etc
     * @param array $data The submitted custom field values
     * @param boolean $autosave Is it initiating an autosave?
     * @return
     */
    public function entry_submission_ready($meta, $data, $autosave)
    {
        // Call fieldtype hooks if they exist.
        ee()->publisher_lib->call('entry_submission_ready', array(
            'meta' => $meta,
            'data' => $data,
            'autosave' => $autosave
        ));

        if ( !isset($meta['channel_id']) || ee()->publisher_model->is_ignored_channel($meta['channel_id']))
        {
            return;
        }

        ee()->publisher_lib->is_new_entry = ($data['entry_id'] == 0) ? TRUE : FALSE;
    }

    /**
     * Before the entry is submitted and validation passed, see
     * if we need to delete anything instead of saving an entry.
     *
     * @param
     * @return
     */
    public function entry_submission_start($channel_id, $autosave)
    {
        // Call fieldtype hooks if they exist.
        ee()->publisher_lib->call('entry_submission_start', array(
            'channel_id' => $channel_id,
            'autosave' => $autosave
        ));

        if ( !$channel_id || ee()->publisher_model->is_ignored_channel($channel_id))
        {
            return;
        }

        // Make sure someone isn't submitting an entry with a URL Title the
        // same as a language code, otherwise chaos ensues on the front-end
        // and its a potential debugging nightmare.
        // Been there and don't ever want to go back.
        $language_codes = array_keys(ee()->publisher_model->get_languages('short_name', TRUE));
        $url_title = ee()->input->post('url_title');

        if (in_array($url_title, $language_codes))
        {
            ee()->api_channel_entries->errors['url_title'] = lang('url_title') .' can\'t be the same as a language code.';
        }

        $redirect = FALSE;
        $status   = ee()->input->get_post('publisher_view_status');
        $lang_id  = ee()->input->get_post('publisher_lang_id');

        // If Delete Draft was clicked...
        if (ee()->input->post('delete_draft') && ee()->input->post('entry_id'))
        {
            ee()->publisher_entry->delete_draft(ee()->input->post('entry_id', TRUE));
            $redirect = TRUE;
            $status   = PUBLISHER_STATUS_OPEN;
        }

        // If Delete Translation was clicked...
        if (ee()->input->post('delete_translation') && ee()->input->post('entry_id'))
        {
            ee()->publisher_entry->delete_translation(ee()->input->post('entry_id', TRUE), ee()->input->post('site_language', TRUE));
            $redirect = TRUE;
            $lang_id = ee()->publisher_lib->default_lang_id;
        }

        // Delete an the approval if it exists, b/c we just accepted the draft...
        if ( !ee()->publisher_setting->sync_drafts() && ee()->input->post('accept_draft'))
        {
            ee()->publisher_approval_entry->delete(ee()->input->post('entry_id', TRUE));
        }

        if ($redirect)
        {
            ee()->functions->redirect(ee()->publisher_helper_url->get_cp_url(array(
                'C'             => 'content_publish',
                'M'             => 'entry_form',
                'channel_id'    => ee()->input->get_post('channel_id'),
                'entry_id'      => ee()->input->get_post('entry_id'),
                'lang_id'       => $lang_id,
                'publisher_status' => $status
            )));
        }

        // Grab site_pages prior to modification and safe for later reference
        ee()->publisher_site_pages->set_core_pages();
    }

    /**
     * Called when all EE entry save proceedures are done.
     *
     * If 3rd party lib has a entry_submission_absolute_end it will get called.
     * If it needs to process even if its an ignored channel then it must reference
     * ee()->publisher_lib->publisher_save_status/lang_id, not the $params, otherwise
     * they'll be false.
     *
     * @param int $entry_id Entry ID of submitted entry
     * @param array $meta Meta data such as author, categories, status etc
     * @param array $data The submitted custom field values
     * @return void
     */
    public function entry_submission_absolute_end($entry_id, $meta, $data)
    {
        // New entries have 0 as the entry_id, pass it along.
        $data['entry_id'] = $entry_id;

        // So we can reference this data on the Preview page
        $_SESSION['publisher_post_data'] = array_merge($_POST, $meta, $data);

        $publisher_save_status = ee()->publisher_lib->publisher_save_status;
        $publisher_view_status = ee()->input->post('publisher_view_status');

        // Bust our Cache, delete everything.
        ee()->publisher_cache->driver->delete();

        if ( !isset($meta['channel_id']) || ee()->publisher_model->is_ignored_channel($meta['channel_id']))
        {
            // Last chance to give 3rd party field types the opportunity to do any processing after an entry is saved
            ee()->publisher_lib->call('entry_submission_absolute_end', array(
                'entry_id'      => $entry_id,
                'meta'          => $meta,
                'data'          => $data,
                'publisher_save_status' => $publisher_save_status,
                'publisher_view_status' => $publisher_view_status,
                'publisher_lang_id' => ee()->publisher_lib->default_lang_id
            ));

            return;
        }

        // Set this so new entries get it.
        ee()->publisher_lib->entry_id = $entry_id;

        // Prepare base fields: status, lang_id, channel_id, date fields etc
        ee()->publisher_entry->prepare($entry_id, $meta, $data);

        // Insert data from ee()->publisher_model->data_columns / title_columns.
        // Those class properties are updated through Publisher lib for each field
        // and prepared for insertion here.
        ee()->publisher_entry->save();

        // If new entry saved as Draft, make sure exp_channel_titles is set to Draft so it does not appear on the site.
        ee()->publisher_entry->save_as_draft($meta);

        // Take care of any category assignments.
        ee()->publisher_category->save_category_posts($entry_id, $meta, $data);

        // Last chance to give 3rd party field types the opportunity to do any processing after an entry is saved
        ee()->publisher_lib->call('entry_submission_absolute_end', array(
            'entry_id'      => $entry_id,
            'meta'          => $meta,
            'data'          => $data,
            'publisher_save_status' => $publisher_save_status,
            'publisher_view_status' => $publisher_view_status,
            'publisher_lang_id' => ee()->publisher_lib->lang_id
        ));
    }

    /**
     * Alias to call entry_submission_absolute_end when a Safecracker form is submitted
     *
     * @param  object $sc Entire SC object with all necessary data
     * @return void
     */
    public function safecracker_submit_entry_end(&$sc) // TODO: rename to channel_form_submit_entry_end
    {
        if (isset($sc->EE))
        {
            $post = $sc->EE->api_sc_channel_entries;
            ee()->api_channel_entries = $sc->EE->api_sc_channel_entries;
            $this->entry_submission_absolute_end($post->entry_id, $post->meta, $post->data);
        }
        elseif (isset($sc->entry['entry_id']))
        {
            $this->entry_submission_absolute_end($sc->entry['entry_id'], $sc->entry, $sc->entry);
        }
    }

    /**
     * When a Safecracker form is loaded make sure the data is correct. Most of the data
     * is taken care of by Publisher b/c it instantiates the custom field handlers.
     * Categories need a bit more help.
     *
     * @param  string $tagdata Full Safecracker form
     * @param  object $sc      Entire SC object with all necessary data
     * @return string
     */
    public function safecracker_entry_form_tagdata_start($tagdata, &$sc)
    {
        if (isset($sc->entry) && !empty($sc->entry))
        {
            $status = isset(ee()->TMPL->tagparams['publisher_status']) ? ee()->TMPL->tagparams['publisher_status'] : ee()->publisher_lib->status;

            // Get the translated or draft version of the entry instead.
            $publisher_entry = ee()->publisher_entry->get($sc->entry['entry_id'], $status, FALSE, TRUE);

            if ($publisher_entry)
            {
                $sc->entry = array_merge($sc->entry, (array)$publisher_entry);
            }

            $sc->entry['categories'] = ee()->publisher_category->get_category_posts($sc->entry['entry_id'], $status);
        }

        return $tagdata;
    }

    /**
     * Make sure the Safecracker hidden fields and action URLs are correct.
     *
     * @param  string $tagdata Full Safecracker form
     * @param  object $sc      Entire SC object with all necessary data
     * @return string          Modified Safecracker form
     */
    public function safecracker_entry_form_tagdata_end($tagdata, &$sc)
    {
        ee()->load->library('Publisher/Publisher_parser');
        return ee()->publisher_parser->replace_form_variables($tagdata);
    }

    /**
     * Modify the entry results array with the translated data.
     *
     * @param  object $channel The channel object instance
     * @param  array  $results The array of entries found by EE
     * @return array
     */
    public function channel_entries_query_result($channel, $results)
    {
        if (ee()->extensions->last_call !== FALSE)
        {
            $results = ee()->extensions->last_call;
        }

        // @TODO See if channel param is present. If so, and it does not contain a | then
        // assume they are querying a single channel, then check to see if its ignored.
        // If its ignored, return $results here and forego all this.

        $entry_ids = array();
        ee()->publisher_entry->is_paginating = FALSE;
        $persistent_entries = ee()->publisher_setting->persistent_entries();

        // Only way to tell if its a Search results pagination is if pagination->offset is a float
        // and not an integer. Adding || ee()->publisher_helper_url->is_search_url() to the conditional
        // below doesn't work either b/c the search module query is not in the db->queries array :(
        // var_dump($channel->pagination);
        if (isset($channel->pager_sql) && $channel->pager_sql !== '' && !$persistent_entries)
        {
            ee()->publisher_entry->is_paginating = TRUE;
            $entry_ids = ee()->publisher_entry->get_pagination_entries($channel);
        }
        else
        {
            foreach ($results as $entry)
            {
                $entry_ids[] = $entry['entry_id'];
            }
        }

        if ( !isset(ee()->session->cache['publisher']['entries_query_result_entry_ids']))
        {
            ee()->session->cache['publisher']['entries_query_result_entry_ids'] = array();
        }

        ee()->session->cache['publisher']['entries_query_result_entry_ids'] = $entry_ids;

        // If we're in production mode, and viewing the default language, don't
        // do anything because the default language published data is in exp_channel_data already.
        if (ee()->publisher_lib->is_default_mode === TRUE || ee()->TMPL->fetch_param('disable_publisher') == 'yes')
        {
            // If turned off, we still need to do some filtering, and a query :/
            if ( !$persistent_entries)
            {
                $results = ee()->publisher_entry->get_all($entry_ids, $results, ee()->publisher_lib->lang_id, FALSE);
            }
            // If running in default/original mode, no extra queries required. Sorting is already done for us too.
            else
            {
                return ee()->publisher_entry->add_publisher_fields_to_results($results);
            }
        }

        // Grab translated data in one go and merge with the result array.
        $results = ee()->publisher_entry->get_all($entry_ids, $results);

        // Reorder the results by a custom field or title
        if ($orderby = ee()->TMPL->fetch_param('orderby'))
        {
            // ee()->publisher_log->to_template();

            $sort = ee()->TMPL->fetch_param('sort', 'desc');

            $results = ee()->publisher_entry->order_query_result($entry_ids, $results, $orderby, $sort);
        }

        return $results;
    }

    /**
     * Modify the initial search string to search the Publisher tables instead.
     * It will be saved to EE's cache, which the result_query hook below
     * will be called so the cached query is properly formed.
     *
     * @param  string $sql
     * @param  string $hash Unique ID of the search parameters
     * @return string
     */
    public function channel_search_modify_search_query($sql, $hash)
    {
        if (ee()->extensions->last_call)
        {
            $sql = ee()->extensions->last_call;
        }

        // Stop mod.search.php from continuing so this query is saved to the db.
        ee()->extensions->end_script = TRUE;

        ee()->load->library('Publisher/Publisher_search');
        return ee()->publisher_search->modify_search_query($sql, $hash);
    }

    /**
     * Make sure the resulting query string is updated to search the Publisher tables.
     *
     * @param  string $sql
     * @param  string $hash Unique ID of the search parameters
     * @return string
     */
    public function channel_search_modify_result_query($sql, $hash)
    {
        if (ee()->extensions->last_call)
        {
            $sql = ee()->extensions->last_call;
        }

        ee()->load->library('Publisher/Publisher_search');
        return ee()->publisher_search->modify_result_query($sql, $hash);
    }

    /**
     * publish_form_channel_preferences
     *
     * @param
     * @return
     */
    public function publish_form_entry_data($data)
    {
        if (ee()->extensions->last_call)
        {
            $data = ee()->extensions->last_call;
        }

        $channel_id = isset($data['channel_id']) ? $data['channel_id'] : FALSE;

        if (ee()->publisher_model->is_ignored_channel($channel_id))
        {
            return $data;
        }

        $data['category'] = ee()->publisher_category->get_category_posts($data['entry_id']);

        // Make sure we have the translated/draft URL Title value
        if ($url_title = ee()->publisher_site_pages->get_url_title($data['entry_id']))
        {
            $data['url_title'] = $url_title;
        }

        return $data;
    }

    /**
     * publish_form_channel_preferences
     *
     * @param
     * @return
     */
    public function publish_form_channel_preferences($data)
    {
        $channel_id = isset($data['channel_id']) ? $data['channel_id'] : FALSE;

        if ($channel_id AND ee()->publisher_model->is_ignored_channel($channel_id))
        {
            return $data;
        }

        // TESTing
        // $data['enable_versioning'] = 'n';

        $data['entry_id'] = ee()->input->get('entry_id');

        $vars = ee()->publisher_helper->get_toolbar_options('entry', $data);

        $toolbar = str_replace("\n", "", ee()->load->view('toolbar', $vars, TRUE));

        $script = '
        if ($(".publishPageContents form").length == 1)
        {
            $(function(){
                $(".publishPageContents form").prepend(\''. $toolbar .'\').publisherToolbar();
            });
        }
        ';

        if (ee()->publisher_lib->is_diff_enabled)
        {
            ee()->load->library('Publisher/Publisher_diff');

            $open = ee()->publisher_entry->get($data['entry_id'], PUBLISHER_STATUS_OPEN);
            $draft = ee()->publisher_entry->get($data['entry_id'], PUBLISHER_STATUS_DRAFT);

            $diff = json_encode(ee()->publisher_diff->get_entry_diff($data['entry_id'], $open, $draft));

            $script .= 'Publisher.show_diffs('. $diff .');';
        }

        // If we have a translated/draft value, set it.
        // This is kind of hack.
        if (ee()->publisher_setting->url_translations())
        {
            if ($url_title = ee()->publisher_site_pages->get_page_url($data['entry_id']))
            {
                $script .= '
                if ($("[name=structure__uri]").length == 1) {
                    $("[name=structure__uri]").val("'. $url_title .'");
                }
                if ($("[name=pages__pages_uri]").length == 1) {
                    $("[name=pages__pages_uri]").val("'. $url_title .'");
                }
                if ($("[name=better_pages_url]").length == 1) {
                    $("[name=better_pages_url]").val("'. $url_title .'");
                }
                ';
            }
        }
        // If its not enabled, then always get the default value. Its possible to save a translated
        // value to the DB, but upon editing the entry again it will display the default.
        else
        {
            if ($url_title = ee()->publisher_site_pages->get_page_url($data['entry_id'], ee()->publisher_lib->default_lang_id))
            {
                $script .= '
                if ($("[name=structure__uri]").length == 1) {
                    $("[name=structure__uri]").val("'. $url_title .'");
                }
                if ($("[name=pages__pages_uri]").length == 1) {
                    $("[name=pages__pages_uri]").val("'. $url_title .'");
                }
                if ($("[name=better_pages_url]").length == 1) {
                    $("[name=better_pages_url]").val("'. $url_title .'");
                }
                ';
            }
        }

        if (
            !ee()->publisher_lib->is_default_language AND
            ee()->publisher_setting->persistent_relationships()
        ){
            ee()->cp->add_to_head('<link href="'. ee()->publisher_helper->get_theme_url() .'publisher/styles/disabled-relationships.css" rel="stylesheet" />');

            $script .= 'Publisher.disable_relationships = true;';
        }

        // If not editing the default language, don't let users add or remove Matrix rows. They should be added or removed
        // in the default language only, otherwise if someone deletes a row, or all the rows, in a translated entry the
        // rows in the default language do not show up at all. Sticking to the single-tree translation model, a translated
        // entry would have the same content, just translated anyway. User's can change the order of rows though.
        if (
            !ee()->publisher_lib->is_default_language AND
            ee()->publisher_setting->persistent_matrix() AND
            array_key_exists('matrix', ee()->addons->get_installed('fieldtypes'))
        ){
            $script .= 'Publisher.disable_matrix = true;';
        }

        ee()->cp->add_to_foot('<script type="text/javascript">$(function(){'. preg_replace("/\s+/", " ", $script) .'});</script>');

        // If P&T Pill is installed its JS and CSS will be loaded.
        ee()->publisher_helper->load_pill_assets();

        ee()->cp->add_to_head('<link href="'. ee()->publisher_helper->get_theme_url() .'publisher/styles/toolbar.css" rel="stylesheet" />');

        ee()->cp->add_to_foot('<script type="text/javascript" src="'. ee()->publisher_helper->get_theme_url() .'publisher/scripts/publisher.js"></script>');

        return $data;
    }

    /**
     * Delete one or multiple entries
     * @return void
     */
    public function delete_entries_start()
    {
        if (ee()->input->post('delete') AND ee()->publisher_setting->delete_publisher_data())
        {
            ee()->db->where_in('entry_id', ee()->input->post('delete'))->delete('publisher_titles');
            ee()->db->where_in('entry_id', ee()->input->post('delete'))->delete('publisher_data');

            // Let fieldtypes do some cleanup
            ee()->publisher_lib->call('delete_entries_start', array(
                'entry_id' => ee()->input->post('delete')
            ));
        }

        foreach (ee()->input->post('delete') as $entry_id)
        {
            ee()->publisher_site_pages->delete($entry_id);
            ee()->publisher_approval->delete($entry_id, 'entry');
        }

        // Bust our Cache
        ee()->publisher_cache->driver->delete();
    }

    /**
     * delete_entries_loop
     * @param
     * @return
     */
    public function delete_entries_loop($entry_id, $channel_id)
    {
        if (ee()->publisher_setting->delete_publisher_data())
        {
            $data = array(
                'entry_id' => $entry_id,
                'channel_id' => $channel_id
            );

            ee()->db->where($data)->delete('publisher_titles');
            ee()->db->where($data)->delete('publisher_data');

            // Let fieldtypes do some cleanup
            ee()->publisher_lib->call('delete_entries_loop', $data);
        }

        ee()->publisher_site_pages->delete($entry_id);
        ee()->publisher_approval->delete($entry_id, 'entry');

        // Bust our Cache
        ee()->publisher_cache->driver->delete();
    }

    /**
     * Oh yes, going down that road...
     * @return string
     */
    public function channel_module_categories_start()
    {
        ee()->load->library('Publisher/Publisher_channel_categories');
        return ee()->publisher_channel_categories->categories();
    }

    /**
     * Oh yes, going down that road...
     * @return string
     */
    public function channel_module_category_heading_start()
    {
        ee()->load->library('Publisher/Publisher_channel_categories');
        return ee()->publisher_channel_categories->category_heading();
    }

    /**
     * Take translated URLs and try to find a matching default
     * language group/template so EE knows what to load.
     *
     * @param  string $uri_string
     * @return array
     */
    public function core_template_route($uri_string)
    {
        $last_call = NULL;
        $site_404  = ee()->config->item('site_404');
        $strict_urls = ee()->config->item('strict_urls');
        $segment_1 = ee()->uri->segment(1);

        if (ee()->extensions->last_call)
        {
            $uri_string = ee()->extensions->last_call;
            $last_call = $uri_string;
        }

        // Don't muck with the URI or templates
        if (PUBLISHER_LITE && $last_call)
        {
            return $last_call;
        }

        if (PUBLISHER_LITE)
        {
            return;
        }

        // Did another extension already return something?
        // Only valid response from another extension is a
        // template_group/template_name array. Update $parts
        // and reset $uri_string to... a string.
        if (is_array($uri_string))
        {
            $parts = $uri_string;
            $uri_string = implode('/', $parts);
        }
        else
        {
            $parts = explode('/', $uri_string);
        }

        // If its a form post make sure the uri is correct. safecracker_entry_form_tagdata_end()
        // replaces all form actions and hidden vars with correct segments so inline errors work.
        if (isset($_POST) && isset($parts[0]) && in_array($parts[0], ee()->publisher_model->language_codes))
        {
            array_shift($parts);

            $uri_string = implode('/', $parts);
            ee()->uri->uri_string = $uri_string;

            // Re-index them so it starts at 1
            $new_segments = array();
            $i = 1;

            foreach($parts as $part)
            {
                $new_segments[$i] = $part;
                $i++;
            }

            ee()->uri->segments = $new_segments;
        }

        // If a valid translated URL is found, then the persistent entries lookup below won't run.
        // This also does not get run for Structure/Pages requests. Structure finds the template
        // itself, even if we're on a translated page, b/c site_pages is updated by Publisher
        // prior to Structure doing its lookup.
        if (ee()->publisher_setting->url_translations())
        {
            // Load the translated routes if EE 2.8+
            ee()->publisher_template->load_routes();

            // See if there is a translated page for the current URL
            // $related = ee()->publisher_site_pages->find_related(ee()->publisher_session->get_current_url());

            $related = ee()->publisher_site_pages->find_related(
                ee()->publisher_session->get_current_url(),
                ee()->publisher_lib->lang_id,
                ee()->publisher_lib->default_lang_id
            );

            // If not, then we're using template_group/template pattern
            if ( !empty($parts) && !$related)
            {
                // Get the default template_group/template for a translated version
                // so EE knows which templates to actually load.
                $segments = ee()->publisher_template->get_default_segments($parts, TRUE, TRUE, FALSE);

                // Try to set the canonical URL if we're using translated URLs
                ee()->publisher_session->set_canonical_url($uri_string, $segments);

                // Is it a native search result page?
                if (ee()->publisher_helper_url->is_search_url($segments))
                {
                    // Because ee()->TMPL->log isn't available yet :/
                    ee()->publisher_log->message(ee()->uri->query_string, 'Search results page. Reset ee()->uri->query_string.');
                    ee()->uri->query_string = ee()->publisher_helper_url->is_search_url($segments, TRUE);
                }

                // If we have a template group and template send it back to EE
                // so it loads the correct/default template based off the translated
                // values found from get_default_segments().
                if ($segments[0] && $segments[1])
                {
                    // Stop all the things, including Structure
                    ee()->extensions->end_script = TRUE;
                    return array($segments[0], $segments[1]);
                }
            }
            // Its a site_page
            elseif ( !empty($parts) && $related)
            {
                $segments = explode('/', ee()->publisher_helper_url->remove_site_index($related));

                // // Try to set the canonical URL if we're using translated URLs
                ee()->publisher_session->set_canonical_url($uri_string, $segments);

                // Set {publisher:segment_x} variables. If using translated URLs
                // publisher_template->get_default_segments will call this.
                ee()->publisher_helper_url->set_publisher_segments($segments);
            }
        }
        else
        {
            // Set {publisher:segment_x} variables. If using translated URLs
            // publisher_template->get_default_segments will call this.
            ee()->publisher_helper_url->set_publisher_segments(ee()->uri->segments);
        }

        // If persistent entries are turned off, and Pages or Structure is installed, its quite possible
        // that the entry we're looking for does not exist in the site_pages array b/c it is not in
        // the default language.
        if ($uri_string != '' && !ee()->publisher_setting->persistent_entries() && ee()->publisher_site_pages->is_installed())
        {
            $site_pages = ee()->publisher_site_pages->get();

            // Prevent redirect loops
            if ($this->is_404_redirect($uri_string))
            {
                return $last_call;
            }

            if ( !empty($site_pages))
            {
                $match_uri = '/'.trim($uri_string, '/'); // will result in '/' if uri_string is blank
                $page_uris = $site_pages[ee()->publisher_lib->site_id]['uris'];

                // trim page uris in case there's a trailing slash on any of them
                foreach ($page_uris as $index => $value)
                {
                    $page_uris[$index] = '/'.trim($value, '/');
                }

                // case insensitive URI comparison
                $entry_id = array_search(strtolower($match_uri), array_map('strtolower', $page_uris));

                if ( !$entry_id && $match_uri != '/')
                {
                    $entry_id = array_search($match_uri.'/', $page_uris);
                }

                // Not a page? Return so it uses the template it was already trying to use.
                if ($entry_id === FALSE)
                {
                    return $last_call;
                }

                // Make sure this is set b/c its bypassed in libraries/Core.php->generate_page()
                ee()->uri->page_query_string = $entry_id;

                // If the requested entry has a translation, show the proper template
                if (ee()->publisher_entry->has_translation($entry_id))
                {
                    $template_id = $site_pages[ee()->publisher_lib->site_id]['templates'][$entry_id];

                    // Get the template_group/template path value
                    $template_path = ee()->publisher_template->get_template_path($template_id);

                    if ($template_path)
                    {
                        return explode('/', $template_path);
                    }
                }
                // Otherwise throw a 404, but only if strict urls is turned on
                elseif (ee()->publisher_setting->persistent_entries_show_404() && $strict_urls == 'y')
                {
                    $template = explode('/', $site_404);

                    if (isset($template[1]))
                    {
                        return $template;
                    }
                    else
                    {
                        $this->throw_404();
                    }
                }
            }
        }

        // If we got this far, return whatever another add-on, e.g. Structure, might want to return.
        if (ee()->extensions->last_call)
        {
            return ee()->extensions->last_call;
        }
    }

    /**
     * If strict urls are enabled, we have a defined 404 template,
     * and its not the default language, redirect to the 404 template.
     *
     * @return void
     */
    public function throw_404()
    {
        $site_404  = ee()->config->item('site_404');
        $strict_urls = ee()->config->item('strict_urls');
        $segment_1 = ee()->uri->segment(1);

        if ($site_404 && $strict_urls == 'y' && $segment_1 != '404' && !ee()->publisher_lib->is_default_mode)
        {
            ee()->functions->redirect(ee()->functions->create_url(ee()->functions->extract_path("=404")));
        }
    }

    /**
     * See if we got redirected to the 404 page.
     *
     * @param  string  $uri_string
     * @return boolean
     */
    public function is_404_redirect($uri_string)
    {
        $site_404  = ee()->config->item('site_404');
        $segment_1 = ee()->uri->segment(1);

        if ($segment_1 == '404' || ($site_404 != '' && strpos($uri_string, $site_404) !== FALSE))
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Parse some path variables in each Entries loop
     *
     * @param  string $tagdata
     * @param  array $row
     * @param  object $channel
     * @return string
     */
    public function channel_entries_tagdata($tagdata, $row, $channel)
    {
        if (ee()->extensions->last_call)
        {
            $tagdata = ee()->extensions->last_call;
        }

        // Make sure this is only called on PAGE requests. If session var is set then its coming through
        // the template parser via Publisher_diff in the CP.
        if (ee()->publisher_lib->is_diff_enabled AND !isset(ee()->session->cache['publisher']['entry_diff']))
        {
            ee()->load->library('Publisher/Publisher_diff');
            $tagdata = ee()->publisher_diff->prepare($tagdata, $row);
        }

        ee()->load->library('Publisher/Publisher_parser');
        return ee()->publisher_parser->replace_path_variables($tagdata, $row);
    }

    /**
     * Parse the tagdata at the end of each entry loop
     *
     * @param  string $tagdata
     * @param  array  $row
     * @return string
     */
    public function channel_entries_tagdata_end($tagdata, $row)
    {
        // if (isset($row['publisher_lang_id']) && isset($row['publisher_status']))
        // {
            // $tagdata = str_replace(LD.'publisher_lang_id'.RD, $row['publisher_lang_id'], $tagdata);
            // $tagdata = str_replace(LD.'publisher_status'.RD, $row['publisher_status'], $tagdata);
        // }

        return $tagdata;
    }

    /**
     * Fix root_url vars, and parse the diff if enabled.
     *
     * @param  string $final_template
     * @param  boolean $sub
     * @param  integer $site_id
     * @return string
     */
    public function template_post_parse($final_template, $sub, $site_id)
    {
        if (ee()->extensions->last_call)
        {
            $final_template = ee()->extensions->last_call;
        }

        // If root_url is used in an entry, it won't get replaced early. Go back and fix any potential stragglers.
        $final_template = str_replace(LD.'root_url'.RD, ee()->config->_global_vars['root_url'], $final_template);

        ee()->load->library('Publisher/Publisher_parser');
        $final_template = ee()->publisher_parser->replace_path_variables($final_template);
        $final_template = ee()->publisher_parser->replace_form_variables($final_template);

        if (ee()->publisher_site_pages->is_installed('structure'))
        {
            $final_template = ee()->publisher_site_pages->replace_structure_variables($final_template);
        }

        // Make sure this is only called on PAGE requests. If session var is set then its coming through
        // the template parser via Publisher_diff in the CP.
        if (ee()->publisher_lib->is_diff_enabled AND !isset(ee()->session->cache['publisher']['entry_diff']))
        {
            ee()->load->library('Publisher/Publisher_diff');
            $final_template = ee()->publisher_diff->parse($final_template);
        }

        if (PUBLISHER_DEBUG)
        {
            $final_template = str_replace('</body>', ee()->publisher_log->get_output().'</body>', $final_template);
        }

        return $final_template;
    }

    public function cp_menu_array($menu)
    {
        if (ee()->extensions->last_call !== FALSE)
        {
            $menu = ee()->extensions->last_call;
        }

        $menu_groups = ee()->publisher_setting->show_publisher_menu();
        $group_id    = ee()->session->userdata['group_id'];

        // Which member groups can see the menu?
        if ($group_id != 1 && !in_array($group_id, $menu_groups))
        {
            return $menu;
        }

        ee()->lang->loadfile('publisher');

        $m = array();

        if (PUBLISHER_LITE === FALSE) $m['publisher_phrases'] = ee()->publisher_helper_cp->mod_link('phrases');
        $m['publisher_categories'] = ee()->publisher_helper_cp->mod_link('categories');

        if (ee()->session->userdata['group_id'] == 1)
        {
            $m[] = '----';
            if (PUBLISHER_LITE === FALSE) $m['publisher_templates'] = ee()->publisher_helper_cp->mod_link('templates');
            $m['publisher_previews']  = ee()->publisher_helper_cp->mod_link('previews');
            if (PUBLISHER_LITE === FALSE) $m['publisher_languages'] = ee()->publisher_helper_cp->mod_link('languages');
            $m['publisher_settings']  = ee()->publisher_helper_cp->mod_link('settings');
        }

        $m[] = '----';
        $m['overview'] = ee()->publisher_helper_cp->base_url;

        if ($group_id == 1)
        {
            $m['publisher_support']  = ee()->publisher_helper_cp->mod_link('support');
        }

        $menu['publisher'] = $m;

        return $menu;
    }

    public function cp_js_end()
    {
        $script = '';

        // If another extension shares the same hook
        if (ee()->extensions->last_call !== FALSE)
        {
            $script = ee()->extensions->last_call;
        }

        $qry = ee()->db->get('publisher_languages');

        if ($qry->num_rows() == 1)
        {
            return $script;
        }

        $script .= '

        $(".mainTable").each(function(){

            var $table = $(this);

            if ($table.closest("form").attr("id") != "entries_form")
            {
                return;
            }

            // Initial data on page load
            var config_data = $table.data("table_config");
            var entry_ids = [];

            var set_publisher_states = function(entry_ids)
            {
                if (entry_ids.length == 0)
                {
                    return;
                }

                $.ajax({
                    type: "GET",
                    url: EE.publisher.ajax_get_translation_status,
                    data: {type: "entry", id: entry_ids +""},
                    success: function (data, status, xhr)
                    {
                        var data = $.parseJSON(data);

                        $table.find("tbody tr").each(function(){
                            var $tr = $(this);
                            var $td = $tr.find("td:first-child");
                            var row_entry_id = $td.text();
                            var $span = $(this).find("span[class^=status]");

                            $span.after(data[row_entry_id]);
                        });
                    }
                });

                $.ajax({
                    type: "GET",
                    url: EE.publisher.ajax_get_entry_status,
                    data: {type: "entry", id: entry_ids +""},
                    success: function (data, status, xhr)
                    {
                        var data = $.parseJSON(data);

                        $table.find("tbody tr").each(function(){
                            var $tr = $(this);
                            var $td = $tr.find("td:first-child");
                            var row_entry_id = $td.text();
                            var $span = $(this).find("span[class^=status]");

                            if (data[row_entry_id] == "y")
                            {
                                $tr.addClass("publisher-draft");
                            }

                        });
                    }
                });
            }

            for (row in config_data.rows)
            {
                entry_ids.push(config_data.rows[row].entry_id);
            }

            set_publisher_states(entry_ids);

            // When the ajax filter is initiated
            $table.ajaxSuccess(function(e, xhr, settings){
                url = settings.url;
                var regex = /(C=content_edit)/g;

                if(regex.test(url))
                {
                    var data = jQuery.parseJSON(xhr.responseText);
                    var entry_ids = [];

                    for (row in data.rows)
                    {
                        entry_ids.push(data.rows[row].entry_id);
                    }

                    set_publisher_states(entry_ids);
                }
            });
        });
        ';

        return '$(function(){'. $script .'});';
    }

    public function entry_submission_redirect($entry_id, $meta, $data, $cp_call, $orig_loc)
    {
        return $orig_loc;
    }

    /**
     * Handled in upd.publisher.php
     */
    public function disable_extension(){}
    public function update_extension($current = ''){}
    public function activate_extension() {}

    private function debug()
    {
        if ( !$this->debug || REQ == 'CP') return;

        // ee()->load->library('Publisher/Publisher_searchable');
        // ee()->publisher_searchable->save_entry(53, 36, 'channel_grid_field_36');
        // ee()->publisher_searchable->save_entry(53, 36, 'channel_grid_field_36');
        // ee()->publisher_searchable->save_entry(20, 5, 'matrix_data');

        if (REQ == 'PAGE') {
            $publisher_site_pages = ee()->publisher_site_pages->get_all();
            echo '<pre>'; var_dump($publisher_site_pages); die;
        }

        // $pages = array(
        //     1 => '/',
        //     9 => '/products',
        //     13 => '/german-only-page-1',
        //     19 => '/newcastle-关于',
        //     22 => '/stoke',
        //     20 => '/everton',
        //     23 => '/liverpool',
        //     24 => '/fulham',
        //     25 => '/wigan',
        //     26 => '/city',
        // );

        // // eng to chinese
        // ee()->publisher_lib->prev_lang_id = 1;
        // $u = ee()->publisher_helper_url->get_translated_url('http://ee255.dev/en/newcastle', 1, 6);
        // var_dump($u);

        // var_dump(array_search('/newcastle-关于', $pages));

        // // chinese to eng with encoded url
        // ee()->publisher_lib->prev_lang_id = 6;
        // $u = ee()->publisher_helper_url->get_translated_url('http://ee255.dev/zh/newcastle-%E5%85%B3%E4%BA%8E', 6, 1);
        // var_dump($u);

        // // chinese to eng with decoded url
        // ee()->publisher_lib->prev_lang_id = 6;
        // $u = ee()->publisher_helper_url->get_translated_url('http://ee255.dev/zh/newcastle-关于', 6, 1);
        // var_dump($u);

        // $u = ee()->publisher_entry->create_url_title('alpha');
        // $u2 = ee()->publisher_entry->create_url_title('alpha-1');
        // $u3 = ee()->publisher_entry->create_url_title('alpha-2');
        // var_dump($u, $u2, $u3);

        // $u = ee()->publisher_entry->create_url_title('alpha', TRUE);
        // $u2 = ee()->publisher_entry->create_url_title('alpha-1', TRUE);
        // $u3 = ee()->publisher_entry->create_url_title('alpha-2', TRUE);
        // var_dump($u, $u2, $u3); die;

        // ee()->publisher_lib->prev_lang_id = 1;
        // $u = ee()->publisher_helper_url->get_alternate_url(NULL, 1, 3);
        // var_dump($u);

        // ee()->publisher_lib->prev_lang_id = 1;
        // $u = ee()->publisher_helper_url->get_alternate_url(NULL, 1, 2);
        // var_dump($u);

        // ee()->publisher_lib->prev_lang_id = 2;
        // $u = ee()->publisher_helper_url->get_alternate_url(NULL, 2, 1);
        // var_dump($u);

        // ee()->publisher_lib->prev_lang_id = 2;
        // $u = ee()->publisher_helper_url->get_alternate_url(NULL, 2, 3);
        // var_dump($u);

        // ee()->publisher_lib->prev_lang_id = 3;
        // $u = ee()->publisher_helper_url->get_alternate_url(NULL, 3, 1);
        // var_dump($u);

        // ee()->publisher_lib->prev_lang_id = 1;
        // $u = ee()->publisher_helper_url->get_alternate_url('http://ee255.dev/en/layouts/products/', 1, 2);
        // var_dump($u);

        // ee()->publisher_lib->prev_lang_id = 1;
        // $s = ee()->publisher_template->get_translated_segments(array('layouts', 'products'), 1, 2);
        // var_dump($s);
    }


    /* ===========================================================
        Matrix Support
    ============================================================ */

    public function matrix_data_query($matrix, $params, $sql, $select_mode)
    {
        return ee()->publisher_matrix_hooks->matrix_data_query($matrix, $params, $sql, $select_mode);
    }

    public function matrix_save_row($matrix, $data)
    {
        return ee()->publisher_matrix_hooks->matrix_save_row($matrix, $data);
    }

    /* ===========================================================
        Playa Support
    ============================================================ */

    public function playa_fetch_rels_query($playa, $sql, $where)
    {
        return ee()->publisher_playa_hooks->playa_fetch_rels_query($playa, $sql, $where);
    }

    public function playa_field_selections_query($playa, $where)
    {
        return ee()->publisher_playa_hooks->playa_field_selections_query($playa, $where);
    }

    public function playa_save_rels($playa, $selections, $data)
    {
        return ee()->publisher_playa_hooks->playa_save_rels($playa, $selections, $data);
    }

    /* ===========================================================
        Assets Support
    ============================================================ */

    public function assets_field_selections_query($assets, $sql)
    {
        return ee()->publisher_assets_hooks->assets_field_selections_query($assets, $sql);
    }

    public function assets_save_row($assets, $data)
    {
        return ee()->publisher_assets_hooks->assets_save_row($assets, $data);
    }

    public function assets_data_query($assets, $sql)
    {
        return ee()->publisher_assets_hooks->assets_data_query($assets, $sql);
    }

    /* ===========================================================
        Structure Support
    ============================================================ */

    public function structure_get_data_end($data)
    {
        return ee()->publisher_structure_hooks->structure_get_data_end($data);
    }

    public function structure_reorder_end($data, $site_pages)
    {
        ee()->publisher_structure_hooks->structure_reorder_end($data, $site_pages);
    }

    public function structure_create_custom_titles($custom_titles)
    {
        return ee()->publisher_structure_hooks->structure_create_custom_titles($custom_titles);
    }

    public function structure_get_overview_title($sql, $entry_id)
    {
        return ee()->publisher_structure_hooks->structure_get_overview_title($sql, $entry_id);
    }

    public function structure_get_selective_data_results($results)
    {
        return ee()->publisher_structure_hooks->structure_get_selective_data_results($results);
    }

    /* ===========================================================
        Zenbu
    ============================================================ */

    public function zenbu_modify_status_display($status, $entry, $row, $statuses)
    {
        return ee()->publisher_zenbu_hooks->zenbu_modify_status_display($status, $entry, $row, $statuses);
    }

    /* ===========================================================
        New Relationships/Zero Wing Support
    ============================================================ */

    public function relationships_query($type, $entry_ids, $depths, $sql)
    {
        return ee()->publisher_relationship_hooks->relationships_query($type, $entry_ids, $depths, $sql);
    }

    public function relationships_display_field($entry_id, $field_id, $sql)
    {
        return ee()->publisher_relationship_hooks->relationships_display_field($entry_id, $field_id, $sql);
    }

    public function relationships_post_save($relationships, $entry_id, $field_id)
    {
        return ee()->publisher_relationship_hooks->relationships_post_save($relationships, $entry_id, $field_id);
    }

    public function relationships_modify_rows($rows, $node)
    {
        return ee()->publisher_relationship_hooks->relationships_modify_rows($rows, $node);
    }

    /* ===========================================================
        Grid Support
    ============================================================ */

    public function grid_query($entry_ids, $field_id, $content_type, $table_name, $sql)
    {
        return ee()->publisher_grid_hooks->grid_query($entry_ids, $field_id, $content_type, $table_name, $sql);
    }

    public function grid_save($entry_id, $field_id, $content_type, $table_name, $data)
    {
        return ee()->publisher_grid_hooks->grid_save($entry_id, $field_id, $content_type, $table_name, $data);
    }

    /**
     * Load classes to handle 3rd party hooks
     *
     * @param  array  $hooks
     * @return void
     */
    private function _register_hooks($hooks = array())
    {
        if (empty($hooks))
            return;

        ee()->load->library('Publisher/hooks/Publisher_hooks_base');

        foreach ($hooks as $hook)
        {
            ee()->load->library('Publisher/hooks/Publisher_'. $hook .'_hooks');
        }
    }
}

define('PUBLISHER_LITE', FALSE);

/* End of file ext.publisher.php */
/* Location: /system/expressionengine/third_party/publisher/ext.publisher.php */