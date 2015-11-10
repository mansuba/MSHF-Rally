<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store;

use InvalidArgumentException;

class Converter
{
    const G_PER_LB = 453.59237;
    const MM_PER_IN = 25.4;

    public static function convertWeight($value, $fromUnits, $toUnits)
    {
        if ($fromUnits == $toUnits) {
            return $value;
        }

        // normalize metric to g
        if ($fromUnits == 'kg') {
            $value = $value * 1000;
            $fromUnits = 'g';
        }
        if ($toUnits == 'kg') {
            $value = $value / 1000;
            $toUnits = 'g';
        }

        // if converting g <> kg, our work here is done...
        if ($fromUnits == $toUnits) {
            return $value;
        }

        // convert
        if ($fromUnits == 'g' and $toUnits == 'lb') {
            return $value / static::G_PER_LB;
        }
        if ($fromUnits == 'lb' and $toUnits == 'g') {
            return $value * static::G_PER_LB;
        }

        throw new InvalidArgumentException;
    }

    public static function convertLength($value, $fromUnits, $toUnits)
    {
        if ($fromUnits == $toUnits) {
            return $value;
        }

        // normalize metric to mm
        if ($fromUnits == 'm') {
            $value = $value * 1000;
            $fromUnits = 'mm';
        }
        if ($fromUnits == 'cm') {
            $value = $value * 10;
            $fromUnits = 'mm';
        }
        if ($toUnits == 'm') {
            $value = $value / 1000;
            $toUnits = 'mm';
        }
        if ($toUnits == 'cm') {
            $value = $value / 10;
            $toUnits = 'mm';
        }
        // normalize imperial to in
        if ($fromUnits == 'ft') {
            $value = $value * 12;
            $fromUnits = 'in';
        }
        if ($toUnits == 'ft') {
            $value = $value / 12;
            $toUnits == 'in';
        }

        // if converting m <> cm <> mm, or ft <> in, our work here is done...
        if ($fromUnits == $toUnits) {
            return $value;
        }

        // convert
        if ($fromUnits == 'mm' and $toUnits == 'in') {
            return $value / static::MM_PER_IN;
        }
        if ($fromUnits == 'in' and $toUnits == 'mm') {
            return $value * static::MM_PER_IN;
        }

        throw new InvalidArgumentException;
    }
}
