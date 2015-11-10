<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Tag;

use Store\Model\Order;

class DownloadTag extends AbstractTag
{
    public function parse()
    {
        $file_url = $this->param('url');
        if (empty($file_url)) return;

        $order_id = (int) $this->param('order_id');
        $expire = (int) $this->param('expire');

        $order = Order::where('site_id', config_item('site_id'))->where('id', $order_id)->first();
        if (empty($order) OR $order->is_order_unpaid) return;

        // make sure download hasn't expired
        if ($this->ee->store->orders->is_download_expired($order, $expire)) return;

        $file = $this->ee->store->store->get_file_by_url($file_url);
        if (!$file) {
            return '<span style="font-weight: bold; color: red;">'.lang('store.download_not_found').'</span>';
        }

        $params = array('o' => $order_id, 'f' => $file->file_id);
        if ($expire > 0) $params['e'] = $expire;

        // generate file download key to verify parameters (prevents people guessing download URLs)
        $params['k'] = $this->ee->store->orders->generate_download_key($order, $file->file_id, $expire);

        $out = '<a href="'.$this->ee->store->store->get_action_url('act_download_file').AMP.
            http_build_query($params).'"';

        foreach (array('id', 'class', 'style') as $param) {
            if (($param_val = $this->param($param)) !== false) {
                $out .= ' '.$param.'="'.$param_val.'"';
            }
        }
        $out .= '">'.$this->tagdata.'</a>';

        return $out;
    }
}
