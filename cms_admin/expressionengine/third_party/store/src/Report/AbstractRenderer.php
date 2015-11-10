<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Report;

abstract class AbstractRenderer
{
    protected $orderby;
    protected $sort;

    abstract public function table_open();

    abstract public function table_close();

    abstract public function table_header(array $data);

    abstract public function table_row(array $data);

    abstract public function table_footer(array $data);

    /**
     * Initialize renderer.
     *
     * Allows report to format table headings using current orderby/sort options.
     *
     * @param string $orderby The existing column being sorted
     * @param string $sort The existing sort direction
     */
    public function initialize($orderby, $sort)
    {
        $this->orderby = $orderby;
        $this->sort = $sort;
    }

    protected function render($str)
    {
        echo $str;
    }
}
