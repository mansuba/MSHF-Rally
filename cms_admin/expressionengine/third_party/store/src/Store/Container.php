<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use Illuminate\CodeIgniter\CodeIgniterConnectionResolver;
use Store\Model\AbstractModel;

/**
 * Store service container
 */
class Container
{
    protected $ee;

    public function __construct($ee)
    {
        $this->ee = $ee;
    }

    public function initialize()
    {
        // pass all db queries to CI
        $resolver = new CodeIgniterConnectionResolver($this->ee);
        AbstractModel::setConnectionResolver($resolver);
        $this->db = $resolver->connection();

        // load store config
        $this->config->load();
    }

    public function __get($name)
    {
        $class = 'Store\\Service\\'.studly_case($name).'Service';
        $this->$name = new $class($this->ee);

        return $this->$name;
    }
}
