<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Report;

use Closure;
use Store\DateTime;
use Store\Model\Order;
use Store\OptionBag;

abstract class AbstractReport
{
    protected $ee;
    protected $options;
    protected $orderby;
    protected $sort = 'asc';
    protected $render;

    /**
     * Create a new report
     *
     * @param mixed $ee A reference to the global EE object
     * @param AbstractRenderer $renderer A renderer to format the report
     * @param array|null $options An array of options to initialize the report with
     */
    public function __construct($ee, AbstractRenderer $renderer, $options = array())
    {
        $this->ee = $ee;
        $this->render = $renderer;
        $this->orderby = array_pull($options, 'orderby') ?: $this->orderby;
        $this->sort = array_pull($options, 'sort') ?: $this->sort;
        $this->options = new OptionBag($this->default_options(), $options);
    }

    abstract public function default_options();

    public function options()
    {
        return $this->options;
    }
}
