<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use EE_Template;
use Store\Model\Email;
use Store\Model\Order;

class EmailService extends AbstractService
{
    protected $snippets;

    /**
     * Send an email
     */
    public function send(Email $email, Order $order)
    {
        $this->ee->load->library('email');
        $this->ee->load->helper('text');

        $tag_vars = array($order->toTagArray());

        $this->ee->email->EE_initialize();
        $this->ee->email->to($this->parse($email->to, $tag_vars));
        $this->ee->email->wordwrap = $email->word_wrap;
        $this->ee->email->mailtype = $email->mail_format;

        if (config_item('store_from_email')) {
            $this->ee->email->from(config_item('store_from_email'), config_item('store_from_name'));
        } else {
            $this->ee->email->from(config_item('webmaster_email'), config_item('webmaster_name'));
        }

        if ($email->bcc) {
            $this->ee->email->bcc($email->bcc);
        }

        $this->ee->email->subject($this->parse($email->subject, $tag_vars));
        $this->ee->email->message($this->parse_html($email->contents, $tag_vars, true));
        $this->ee->email->send();
    }

    /**
     * Parse a template and return as plain text string (no html entities)
     */
    public function parse($template, $tag_vars, $parse_embeds = false)
    {
        return html_entity_decode($this->parse_html($template, $tag_vars, $parse_embeds));
    }

    /**
     * Seriously weak
     */
    public function parse_html($template, $tag_vars, $parse_embeds = false)
    {
        $this->ee->load->library('template');

        // back up existing TMPL class
        $OLD_TMPL = isset($this->ee->TMPL) ? $this->ee->TMPL : null;
        $this->ee->TMPL = new EE_Template;

        // parse simple variables
        $template = $this->ee->TMPL->parse_variables($template, $tag_vars);

        if ($parse_embeds) {
            // extra weak
            if (null === $this->snippets) {
                $result = $this->ee->db->select('snippet_name, snippet_contents')
                    ->where('site_id', config_item('site_id'))
                    ->or_where('site_id', 0)
                    ->get('snippets')->result();

                $this->snippets = array();
                foreach ($result as $row) {
                    $this->snippets[$row->snippet_name] = $row->snippet_contents;
                }
            }

            $this->ee->config->_global_vars = array_merge($this->ee->config->_global_vars, $this->snippets);

            // parse as complete template (embeds, snippets, and globals)
            $this->ee->TMPL->parse($template);
            $template = $this->ee->TMPL->parse_globals($this->ee->TMPL->final_template);
        }

        // restore old TMPL class
        $this->ee->TMPL = $OLD_TMPL;

        return $template;
    }
}
