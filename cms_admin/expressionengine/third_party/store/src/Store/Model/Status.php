<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class Status extends AbstractModel
{
    protected $table = 'store_statuses';
    protected $fillable = array('name', 'color', 'email_ids', 'is_default');

    public function setColorAttribute($value)
    {
        $this->attributes['color'] = preg_replace('/[^#\w]+/', '', $value) ?: null;
    }

    public function getEmailIdsAttribute()
    {
        return $this->getPipeArrayAttribute('email_ids');
    }

    public function setEmailIdsAttribute($value)
    {
        return $this->setPipeArrayAttribute('email_ids', $value);
    }

    public function getEmailNames()
    {
        $email_ids = $this->email_ids;

        if (empty($email_ids)) {
            return null;
        }

        $names = Email::where('site_id', $this->site_id)
            ->whereIn('id', $this->email_ids)
            ->lists('name');

        return implode(', ', array_map('store_email_template_name', $names));
    }
}
