<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use DateTime;
use Ip_to_nation_data;

class StoreService extends AbstractService
{
    protected $_action_ids = array();

    public function secure_request()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) AND $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            return true;
        }
        if (empty($_SERVER['HTTPS']) OR strtolower($_SERVER['HTTPS']) == 'off') {
            return false;
        }

        return true;
    }

    public function is_store_ext_enabled()
    {
        return config_item('allow_extensions') == 'y';
    }

    public function is_store_ft_enabled()
    {
        // find enabled fieldtype rows
        $this->ee->db->from('fieldtypes');
        $this->ee->db->where('name', 'store');

        return $this->ee->db->count_all_results() > 0;
    }

    public function get_store_action_id($method)
    {
        $this->ee->db->where('class', 'Store');
        $this->ee->db->where('method', $method);
        $row = $this->ee->db->get('actions')->row_array();
        if (empty($row)) return false;
        else return (int) $row['action_id'];
    }

    /**
     * Get an array of channel ids which contain Store products.
     * Cached to avoid extra db queries.
     *
     * @return array
     */
    public function get_store_channels()
    {
        static $product_channels = NULL;

        if (is_null($product_channels)) {
            $query = $this->ee->db->distinct()
                ->select('channel_id')
                ->from('channels')
                ->join('channel_fields', 'channel_fields.group_id = channels.field_group')
                ->where('channel_fields.field_type', 'store')
                ->get()->result_array();

            $product_channels = array();
            foreach ($query as $row) {
                $product_channels[] = $row['channel_id'];
            }
        }

        return $product_channels;
    }

    public function get_entry_page_url($site_id, $entry_id)
    {
        $site_pages = config_item('site_pages');

        if ($site_pages !== false && isset($site_pages[$site_id]['uris'][$entry_id])) {
            return array(
                'page_uri' => $site_pages[$site_id]['uris'][$entry_id],
                'page_url' => $this->ee->functions->create_page_url($site_pages[$site_id]['url'], $site_pages[$site_id]['uris'][$entry_id])
            );
        } else {
            return array(
                'page_uri' => NULL,
                'page_url' => NULL,
            );
        }
    }

    /**
     * Find a file based on its public URL
     */
    public function get_file_by_url($file_url)
    {
        $upload_dir = $this->get_upload_dir_by_url(dirname($file_url).'/');
        if (!$upload_dir) {
            return false;
        }

        $file = $this->ee->db->where('upload_location_id', $upload_dir->id)
            ->where('file_name', basename($file_url))
            ->get('files')->row();

        return $file ?: false;
    }

    /**
     * Find an upload dir based on its public URL
     */
    public function get_upload_dir_by_url($dir_url)
    {
        foreach ($this->get_file_upload_preferences() as $dir) {
            if ($dir['url'] === $dir_url) {
                return (object) $dir;
            }
        }

        return false;
    }

    public function get_file_path($file_id)
    {
        $file = $this->ee->db->where('file_id', $file_id)->get('files')->row();
        if (empty($file)) {
            return false;
        }

        $upload_prefs = $this->get_file_upload_preferences();
        if (empty($upload_prefs[$file->upload_location_id])) {
            return false;
        }

        $path = $upload_prefs[$file->upload_location_id]['server_path'];

        // is this a relative path?
        if (strpos($path, '/') !== 0) {
            $path = APPPATH.'../'.$path;
        }

        return rtrim($path, '/').'/'.$file->file_name;
    }

    public function get_file_upload_preferences()
    {
        $this->ee->load->model('file_upload_preferences_model');

        return $this->ee->file_upload_preferences_model->get_file_upload_preferences();
    }

    public function get_action_id($method)
    {
        if (empty($this->_action_ids)) {
            $result = $this->ee->db->where('class', 'Store')
                ->get('actions')->result_array();

            foreach ($result as $row) {
                $this->_action_ids[$row['method']] = (int) $row['action_id'];
            }
        }

        return isset($this->_action_ids[$method]) ? $this->_action_ids[$method] : 0;
    }

    public function get_action_url($method)
    {
        $url = $this->ee->functions->fetch_site_index().QUERY_MARKER.
            'ACT='.$this->get_action_id($method);

        if ($this->secure_request()) {
            $url = str_ireplace('http://', 'https://', $url);
        }

        return $url;
    }

    public function create_url($path = false)
    {
        if ($path === false) {
            $url = $this->ee->functions->fetch_current_uri();
        } else {
            $url = $this->ee->functions->create_url($path);
        }

        if ($this->secure_request()) {
            $url = str_ireplace('http://', 'https://', $url);
        }

        return $url;
    }

    public function ip_country($ip_address)
    {
        if (config_item('ip2nation') == 'y') {
            $ip_data = new Ip_to_nation_data;

            return strtoupper($ip_data->find($ip_address)) ?: null;
        }
    }

    public function format_date(DateTime $datetime, $format)
    {
        return $this->ee->localize->format_date($format, $datetime->getTimestamp(), $datetime->getTimezone()->getName());
    }
}
