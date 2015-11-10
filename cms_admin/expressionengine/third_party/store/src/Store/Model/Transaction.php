<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

use Omnipay\Omnipay;
use Omnipay\Common\Exception\OmnipayException;

class Transaction extends AbstractModel
{
    const AUTHORIZE = 'authorize';
    const CAPTURE = 'capture';
    const PURCHASE = 'purchase';
    const REFUND = 'refund';

    const PENDING = 'pending';
    const REDIRECT = 'redirect';
    const SUCCESS = 'success';
    const FAILED = 'failed';

    protected $table = 'store_transactions';

    public function __construct(array $attributes = array())
    {
        // generate unique hash
        $this->hash = md5(uniqid(mt_rand(), true));

        parent::__construct($attributes);
    }

    public function order()
    {
        return $this->belongsTo('\Store\Model\Order');
    }

    public function member()
    {
        return $this->belongsTo('\Store\Model\Member');
    }

    public function parent()
    {
        return $this->belongsTo('\Store\Model\Transaction', 'parent_id');
    }

    public function children()
    {
        return $this->hasMany('\Store\Model\Transaction', 'parent_id');
    }

    public function canCapture()
    {
        // can only capture authorize payments
        if ($this->type != static::AUTHORIZE || $this->status != static::SUCCESS) {
            return false;
        }

        // check gateway supports capture
        try {
            if (!Omnipay::create($this->payment_method)->supportsCapture()) {
                return false;
            }
        } catch (OmnipayException $e) {
            return false;
        }

        // check transaction hasn't already been captured
        return $this->children()->where('type', static::CAPTURE)
            ->where('status', static::SUCCESS)
            ->count() == 0;
    }

    public function canRefund()
    {
        // can only refund purchase or capture transactions
        if (!in_array($this->type, array(static::PURCHASE, static::CAPTURE)) ||
            $this->status != static::SUCCESS) {
            return false;
        }

        // check gateway supports refund
        try {
            if (!Omnipay::create($this->payment_method)->supportsRefund()) {
                return false;
            }
        } catch (OmnipayException $e) {
            return false;
        }

        // check transaction hasn't already been refunded
        return $this->children()->where('type', static::REFUND)
            ->where('status', static::SUCCESS)
            ->count() == 0;
    }
}
