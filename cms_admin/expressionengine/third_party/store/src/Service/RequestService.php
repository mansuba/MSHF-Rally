<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

/**
 * Easy access to the Symfony Request class
 */
class RequestService extends \Symfony\Component\HttpFoundation\Request
{
    public function __construct($ee)
    {
        // similar to Request::createFromGlobals(), but allows lazy initialization
        parent::__construct($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER);
    }
}
