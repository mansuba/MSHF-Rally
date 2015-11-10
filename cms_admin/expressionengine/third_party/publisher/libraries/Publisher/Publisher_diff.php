<?php

/**
 * ExpressionEngine Publisher Diff Class
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

class Publisher_diff {

    /**
     * Take some tag data, a snippet, custom field, or $tagdata
     * from the entries_tagdata hooks. Save the full tags to cache variable
     * to be used in the parse() method.
     *
     * @param  string $tagdata
     * @param  array $row
     * @return string
     */
    public function prepare($tagdata, $row, $field = '')
    {
        $entry_id = $row['entry_id'];

        // Grab all statuses in this channel to make sure the entry is found at all.
        // There is no way to know which status devs are using in their tags,
        // so just use them all, it really doesn't matter in this case.
        $channel_id = isset($row['channel_id']) ? $row['channel_id'] : ee()->input->get('channel_id');
        $statuses = $this->get_channel_statuses($channel_id);

        $open_str  = '{exp:channel:entries publisher_diff_status="open" status="'. $statuses .'" entry_id="'. $entry_id .'"}'. $tagdata .'{/exp:channel:entries}';
        $draft_str = '{exp:channel:entries publisher_diff_status="draft" status="'. $statuses .'" entry_id="'. $entry_id .'"}'. $tagdata .'{/exp:channel:entries}';

        // Create a key to use
        $tag = $field != '' ? $field : md5($open_str);

        if ( !isset(ee()->session->cache['publisher']['tagdata']))
        {
            ee()->session->cache['publisher']['tagdata'] = array();
        }

        // Prevent recurssion
        if ( !array_key_exists('entry:'.$tag, ee()->session->cache['publisher']['tagdata']))
        {
            ee()->session->cache['publisher']['tagdata']['entry:'.$tag] = array(
                'draft' => $draft_str,
                'open'  => $open_str
            );

            // Create a token placeholder that we'll replace with the diff later.
            $tagdata = 'entry:'.$tag;
        }

        return $tagdata;
    }

    /**
     * Grab all custom statuses defined for a channel
     * @param  integer $channel_id
     * @return string
     */
    private function get_channel_statuses($channel_id)
    {
        $qry = ee()->db->select('s.status')
                ->from('channels AS c')
                ->join('statuses AS s', 's.group_id = c.status_group')
                ->where('c.channel_id', $channel_id)
                ->get();

        $statuses = array();

        if ( !$qry->num_rows())
        {
            return 'open|closed';
        }

        foreach ($qry->result() as $row)
        {
            $statuses[] = $row->status;
        }

        return implode('|', $statuses);
    }

    /**
     * Use the Template class to parse the $tagdata
     *
     * @param  string  $template Template or code snippet to parse
     * @param  string  $style    If passed, will be relayed to get_diff() method
     * @param  string  $field    Which publish field?
     * @return string            Final parsed template or snippet
     */
    public function parse($template, $style = FALSE, $field = FALSE)
    {
        // Check just incase.
        if ( !isset(ee()->session->cache['publisher']['tagdata']) OR
              empty(ee()->session->cache['publisher']['tagdata']))
        {
            return $template;
        }

        $update_keys = array();

        if ( !isset(ee()->TMPL))
        {
            ee()->load->library('template');
            ee()->TMPL = new EE_Template;
        }

        $original_TMPL = ee()->TMPL;

        foreach (array(PUBLISHER_STATUS_OPEN, PUBLISHER_STATUS_DRAFT) as $status)
        {
            foreach (ee()->session->cache['publisher']['tagdata'] as $tagdata_key => $tagdata_statuses)
            {
                if ($field AND $tagdata_key != 'entry:'.$field)
                {
                    continue;
                }

                $old_TMPL = ee()->TMPL;
                ee()->TMPL = new EE_Template;

                ee()->TMPL->template = '';
                ee()->TMPL->parse($tagdata_statuses[$status]);
                ee()->TMPL->template = ee()->TMPL->parse_globals(ee()->TMPL->template);

                // Save for now, replace later
                $update_keys[$tagdata_key][$status] = ee()->TMPL->final_template;

                ee()->TMPL = $old_TMPL;
            }
        }

        ee()->TMPL = $original_TMPL;

        foreach ($update_keys as $key => $data)
        {
            $open_data = isset($data[PUBLISHER_STATUS_OPEN]) ? $data[PUBLISHER_STATUS_OPEN] : '';
            $draft_data = isset($data[PUBLISHER_STATUS_DRAFT]) ? $data[PUBLISHER_STATUS_DRAFT] : '';

            $diff = $this->get_diff($open_data, $draft_data, $style);
            $template = str_replace($key, $diff, $template);
        }

        // Make sure image paths are correct.
        $template = ee()->publisher_helper->parse_file_path($template);

        return $template;
    }

    /**
     * Short and sweet, get the diff!
     *
     * @param  string $open   Old text content
     * @param  string $draft  New text content
     * @return string
     */
    public function get_diff($open, $draft, $style = FALSE)
    {
        if ($style == 'text' OR ee()->publisher_setting->diff_style() == 'text')
        {
            $open = strip_tags($open);
            $draft = strip_tags($draft);
        }

        // Make sure we only call this once per page request.
        if ( !isset(ee()->session->cache['publisher']['diff_driver']))
        {
            ee()->load->library('Publisher/Publisher_drivers');
            ee()->session->cache['publisher']['diff_driver'] = ee()->publisher_drivers->get_driver(ee()->publisher_setting->diff_driver());
        }

        $diff = ee()->session->cache['publisher']['diff_driver']->get_diff($open, $draft);

        // Make sure image paths are correct.
        $diff = ee()->publisher_helper->parse_file_path($diff);

        return $diff;
    }

    /**
     * Get and parse the diff for each field in an entry
     *
     * @param  integer $entry_id
     * @param  string $open
     * @param  string $draft
     * @return array
     */
    public function get_entry_diff($entry_id, $open, $draft)
    {
        $diffs = array();

        ee()->session->cache['publisher']['entry_diff'] = TRUE;

        if ($open AND $draft)
        {
            foreach ($open as $field => $value)
            {
                // Make sure its a title, or another field_id_N field.
                if (ee()->publisher_model->is_custom_field($field))
                {
                    $settings = $this->get_settings($field, FALSE);
                    $tagdata = $settings['use_template'];
                    $style = isset($settings['style']) ? $settings['style'] : ee()->publisher_setting->diff_style();

                    if ($settings['enabled'] == 'y' OR $field == 'title')
                    {
                        if ($tagdata)
                        {
                            $tagdata = $this->prepare($tagdata, array('entry_id' => $entry_id), $field);
                            $tagdata = $this->parse($tagdata, $style, $field);

                            // Only if we have a string/diff
                            if ($tagdata)
                            {
                                $diffs[$field] = $tagdata;
                            }
                        }
                        else
                        {
                            // Do we have an actual difference in the field values?
                            if ($open->$field != $draft->$field)
                            {
                                $diffs[$field] = $this->get_diff($open->$field, $draft->$field, $style);
                            }
                        }
                    }
                }
            }
        }

        return $diffs;
    }

    /**
     * Get custom field template setting
     *
     * @param  integer  $field_id
     * @param  boolean $return_all
     * @return array/string
     */
    public function get_settings($field_id)
    {
        $field_id = preg_replace('/field_id_(\d+)/', '$1', $field_id);

        $qry = ee()->db->where('field_id', $field_id)
                            ->get('publisher_diff_settings');

        if ($qry->num_rows() == 1)
        {
            $settings = $qry->row();
            $snippet_contents = FALSE;

            if ($settings->snippet_id)
            {
                $snippets = ee()->publisher_template->get_snippets(FALSE);

                if (array_key_exists($settings->snippet_id, $snippets))
                {
                    $snippet_contents = ee()->publisher_template->get_snippet_contents($settings->snippet_id);
                }
            }

            return array(
                'snippet'  => $settings->snippet_id,
                'template' => $settings->template_custom,
                'use_template' => ($snippet_contents ? $snippet_contents : $settings->template_custom),
                'enabled'  => $settings->enabled,
                'style'    => $settings->style
            );
        }

        return array(
            'snippet' => '',
            'template' => '',
            'use_template' => '',
            'enabled'  => '',
            'style' => ''
        );
    }

    /**
     * Save the settings
     *
     * @return void
     */
    public function save_field_setting()
    {
        $field_id = ee()->input->post('field_id');
        $snippet_id = ee()->input->post('publisher_diff_snippet_file');
        $template_custom = ee()->input->post('publisher_diff_template_custom');
        $enabled = ee()->input->post('publisher_diff_enabled') ? 'y' : 'n';
        $style = ee()->input->post('publisher_diff_style');

        if ($field_id)
        {
            $data = array(
                'field_id'          => $field_id,
                'snippet_id'        => $snippet_id,
                'template_custom'   => $template_custom,
                'enabled'           => $enabled,
                'style'             => $style
            );

            $where = array(
                'field_id' => $field_id
            );

            ee()->publisher_model->insert_or_update('publisher_diff_settings', $data, $where);
        }
    }

    /**
     * Generate the HTML needed to add the extra fields to the field settings page
     *
     * @return  void
     */
    public function add_diff_field()
    {
        $field_id = ee()->input->get_post('field_id');

        ee()->load->model('publisher_template');
        $snippets  = ee()->publisher_template->get_snippets();
        $style = array('full' => 'Full', 'text' => 'Text Only');

        $settings = $this->get_settings($field_id);

        $field ='<p><label for="diff_enabled">Enabled</label> '.form_checkbox('publisher_diff_enabled', 'y', ($settings['enabled'] == 'y' ? TRUE : FALSE), 'id=diff_enabled');
        $field .= '<p><label for="diff_style">Diff Style</label> '.form_dropdown('publisher_diff_style', $style, $settings['style'], 'id=diff_style').'</p>';
        $field .= '<p><label for="diff_snippet">Snippet</label> '.form_dropdown('publisher_diff_snippet_file', $snippets, $settings['snippet'], 'id=diff_snippet').'</p>';

        $field .= '<p>OR</p><p><label for="publisher_diff_template_custom">Custom Template</label>'.form_textarea(array(
            'name'      => 'publisher_diff_template_custom',
            'id'        => 'publisher_diff_template_custom',
            // Replace newlines with a temporary token so the insertion works
            'value'     => str_replace("\n", "[NL]", $settings['template']),
            'size'      => 20
        )).'</p>';

        $label = '<strong>Diff Settings</strong><br />When viewing a Draft version of an entry you can optionally display a diff of the Draft and Published version of the entry. If no Snippet or Custom Template is defined, a simple string comparison will be performed on the field values. Read more about <a href="http://boldminded.com/add-ons/publisher/diffs">Publisher Diffs</a>.';

        $script = '$(".mainTable:eq(0) tbody tr:last-child").after(\'<tr><td>'. $label .'</td><td>'. $field .'</td></tr>\');';

        // Replace [NL] with a newline character, this is the only way to get new lines into the textarea prior to
        // CodeMirror initating, otherwise we get illegal character warnings for the new lines.
        $script .= '$("#publisher_diff_template_custom").val( $("#publisher_diff_template_custom").val().replace(/\[NL\]/g, "\n") );';
        $script .= 'var myCodeMirror = CodeMirror.fromTextArea(publisher_diff_template_custom, { mode:  "htmlmixed", lineNumbers: true });';

        // Load all the CodeMirror assets
        ee()->cp->add_to_head('
            <link href="'. ee()->publisher_helper->get_theme_url() .'publisher/codemirror/lib/codemirror.css" rel="stylesheet" />
        ');

        ee()->cp->add_to_foot('
            <script src="'. ee()->publisher_helper->get_theme_url() .'publisher/codemirror/lib/codemirror.js"></script>
            <script src="'. ee()->publisher_helper->get_theme_url() .'publisher/codemirror/mode/javascript/javascript.js"></script>
            <script src="'. ee()->publisher_helper->get_theme_url() .'publisher/codemirror/mode/xml/xml.js"></script>
            <script src="'. ee()->publisher_helper->get_theme_url() .'publisher/codemirror/mode/css/css.js"></script>
            <script src="'. ee()->publisher_helper->get_theme_url() .'publisher/codemirror/mode/htmlmixed/htmlmixed.js"></script>
        ');

        ee()->javascript->output('$(function(){'. preg_replace("/\s+/", " ", $script) .'});');
        ee()->javascript->compile();
    }
}