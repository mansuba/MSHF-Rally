<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class Email extends AbstractModel
{
    protected $table = 'store_emails';
    protected $fillable = array('name', 'subject', 'contents', 'to', 'bcc', 'mail_format',
        'word_wrap', 'enabled');

    public function __construct(array $attributes = array())
    {
        $this->enabled = 1;

        parent::__construct($attributes);
    }
}
