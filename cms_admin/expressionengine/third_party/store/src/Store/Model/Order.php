<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Model;

use Illuminate\Database\Query\Expression as Raw;
use Store\Converter;
use Store\Exception\CartException;
use Store\Model\OrderItem;

class Order extends AbstractModel
{
    protected $table = 'store_orders';

    // temporary password attributes not stored in database
    protected $tempPassword;
    protected $tempPasswordConfirm;

    protected $currency_attributes = array(
        'order_subtotal', 'order_subtotal_tax', 'order_subtotal_inc_tax',
        'order_discount', 'order_discount_tax', 'order_discount_inc_tax',
        'order_subtotal_inc_discount', 'order_subtotal_inc_discount_tax', 'order_subtotal_inc_discount_inc_tax',
        'order_shipping', 'order_shipping_discount', 'order_shipping_inc_discount',
        'order_shipping_tax', 'order_shipping_inc_tax', 'order_shipping_total',
        'order_handling', 'order_handling_tax', 'order_handling_inc_tax', 'order_handling_total',
        'order_subtotal_inc_shipping', 'order_subtotal_inc_shipping_tax', 'order_subtotal_inc_shipping_inc_tax',
        'order_shipping_subtotal', 'order_tax', 'order_items_total', 'order_adjustments_total',
        'order_you_save', 'order_total', 'order_total_ex_tax', 'order_paid', 'order_owing');

    protected $fillable = array(
        'billing_name', 'billing_first_name', 'billing_last_name',
        'billing_address1', 'billing_address2', 'billing_address3', 'billing_city',
        'billing_region', 'billing_state', 'billing_country', 'billing_postcode', 'billing_phone', 'billing_company',
        'shipping_name', 'shipping_first_name', 'shipping_last_name',
        'shipping_address1', 'shipping_address2', 'shipping_address3', 'shipping_city',
        'shipping_region', 'shipping_state', 'shipping_country', 'shipping_postcode', 'shipping_phone', 'shipping_company',
        'order_custom1', 'order_custom2', 'order_custom3', 'order_custom4', 'order_custom5',
        'order_custom6', 'order_custom7', 'order_custom8', 'order_custom9',
        'order_email', 'billing_same_as_shipping', 'shipping_same_as_billing',
        'promo_code', 'remove_promo_code', 'shipping_method', 'payment_method',
        'accept_terms', 'register_member', 'username', 'screen_name', 'password', 'password_confirm',
    );

    public function __construct(array $attributes = array())
    {
        $this->loadDefaults();

        parent::__construct($attributes);
    }

    protected function loadDefaults()
    {
        // generate order hash
        $this->order_hash = md5(uniqid(mt_rand(), true));

        // default billing same as shipping value
        $defaultOrderAddress = config_item('store_default_order_address');
        if ($defaultOrderAddress and $defaultOrderAddress !== 'none') {
            $this->$defaultOrderAddress = true;
        }

        // default country and region
        $this->billing_country = config_item('store_default_country');
        $this->billing_state = config_item('store_default_state');
        $this->shipping_country = config_item('store_default_country');
        $this->shipping_state = config_item('store_default_state');

        // default shipping method
        $this->shipping_method = config_item('store_default_shipping_method_id');

        // default units
        $this->weight_units = config_item('store_weight_units');
        $this->dimension_units = config_item('store_dimension_units');
    }

    public function items()
    {
        return $this->hasMany('\Store\Model\OrderItem');
    }

    public function adjustments()
    {
        return $this->hasMany('\Store\Model\OrderAdjustment');
    }

    public function transactions()
    {
        return $this->hasMany('\Store\Model\Transaction');
    }

    public function history()
    {
        return $this->hasMany('\Store\Model\OrderHistory');
    }

    public function discount()
    {
        return $this->belongsTo('\Store\Model\Discount');
    }

    public function member()
    {
        return $this->belongsTo('\Store\Model\Member');
    }

    public function getOrderSubtotalIncTaxAttribute()
    {
        return $this->order_subtotal + $this->order_subtotal_tax;
    }

    public function getOrderSubtotalIncDiscountAttribute()
    {
        return $this->order_subtotal - $this->order_discount;
    }

    public function getOrderSubtotalIncDiscountTaxAttribute()
    {
        return $this->order_subtotal_tax - $this->order_discount_tax;
    }

    public function getOrderSubtotalIncDiscountIncTaxAttribute()
    {
        return $this->order_subtotal_inc_tax - $this->order_discount_inc_tax;
    }

    public function getOrderDiscountIncTaxAttribute()
    {
        return $this->order_discount + $this->order_discount_tax;
    }

    public function getOrderShippingIncDiscountAttribute()
    {
        return $this->order_shipping - $this->order_shipping_discount;
    }

    public function getOrderShippingIncTaxAttribute()
    {
        return $this->order_shipping_total;
    }

    public function getOrderHandlingIncTaxAttribute()
    {
        return $this->order_handling_total;
    }

    public function getOrderSubtotalIncShippingAttribute()
    {
        return $this->order_subtotal + $this->order_shipping;
    }

    public function getOrderSubtotalIncShippingTaxAttribute()
    {
        return $this->order_subtotal_tax + $this->order_shipping_tax;
    }

    public function getOrderSubtotalIncShippingIncTaxAttribute()
    {
        return $this->order_subtotal_inc_tax + $this->order_shipping_inc_tax;
    }

    public function getOrderTotalExTaxAttribute()
    {
        return $this->order_total - $this->order_tax;
    }

    public function getOrderOwingAttribute()
    {
        return $this->order_total - $this->order_paid;
    }

    public function getOrderItemsTotalAttribute()
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->item_total;
        }

        return $total;
    }

    public function getOrderAdjustmentsTotalAttribute()
    {
        $total = 0;
        foreach ($this->adjustments as $adj) {
            if (!$adj->included) {
                $total += $adj->amount;
            }
        }

        return $total;
    }

    public function getOrderYouSaveAttribute()
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->you_save * $item->item_qty;
        }

        return $total;
    }

    public function getTaxPercentAttribute()
    {
        return $this->tax_rate * 100;
    }

    public function getIsOrderPaidAttribute()
    {
        return $this->order_paid >= $this->order_total;
    }

    public function getIsOrderUnpaidAttribute()
    {
        return $this->order_paid < $this->order_total;
    }

    public function getIsOrderCompleteAttribute()
    {
        return $this->order_completed_date > 0;
    }

    public function getRemovePromoCodeAttribute()
    {
        return false;
    }

    public function setRemovePromoCodeAttribute($value)
    {
        if ( ! empty($value)) {
            $this->promo_code = null;
        }
    }

    public function isEmpty()
    {
        return $this->order_qty == 0;
    }

    public function getBillingNameAttribute()
    {
        return trim($this->billing_first_name.' '.$this->billing_last_name);
    }

    public function setBillingNameAttribute($value)
    {
        $parts = explode(' ', $value, 2);
        $this->billing_first_name = $parts[0];
        $this->billing_last_name = isset($parts[1]) ? $parts[1] : null;
    }

    public function getBillingAddress3Attribute()
    {
        return $this->billing_city;
    }

    public function setBillingAddress3Attribute($value)
    {
        $this->billing_city = $value;
    }

    public function getBillingRegionAttribute()
    {
        return $this->billing_state;
    }

    public function setBillingRegionAttribute($value)
    {
        $this->billing_state = $value;
    }

    public function getBillingStateNameAttribute()
    {
        return ee()->store->shipping->get_state_name($this->billing_country, $this->billing_state);
    }

    public function getBillingCountryNameAttribute()
    {
        return ee()->store->shipping->get_country_name($this->billing_country);
    }

    public function getBillingAddressFullAttribute()
    {
        return implode('<br>', array(
            $this->billing_address1,
            $this->billing_address2,
            $this->billing_city,
            $this->billing_state_name.' '.$this->billing_postcode,
            $this->billing_country_name,
        ));
    }

    public function getShippingNameAttribute()
    {
        return trim($this->shipping_first_name.' '.$this->shipping_last_name);
    }

    public function setShippingNameAttribute($value)
    {
        $parts = explode(' ', $value, 2);
        $this->shipping_first_name = $parts[0];
        $this->shipping_last_name = isset($parts[1]) ? $parts[1] : null;
    }

    public function getShippingAddress3Attribute()
    {
        return $this->shipping_city;
    }

    public function setShippingAddress3Attribute($value)
    {
        $this->shipping_city = $value;
    }

    public function getShippingRegionAttribute()
    {
        return $this->shipping_state;
    }

    public function setShippingRegionAttribute($value)
    {
        $this->shipping_state = $value;
    }

    public function getShippingStateNameAttribute()
    {
        return ee()->store->shipping->get_state_name($this->shipping_country, $this->shipping_state);
    }

    public function getShippingCountryNameAttribute()
    {
        return ee()->store->shipping->get_country_name($this->shipping_country);
    }

    public function getShippingAddressFullAttribute()
    {
        return implode('<br>', array(
            $this->shipping_address1,
            $this->shipping_address2,
            $this->shipping_city,
            $this->shipping_state_name.' '.$this->shipping_postcode,
            $this->shipping_country_name,
        ));
    }

    public function setBillingSameAsShippingAttribute($value)
    {
        return $this->setBooleanAttribute('billing_same_as_shipping', $value);
    }

    public function setShippingSameAsBillingAttribute($value)
    {
        return $this->setBooleanAttribute('shipping_same_as_billing', $value);
    }

    public function getOrderShippingQtyAttribute()
    {
        $value = 0;
        foreach ($this->items as $item) {
            if (! $item->free_shipping) {
                $value += $item->item_qty;
            }
        }

        return $value;
    }

    public function getOrderShippingSubtotalAttribute()
    {
        $value = 0;
        foreach ($this->items as $item) {
            if (! $item->free_shipping) {
                $value += $item->item_subtotal;
            }
        }

        return $value;
    }

    public function getOrderShippingWeightAttribute()
    {
        $value = 0;
        foreach ($this->items as $item) {
            if (! $item->free_shipping) {
                $value += $item->weight * $item->item_qty;
            }
        }

        return $value;
    }

    public function getOrderShippingLengthAttribute()
    {
        $value = 0;
        foreach ($this->items as $item) {
            if (! $item->free_shipping) {
                $value = max($value, $item->dimensions[2]);
            }
        }

        return $value;
    }

    public function getOrderShippingWidthAttribute()
    {
        $value = 0;
        foreach ($this->items as $item) {
            if (! $item->free_shipping) {
                $value = max($value, $item->dimensions[1]);
            }
        }

        return $value;
    }

    public function getOrderShippingHeightAttribute()
    {
        $value = 0;
        foreach ($this->items as $item) {
            if (! $item->free_shipping) {
                $value += $item->dimensions[0] * $item->item_qty;
            }
        }

        return $value;
    }

    public function getOrderShippingWeightKgAttribute()
    {
        return Converter::convertWeight($this->order_shipping_weight, $this->weight_units, 'kg');
    }

    public function getOrderShippingWeightLbAttribute()
    {
        return Converter::convertWeight($this->order_shipping_weight, $this->weight_units, 'lb');
    }

    public function getOrderShippingLengthCmAttribute()
    {
        return Converter::convertLength($this->order_shipping_length, $this->dimension_units, 'cm');
    }

    public function getOrderShippingLengthInAttribute()
    {
        return Converter::convertLength($this->order_shipping_length, $this->dimension_units, 'in');
    }

    public function getOrderShippingWidthCmAttribute()
    {
        return Converter::convertLength($this->order_shipping_width, $this->dimension_units, 'cm');
    }

    public function getOrderShippingWidthInAttribute()
    {
        return Converter::convertLength($this->order_shipping_width, $this->dimension_units, 'in');
    }

    public function getOrderShippingHeightCmAttribute()
    {
        return Converter::convertLength($this->order_shipping_height, $this->dimension_units, 'cm');
    }

    public function getOrderShippingHeightInAttribute()
    {
        return Converter::convertLength($this->order_shipping_height, $this->dimension_units, 'in');
    }

    public function getPaymentMethodNameAttribute()
    {
        $payment = PaymentMethod::where('site_id', $this->site_id)
            ->where('class', $this->payment_method)->first();

        return $payment ? $payment->title : $this->payment_method;
    }

    public function getPasswordAttribute()
    {
        return $this->tempPassword;
    }

    /**
     * Hash password as soon as it is set so we can securely store it in the database
     */
    public function setPasswordAttribute($value)
    {
        $this->tempPassword = $value;

        ee()->load->library('auth');
        // if submitted password is empty, hash won't be overwritten
        if (($hash = ee()->auth->hash_password($value)) !== false) {
            $this->password_hash = $hash['password'];
            $this->password_salt = $hash['salt'];
        }
    }

    public function getPasswordConfirmAttribute()
    {
        return $this->tempPasswordConfirm;
    }

    public function setPasswordConfirmAttribute($value)
    {
        $this->tempPasswordConfirm = $value;
    }

    public function getParsedReturnUrlAttribute()
    {
        $url = $this->return_url;
        $url = str_replace('ORDER_ID', $this->id, $url);
        $url = str_replace('ORDER_HASH', $this->order_hash, $url);

        return $url;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array                              $attributes
     * @return Illuminate\Database\Eloquent\Model
     */
    public function fill(array $attributes, array $form_params = array())
    {
        // mass assign fillable attributes
        parent::fill($attributes);

        // mass assign item quantities
        if (isset($attributes['items']) && is_array($attributes['items'])) {
            // add any new items to cart
            foreach ($attributes['items'] as $key => $item_attributes) {
                if (isset($item_attributes['entry_id'])) {
                    $this->addItem($item_attributes, $form_params);
                    unset($attributes['items'][$key]);
                }
            }
        }

        // update quantities for existing items
        if (isset($attributes['items']) || isset($attributes['remove_items'])) {
            foreach ($this->items as $item) {
                // skip items we just added
                if (!$item->exists) {
                    continue;
                }

                if (isset($attributes['items'][$item->id]['item_qty'])) {
                    $item->item_qty = $attributes['items'][$item->id]['item_qty'];
                }

                if ( ! empty($attributes['remove_items'][$item->id])) {
                    $item->item_qty = 0;
                }
            }
        }

        return $this;
    }

    public function addItem(array $item_attributes, array $form_params = array())
    {
        if (empty($item_attributes['entry_id'])) {
            return;
        }

        if (ee()->extensions->active_hook('store_order_item_add_start')) {
            ee()->extensions->call('store_order_item_add_start', $this, $item_attributes, $form_params);
            if (ee()->extensions->end_script) return;
        }

        // decode item modifiers
        if (isset($item_attributes['modifiers'])) {
            $mod_values = $item_attributes['modifiers'];
        } else {
            $mod_values = array();
            foreach ($item_attributes as $key => $value) {
                if (strpos($key, 'modifiers_') === 0) {
                    $mod_values[substr($key, 10)] = $value;
                }
            }
        }

        // fetch custom input fields
        $input_values = array();
        foreach ($form_params as $param => $name) {
            if (strpos($param, 'input:') !== 0) continue;
            $param = substr($param, 6);

            // only use param if it was submitted
            if (isset($item_attributes[$param])) {
                $input_values[$name] = $item_attributes[$param];
            }
        }

        $entry_id = (int) $item_attributes['entry_id'];
        $stock = Stock::findByModifiers($entry_id, $mod_values);
        if (empty($stock)) {
            throw new CartException("Can't find product (entry ID: $entry_id, modifiers ".json_encode($mod_values).')');
        }

        $modifiers = $this->fetchModifiers($stock->product, $mod_values, $input_values);

        // check for existing identical item in cart
        if ($this->exists) {
            $item = $this->findExistingItem($entry_id, $modifiers);
        } else {
            $item = false;
        }

        // how many are we adding
        if (isset($item_attributes['update_qty'])) {
            $item_attributes['item_qty'] = $item_attributes['update_qty'];
        } elseif (empty($item_attributes['item_qty'])) {
            // not adding this product to the cart
            return;
        }

        if ($item) {
            if (isset($item_attributes['update_qty'])) {
                // overwrite item quantity
                $item->item_qty = $item_attributes['update_qty'];
            } else {
                $item->item_qty += $item_attributes['item_qty'];
            }
        } else {
            // add new item to order
            $product = $stock->product;
            $item = OrderItem::createFromStock($stock);
            $item->site_id = $this->site_id;
            $item->sku = $stock->sku;
            $item->item_qty = $item_attributes['item_qty'];
            $item->modifiers = $modifiers;

            $channel_fields = ee()->store->products->get_channel_fields();

            // does this channel allow customers to specify a dynamic price?
            if (!empty($channel_fields[$item->channel_id]['field_settings']['enable_custom_prices'])) {
                if (isset($item_attributes['price'])) {
                    // override product price with submitted value
                    $product->price = (float) $item_attributes['price'];
                }
            }

            // does this channel allow customers to specify dynamic weight/dimensions?
            if (!empty($channel_fields[$item->channel_id]['field_settings']['enable_custom_weights'])) {
                foreach (array('length', 'width', 'height', 'weight') as $key) {
                    if (isset($item_attributes[$key])) {
                        // override product attribute with submitted value
                        $item->$key = (float) $item_attributes[$key];
                    }
                }
            }

            if (!empty($modifiers[0])) {
                foreach ($modifiers as $mod) {
                    $product->price += $mod['price_mod'];
                }
            }

            // apply sales & store price
            $product = ee()->store->products->apply_sales($product);
            $item->price = $product->sale_price;
            $item->regular_price = $product->regular_price;
            $item->on_sale = $product->on_sale;

            $this->items[] = $item;
        }

        if (ee()->extensions->active_hook('store_order_item_add_end')) {
            ee()->extensions->call('store_order_item_add_end', $this, $item, $item_attributes, $form_params);
        }
    }

    /**
     * Create the order modifiers array for a product based on input values
     */
    public function fetchModifiers($product, $mod_values, $input_values)
    {
        if ( ! is_array($mod_values)) $mod_values = array();
        if ( ! is_array($input_values)) $input_values = array();

        $modifiers = array();

        if (count($mod_values) > 0) {
            $product_modifiers = $product->modifiers()->with('options')->whereIn('product_mod_id', array_keys($mod_values))->get();

            foreach ($product_modifiers as $product_mod) {
                $mod_value = $mod_values[$product_mod->product_mod_id];

                $mod_data = array(
                    'modifier_id' => $product_mod->product_mod_id,
                    'modifier_name' => $product_mod->mod_name,
                    'modifier_type' => $product_mod->mod_type,
                    'modifier_value' => $mod_value,
                    'option_id' => '',
                    'price_mod' => '',
                    'price_mod_inc_tax' => '',
                );

                if ($product_mod->mod_type == 'var' OR $product_mod->mod_type == 'var_single_sku') {
                    // check specified option is valid
                    // TODO: array_filter one liner?
                    $product_opt = null;
                    foreach ($product_mod->options as $option) {
                        if ($option->product_opt_id == $mod_value) {
                            $product_opt = $option;
                            break;
                        }
                    }
                    if ( ! $product_opt) continue;

                    $mod_data['option_id'] = $mod_value;
                    $mod_data['modifier_value'] = $product_opt->opt_name;
                    $mod_data['price_mod'] = $product_opt->opt_price_mod;
                    $mod_data['price_mod_inc_tax'] = store_round_currency($product_opt->opt_price_mod * (1 + $this->tax_rate), true);
                }

                $modifiers[] = $mod_data;
            }
        }

        // add modifier entries for template inputs
        foreach ($input_values as $name => $value) {
            $modifiers[] = array(
                'modifier_id' => '',
                'modifier_name' => $name,
                'modifier_type' => 'custom',
                'modifier_value' => $value,
                'option_id' => '',
                'price_mod' => '',
                'price_mod_inc_tax' => '',
            );
        }

        // work around weird bug in EE template engine
        if (empty($modifiers)) $modifiers = array(array());
        return $modifiers;
    }

    /**
     * Find existing item in the order with the same modifiers
     */
    public function findExistingItem($entry_id, $modifiers)
    {
        foreach ($this->items as $item) {
            // TODO: test whether comparing multidimensional arrays like this is reliable
            if ($item->entry_id == $entry_id and $item->modifiers == $modifiers) {
                return $item;
            }
        }

        return false;
    }

    public function countItemsById($entry_id)
    {
        $count = 0;
        foreach ($this->items as $item) {
            if ($item->entry_id == $entry_id) {
                $count += $item->item_qty;
            }
        }

        return $count;
    }

    /**
     * Recalculate order totals, shipping, taxes etc, and save the order.
     */
    public function recalculate()
    {
        // create order if it doesn't already exist (need order ID to save items)
        if (!$this->exists) {
            $this->save();
        }

        if (ee()->extensions->active_hook('store_order_recalculate_start')) {
            ee()->extensions->call('store_order_recalculate_start', $this);
            if (ee()->extensions->end_script) return;
        }

        // pre-populate the order details based on mapped member fields
        if ($this->member_id and false == $this->member_data_loaded) {
            $this->loadMemberAttrs();
        }

        // handle billing same as shipping etc
        if ($this->billing_same_as_shipping) {
            $this->shipping_same_as_billing = false;
            $this->duplicateAddressAttrs('shipping', 'billing');
        } elseif ($this->shipping_same_as_billing) {
            $this->billing_same_as_shipping = false;
            $this->duplicateAddressAttrs('billing', 'shipping');
        }

        // recalculate items
        foreach ($this->items as $key => $item) {
            if (ee()->extensions->active_hook('store_order_item_recalculate_start')) {
                ee()->extensions->call('store_order_item_recalculate_start', $this, $item);
                if (ee()->extensions->end_script) continue;
            }

            $item->recalculate();

            if ($item->item_qty == 0) {
                if ($item->exists) {
                    $item->delete();
                }

                unset($this->items[$key]);
                continue;
            }

            if (ee()->extensions->active_hook('store_order_item_recalculate_end')) {
                ee()->extensions->call('store_order_item_recalculate_end', $this, $item);
            }

            $this->items()->save($item);
        }

        // reset order totals
        $this->updateItemTotals();

        // delete existing adjustments and reload relationship
        $this->adjustments()->delete();
        unset($this->relations['adjustments']);

        // loop through enabled adjusters and save adjustments
        $sort = 0;
        $this->order_total = $this->order_subtotal;
        foreach (ee()->store->orders->get_adjusters() as $adjuster) {
            // run adjustments and add to order
            foreach ($adjuster->adjust($this) as $adjustment) {
                $adjustment->site_id = $this->site_id;
                $adjustment->sort = $sort++;
                $this->adjustments()->save($adjustment);

                // update order total
                if (!$adjustment->included) {
                    $this->order_total += $adjustment->amount;
                }
            }
        }

        if (ee()->extensions->active_hook('store_order_recalculate_end')) {
            ee()->extensions->call('store_order_recalculate_end', $this);
        }

        // re-save order and items
        $this->save();
        foreach ($this->items as $item) {
            $item->save();
        }
    }

    protected function loadMemberAttrs()
    {
        if ('' == $this->order_email) {
            $this->order_email = $this->member->email;
        }

        $data = ee()->store->member->load_member_data($this->member_id);

        foreach (ee()->store->config->order_fields() as $key => $field) {
            if ($field['member_field'] and '' == $this->$key) {
                $member_field = $field['member_field'];
                if (isset($data[$member_field])) {
                    $this->$key = $data[$member_field];
                }
            }
        }

        $this->member_data_loaded = true;
    }

    protected function duplicateAddressAttrs($from, $to)
    {
        foreach (array('name', 'address1', 'address2', 'city',
            'state', 'country', 'postcode', 'phone', 'company') as $field)
        {
            $this->{"{$to}_{$field}"} = $this->{"{$from}_{$field}"};
        }
    }

    public function updateItemTotals()
    {
        // reset order totals
        $this->order_qty = 0;
        $this->order_subtotal = 0;
        $this->order_weight = 0;
        $this->order_length = 0;
        $this->order_width = 0;
        $this->order_height = 0;

        // sum items
        foreach ($this->items as $item) {
            // update order totals
            $this->order_qty += $item->item_qty;
            $this->order_subtotal += $item->item_subtotal;
            $this->order_weight += $item->weight * $item->item_qty;

            // update order dimensions
            $dimensions = $item->dimensions;
            $this->order_length = max($this->order_length, $dimensions[2]);
            $this->order_width = max($this->order_width, $dimensions[1]);
            $this->order_height += $dimensions[0] * $item->item_qty;
        }
    }

    public function getTotalPaid()
    {
        return $this->transactions()
            ->select(array(ee()->store->db->raw('sum(case `type` when "refund" then -`amount` else `amount` end) as `total`')))
            ->whereIn('type', array(Transaction::PURCHASE, Transaction::CAPTURE, Transaction::REFUND))
            ->where('status', Transaction::SUCCESS)
            ->pluck('total') ?: 0;
    }

    public function getTotalAuthorized()
    {
        return $this->transactions()
            ->select(array(ee()->store->db->raw('sum(case `type` when "refund" then -`amount` else `amount` end) as `total`')))
            ->whereIn('type', array(Transaction::AUTHORIZE, Transaction::PURCHASE, Transaction::CAPTURE, Transaction::REFUND))
            ->where('status', Transaction::SUCCESS)
            ->pluck('total') ?: 0;
    }

    /**
     * Mark an order as "complete". At this point the default order status is set, and
     * the order is no longer a cart.
     */
    public function markAsComplete()
    {
        if (ee()->extensions->active_hook('store_order_complete_start')) {
            ee()->extensions->call('store_order_complete_start', $this);
            if (ee()->extensions->end_script) return;
        }

        if ($this->order_completed_date > 0) {
            // order is already complete
            return false;
        }

        $this->order_completed_date = time();

        // mark order as paid if not already
        if (empty($this->order_paid_date)) {
            $this->order_paid_date = time();
        }

        // did the customer request a user account with their order?
        // if order is already associated with a member, skip account creation
        if ($this->register_member) {
            ee()->store->member->register($this);
        }

        // update mapped member fields with order data
        if ($this->member_id) {
            ee()->store->member->save_member_data($this->member_id, $this->toTagArray());
        }

        // save order before continuing
        $this->save();

        // adjust stock levels
        foreach ($this->items as $item) {
            Stock::where('id', $item->stock_id)->where('track_stock', 1)
                ->update(array('stock_level' => new Raw('stock_level - '.(int) $item->item_qty)));
        }

        // if a promo code was used, increment its use count
        if ($this->discount_id) {
            Discount::where('id', $this->discount_id)->increment('total_use_count');
        }

        // update the order status (and trigger any associated email confirmations)
        $status = Status::where('site_id', $this->site_id)->where('is_default', 1)->first();
        if ($status) {
            $this->updateStatus($status);
        }

        if (ee()->extensions->active_hook('store_order_complete_end')) {
            ee()->extensions->call('store_order_complete_end', $this);
        }
    }

    public function updateStatus(Status $status, $member_id = 0, $message = null)
    {
        if (ee()->extensions->active_hook('store_order_update_status_start')) {
            ee()->extensions->call('store_order_update_status_start', $this, $status, $member_id, $message);
            if (ee()->extensions->end_script) return;
        }

        // update order
        $this->order_status_name = $status->name;
        $this->order_status_updated = time();
        $this->order_status_member_id = (int) $member_id;
        $this->order_status_message = $message;
        $this->save();

        // add history entry
        $history = new OrderHistory;
        $history->order_id = $this->id;
        $history->order_status_name = $status->name;
        $history->order_status_updated = time();
        $history->order_status_member_id = (int) $member_id;
        $history->order_status_message = $message;
        $history->save();

        // send any email template associated with the new status
        if ($status->email_ids) {
            $emails = Email::whereIn('id', $status->email_ids)
                ->where('enabled', 1)
                ->get();

            foreach ($emails as $email) {
                ee()->store->email->send($email, $this);
            }
        }

        if (ee()->extensions->active_hook('store_order_update_status_end')) {
            ee()->extensions->call('store_order_update_status_end', $this, $status, $history, $member_id, $message);
        }
    }

    public function toTagArray()
    {
        $attributes = parent::toTagArray();
        $attributes['order_id'] = $this->id;
        $attributes['tax_percent'] = $this->tax_percent;
        $attributes['is_order_paid'] = $this->is_order_paid;
        $attributes['is_order_unpaid'] = $this->is_order_unpaid;
        $attributes['order_status'] = store_order_status_name($this->order_status_name);

        // shipping values
        $attributes['order_shipping_qty'] = $this->order_shipping_qty;
        $attributes['order_shipping_subtotal'] = $this->order_shipping_subtotal;
        $attributes['order_shipping_weight'] = $this->order_shipping_weight;
        $attributes['order_shipping_length'] = $this->order_shipping_length;
        $attributes['order_shipping_width'] = $this->order_shipping_width;
        $attributes['order_shipping_height'] = $this->order_shipping_height;

        // promo codes
        if ($this->discount) {
            $attributes['discount:id'] = $this->discount_id;
            $attributes['discount:name'] = $this->discount->name;
            $attributes['discount:start_date'] = $this->discount->start_date;
            $attributes['discount:end_date'] = $this->discount->end_date;
            $attributes['discount:free_shipping'] = $this->discount->free_shipping;
        } else {
            $attributes['discount:id'] = null;
            $attributes['discount:name'] = null;
            $attributes['discount:start_date'] = null;
            $attributes['discount:end_date'] = null;
            $attributes['discount:free_shipping'] = null;
        }

        $attributes['promo_code_description'] = $attributes['discount:name'];
        $attributes['promo_code_desc'] = $attributes['discount:name'];
        $attributes['promo_code_free_shipping'] = $attributes['discount:free_shipping'];
        $attributes['promo_code_type'] = null;
        $attributes['promo_code_value'] = null;

        $attributes['password'] = $this->password;
        $attributes['password_confirm'] = $this->password_confirm;

        // lookup values
        $attributes['billing_name'] = $this->billing_name;
        $attributes['billing_state_name'] = $this->billing_state_name;
        $attributes['billing_country_name'] = $this->billing_country_name;
        $attributes['shipping_name'] = $this->shipping_name;
        $attributes['shipping_state_name'] = $this->shipping_state_name;
        $attributes['shipping_country_name'] = $this->shipping_country_name;
        $attributes['shipping_method_id'] = $this->shipping_method;
        $attributes['payment_method_name'] = $this->payment_method_name;

        // legacy address attributes
        $attributes['billing_address3'] = $this->billing_address3;
        $attributes['billing_region'] = $this->billing_region;
        $attributes['billing_region_name'] = $this->billing_state_name;
        $attributes['shipping_address3'] = $this->shipping_address3;
        $attributes['shipping_region'] = $this->shipping_region;
        $attributes['shipping_region_name'] = $this->shipping_state_name;

        // relations
        $attributes['items'] = $this->items->toTagArray('item');
        $attributes['adjustments'] = $this->adjustments->toTagArray('adjustment');

        return $attributes;
    }
}
