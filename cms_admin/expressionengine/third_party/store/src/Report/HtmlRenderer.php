<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Report;

class HtmlRenderer extends AbstractRenderer
{
    public function table_open()
    {
        $this->render("<table class='mainTable store_table store_report_table'>\n");
    }

    public function table_close()
    {
        $this->render("</tbody>\n");
        $this->render("</table>\n");
    }

    public function table_header(array $data)
    {
        $this->render("<thead>\n");

        foreach ($data as $key => $cell) {
            if (!is_array($cell)) {
                $cell = array('data' => $cell);
            }
            if (!isset($cell['class'])) {
                $cell['class'] = '';
            }

            if (empty($cell['orderby'])) {
                $cell['class'] .= ' no-sort';
            } else {
                // automatically render clickable headers
                $url_options = array_merge($_GET, array('orderby' => $cell['orderby'], 'sort' => 'asc'));

                if ($this->orderby === $cell['orderby']) {
                    if ($this->sort === 'asc') {
                        $cell['class'] .= ' headerSortDown';
                        // sort in other direction
                        $url_options['sort'] = 'desc';
                    } else {
                        $cell['class'] .= ' headerSortUp';
                    }
                }

                $url = store_cp_url('reports', 'show', $url_options);
                $cell['data'] = store_html_elem('a', array('href' => $url), $cell['data']);
            }

            $data[$key] = $cell;
        }

        $this->table_row($data, 'th');

        $this->render("</thead>\n<tbody>\n");
    }

    public function table_footer(array $data)
    {
        $this->table_row($data, 'td', 'store_table_footer');
    }

    public function table_row(array $data, $td = 'td', $extra_class = false)
    {
        $this->render('<tr>');

        foreach ($data as $data) {
            if (is_array($data)) {
                $content = array_pull($data, 'data');
            } else {
                $content = $data;
                $data = array();
            }

            $class = trim(array_get($data, 'class').' '.$extra_class);
            if ($class) {
                $data['class'] = $class;
            }
            $this->render(store_html_elem($td, $data, $content, true));
        }

        $this->render("</tr>\n");
    }
}
