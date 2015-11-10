<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use Store\Model\Config;
use Store\Model\Country;
use Store\Model\Email;
use Store\Model\State;
use Store\Model\Status;

class InstallService extends AbstractService
{
    public function install_templates($site_id)
    {
        $site_id = (int) $site_id;

        // ensure the example template group does not already exist
        $this->ee->db->from('template_groups')
            ->where('site_id', $site_id)
            ->where('group_name', 'store_example');

        if ($this->ee->db->count_all_results() > 0) {
            return;
        }

        // create the example template group
        $this->ee->db->insert('template_groups', array(
            'group_name' => 'store_example',
            'group_order' => $this->ee->db->count_all('template_groups') + 1,
            'is_site_default' => 'n',
            'site_id' => $site_id,
        ));
        $group_id = $this->ee->db->insert_id();

        $templates_dir = PATH_THIRD.'store/templates/';
        foreach (scandir($templates_dir) as $file_name) {
            if (substr($file_name, -4) == '.css') {
                $template_name = substr($file_name, 0, -4);
                $template_type = 'css';
            } elseif (substr($file_name, -5) == '.html') {
                $template_name = substr($file_name, 0, -5);
                $template_type = 'webpage';
            } else {
                continue;
            }

            $template_data = file_get_contents($templates_dir.$file_name);
            $data = array(
                'group_id'              => $group_id,
                'template_name'         => $template_name,
                'template_notes'        => '',
                'cache'                 => 'n',
                'refresh'               => 0,
                'no_auth_bounce'        => '',
                'php_parse_location'    => 'o',
                'allow_php'             => 'n',
                'template_type'         => $template_type,
                'template_data'         => $template_data,
                'edit_date'             => time(),
                'site_id'               => $site_id
             );

            $this->ee->db->insert('templates', $data);
        }
    }

    public function install_site($site_id)
    {
        $site_id = (int) $site_id;

        if (Config::where('site_id', $site_id)->count() > 0) {
            // trying to install a site which already exists...
            return false;
        }

        // view path isn't already set inside install wizard
        $this->ee->load->add_package_path(PATH_THIRD.'store/', false);

        // install default countries
        $default_countries = require(PATH_THIRD.'store/data/countries.php');
        $insert_countries = array();
        foreach ($default_countries as $code => $name) {
            $insert_countries[] = array(
                'site_id' => $site_id,
                'code' => $code,
                'name' => $name,
                'enabled' => 1,
            );
        }
        Country::where('site_id', $site_id)->delete();
        Country::insert($insert_countries);

        // install default states
        $default_states = require(PATH_THIRD.'store/data/states.php');
        $insert_states = array();
        foreach ($default_states as $country_code => $states) {
            if ($country = Country::where('code', $country_code)->first()) {
                foreach ($states as $code => $name) {
                    $insert_states[] = array(
                        'site_id' => $site_id,
                        'country_id' => $country->id,
                        'code' => $code,
                        'name' => $name,
                    );
                }
            }
        }
        State::where('site_id', $site_id)->delete();
        State::insert($insert_states);

        // add default email template
        Email::where('site_id', $site_id)->delete();
        $email = new Email;
        $email->site_id = $site_id;
        $email->to = '{order_email}';
        $email->name = $this->ee->lang->line('store.email.order_confirmation');
        $email->subject = $this->ee->lang->line('store.email.order_confirmation');
        $email->contents = $this->ee->load->view('emails/confirmation', null, true);
        $email->mail_format = 'text';
        $email->word_wrap = 1;
        $email->enabled = 1;
        $email->save();

        // add default order status and link to order confirmation email template
        Status::where('site_id', $site_id)->delete();
        $status = new Status;
        $status->site_id = $site_id;
        $status->name = 'new';
        $status->color = '';
        $status->email_ids = $email->id;
        $status->is_default = 1;
        $status->save();

        // install default settings
        foreach ($this->ee->store->config->settings as $key => $default) {
            $row = new Config;
            $row->site_id = $site_id;
            $row->preference = $key;
            $row->value = store_setting_default($default);
            $row->save();
        }

        // cleanup
        $this->ee->load->remove_package_path(PATH_THIRD.'store/');

        return true;
    }
}
