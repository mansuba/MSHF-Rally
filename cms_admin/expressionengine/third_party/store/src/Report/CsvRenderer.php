<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Report;

class CsvRenderer extends AbstractRenderer
{
    public function table_open()
    {
    }

    public function table_close()
    {
    }

    public function table_header(array $data)
    {
        $this->table_row($data);
    }

    public function table_footer(array $data)
    {
        $this->table_row($data);
    }

    public function table_row(array $data)
    {
        $row = array();

        foreach ($data as $data) {
            if (is_array($data)) {
                $content = array_pull($data, 'data');
            } else {
                $content = $data;
                $data = array();
            }

            $content = strip_tags($content);
            if (preg_match('/[\s",]/', $content)) {
                $content = '"'.str_replace('"', '""', $content).'"';
            }

            $row[] = $content;

            if (isset($data['colspan']) && $data['colspan'] > 1) {
                for ($i = 1; $i < $data['colspan']; $i++) {
                    $row[] = null;
                }
            }
        }

        $this->render(implode(',', $row)."\r\n");
    }
}
