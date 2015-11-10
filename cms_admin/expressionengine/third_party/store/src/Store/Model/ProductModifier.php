<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

class ProductModifier extends AbstractModel
{
    protected $table = 'store_product_modifiers';
    protected $primaryKey = 'product_mod_id';
    protected $fillable = array('mod_type', 'mod_name', 'mod_instructions', 'mod_order');

    public function __construct(array $attributes = array())
    {
        $this->mod_type = 'var';
        $this->mod_order = 0;
        parent::__construct($attributes);
    }

    public function product()
    {
        return $this->belongsTo('\Store\Model\Product', 'entry_id');
    }

    public function options()
    {
        return $this->hasMany('\Store\Model\ProductOption', 'product_mod_id'); //->orderBy('opt_order');
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array                              $attributes
     * @return Illuminate\Database\Eloquent\Model
     */
    public function fill(array $attributes)
    {
        // mass assign fillable attributes
        parent::fill($attributes);

        if (isset($attributes['options']) && is_array($attributes['options'])) {
            // replace loaded options
            $this->relations['options'] = array();

            // create new options from form data
            foreach ($attributes['options'] as $key => $opt_attributes) {
                $option = new ProductOption($opt_attributes);
                $option->product_mod_id = $this->product_mod_id;

                if (!empty($opt_attributes['product_opt_id'])) {
                    // existing option
                    $option->product_opt_id = $opt_attributes['product_opt_id'];
                    $option->exists = true;
                }

                $this->relations['options'][$key] = $option;
            }
        }

        return $this;
    }

    public function toTagArray()
    {
        $attributes = parent::toTagArray();
        $attributes['modifier_id'] = $this->product_mod_id;
        $attributes['modifier_name'] = $this->mod_name;
        $attributes['modifier_input_name'] = $this->input_name;
        $attributes['modifier_type'] = $this->mod_type;
        $attributes['modifier_instructions'] = $this->mod_instructions;
        $attributes['modifier_options'] = $this->options->toTagArray();

        $select_options = array();
        foreach ($attributes['modifier_options'] as $option) {
            if (isset($option['option_id'])) {
                $select_options[$option['option_id']] = $option['option_name'];
            }
        }

        // FIXME: stop using shitty CI form helpers
        $attributes['modifier_select'] = form_dropdown($attributes['modifier_input_name'], $select_options);
        $attributes['modifier_input'] = form_input($attributes['modifier_input_name']);

        // add option_first and option_last variables
        if (isset($attributes['modifier_options'][0]['option_first'])) {
            $options_count = count($attributes['modifier_options']);
            $attributes['modifier_options'][0]['option_first'] = true;
            $attributes['modifier_options'][$options_count - 1]['option_last'] = true;
        }

        return $attributes;
    }

    public function getInputNameAttribute()
    {
        return 'modifiers_'.$this->product_mod_id;
    }
}
