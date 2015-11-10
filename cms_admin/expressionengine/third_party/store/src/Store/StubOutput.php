<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use EE_Output;

/**
 * StubOutput class prevents ExpressionEngine member class from displaying errors
 */
class StubOutput extends EE_Output
{
    protected $oldOutput;

    public function __construct(EE_Output $oldOutput)
    {
        $this->oldOutput = $oldOutput;

        return parent::__construct();
    }

    /**
     * Stub show_message() function
     */
    public function show_message() {}

    /**
     * We still want show_user_error to call the real show_message function
     */
    public function show_user_error($type = 'submission', $errors, $heading = '')
    {
        $this->oldOutput->show_user_error($type, $errors, $heading);
    }
}
