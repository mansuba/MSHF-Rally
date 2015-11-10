<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

use Omnipay\Omnipay;

class PaymentMethod extends AbstractModel
{
    protected $table = 'store_payment_methods';

    public function createGateway($httpClient = null)
    {
        $gateway = Omnipay::create($this->class, $httpClient);
        $gateway->initialize($this->settings);

        return $gateway;
    }

    public function getSettingsAttribute()
    {
        $settings = json_decode($this->attributes['settings'], true);

        return is_array($settings) ? $settings : array();
    }

    public function setSettingsAttribute($value)
    {
        $this->attributes['settings'] = empty($value) ? null : json_encode((array) $value);
    }
}
