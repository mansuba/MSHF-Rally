<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use Carbon\Carbon;

class DateTime extends Carbon
{
    /**
     * Format using EE's special syntax and localization
     */
    public function formatEE($format)
    {
        return ee()->localize->format_date($format, $this->timestamp, $this->timezoneName);
    }
}
