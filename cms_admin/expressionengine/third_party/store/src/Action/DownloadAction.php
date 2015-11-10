<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Action;

use Store\Model\Order;

class DownloadAction extends AbstractAction
{
    public function perform()
    {
        $order_id = (int) $this->ee->input->get('o');
        $file_id = (int) $this->ee->input->get('f');
        $expire = (int) $this->ee->input->get('e');
        $key = $this->ee->input->get('k');

        $order = Order::where('site_id', config_item('site_id'))->where('id', $order_id)->first();
        if (empty($order) OR $order->is_order_unpaid) {
            return $this->_download_error('Order is not paid!');
        }

        // make sure download key matches
        if ($key !== $this->ee->store->orders->generate_download_key($order, $file_id, $expire)) {
            return $this->_download_error('Incorrect download key!');
        }

        // make sure download link hasn't expired
        if ($this->ee->store->orders->is_download_expired($order, $expire)) {
            exit(lang('download_link_expired'));
        }

        $file_path = $this->ee->store->store->get_file_path($file_id);
        if (empty($file_path)) {
            return $this->_download_error("Can't find file with ID: $file_id");
        }

        if (($real_path = realpath($file_path)) === false) {
            return $this->_download_error("Can't find file: $file_path");
        }

        $path_parts = pathinfo($real_path);
        $extension = $path_parts['extension'];
        $filename = $path_parts['basename'];

        // Load the mime types
        @include(APPPATH.'config/mimes.php');

        // Set a default mime if we can't find it
        $mime = isset($mimes[$extension]) ? $mimes[$extension] : 'application/octet-stream';
        if (is_array($mime)) $mime = $mime[0];

        // dump the file data
        header('Content-Type: "'.$mime.'"');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        /* Hidden config option: store_download_output_method
         * If set to 'xsendfile', downloads will be sent with the X-Sendfile header.
         * This gives better performance, but has not been thoroughly tested yet.
         */
        if (config_item('store_download_output_method') === 'xsendfile') {
            header('X-Sendfile: '.$real_path);
            exit;
        }

        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Content-Length: '.filesize($real_path));

        if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== false) {
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
        } else {
            header('Pragma: no-cache');
        }

        readfile($real_path);
        exit;
    }

    /**
     * Specific download error messages are only displayed to super admins
     */
    private function _download_error($message)
    {
        if ($this->ee->session->userdata('group_id') == 1) {
            show_error($message);
        }

        show_404();
    }
}
