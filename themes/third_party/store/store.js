/*
Exp:resso Store module for ExpressionEngine
Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
*/


(function() {
    var $, lib;

    $ = window.jQuery;

    lib = window.ExpressoStore != null ? window.ExpressoStore : window.ExpressoStore = {};

    if (lib.config == null) {
        lib.config = {};
    }

    if (lib.products == null) {
        lib.products = {};
    }

    // format currency according to the current config
    lib.formatCurrency = function(value) {
        var parts = parseFloat(value).toFixed(lib.config.store_currency_decimals).split('.');
        var out = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, lib.config.store_currency_thousands_sep);

        if (parts[1] != null) {
            out += lib.config.store_currency_dec_point + parts[1];
        }

        return lib.config.store_currency_symbol + out + lib.config.store_currency_suffix;
    };

    // convert a form into a useful hash (supports radios etc)
    lib.serializeForm = function(form) {
        var elem, values, _i, _len, _ref;
        values = {};
        _ref = $(form).serializeArray();
        for (_i = 0, _len = _ref.length; _i < _len; _i++) {
            elem = _ref[_i];
            values[elem.name] = elem.value;
        }
        return values;
    };

    // find a sku for the current form state
    lib.matchSku = function(formdata) {
        // check we have the necessary product data
        var item, match, mod_id, opt_id, product, _i, _len, _ref, _ref1;
        product = lib.products[formdata.entry_id];
        if (!product) {
            return false;
        }

        // if there is only one sku, return it
        if (product.stock.length === 1) {
            return product.stock[0];
        }

        // loop through modifiers, and match them to skus
        _ref = product.stock;
        for (_i = 0, _len = _ref.length; _i < _len; _i++) {
            item = _ref[_i];
            match = true;

            // are there any modifiers which don't match this sku?
            _ref1 = item.opt_values;
            for (mod_id in _ref1) {
                opt_id = _ref1[mod_id];
                if (formdata["modifiers_" + mod_id] !== opt_id.toString()) {
                    match = false;
                    break;
                }
            }
            // found the correct sku
            if (match) {
                return item;
            }
        }
        return false;
    };

    // calculate the price for the current form state
    lib.calculatePrice = function(formdata) {
        // check we have the necessary product data
        var product = lib.products[formdata.entry_id];
        if (!product) {
            return false;
        }

        var price = parseFloat(product.price);

        // add any applicable modifiers
        for (var i = 0; i < product.modifiers.length; i++) {
            var modifier = product.modifiers[i];
            var opt_value = formdata["modifiers_" + modifier.product_mod_id];
            if (opt_value) {
                for (var j = 0; j < modifier.options.length; j++) {
                    var option = modifier.options[j];
                    if (option.product_opt_id == opt_value && option.opt_price_mod) {
                        price += parseFloat(option.opt_price_mod);
                    }
                }
            }
        }

        return price;
    };

    // update magic product classes
    lib.updateSku = function() {
        var form, formdata, in_stock, price, price_str, sku, skudata, stock_level;
        sku = stock_level = "";
        in_stock = true;

        // find the currently selected sku
        formdata = lib.serializeForm(this.form);
        skudata = lib.matchSku(formdata);
        if (skudata) {
            sku = skudata.sku;
            if (skudata.track_stock.toString() === "1") {
                stock_level = skudata.stock_level;
                if (stock_level <= 0) {
                    in_stock = false;
                }
            }
        }

        // update the classes
        form = $(this.form);
        $(".store_product_sku", form).val(sku).text(sku).trigger("change");
        $(".store_product_stock_level, .store_product_stock", form).val(stock_level).text(stock_level).trigger("change");
        $(".store_product_in_stock", form).toggle(in_stock);
        $(".store_product_out_of_stock", form).toggle(!in_stock);

        // calculate the current price
        price = lib.calculatePrice(formdata);
        if (price !== false) {
            price_str = lib.formatCurrency(price);
            $(".store_product_price_val, .store_product_price_inc_tax_val", form).val(price).text(price).trigger("change");
            $(".store_product_price, .store_product_price_inc_tax", form).val(price_str).html(price_str).trigger("change");
        }
    };

    // dynamically link country to state select
    $.fn.bindStateSelect = function(selector) {
        return this.change(function() {
            // remove all but first entry
            var country, select, state, state_id, _ref;
            select = $(selector);
            select.html(select.find('option[value=""]:not([data-empty])'));
            if (country = lib.countries[$(this).val()]) {
                _ref = country.states;
                for (state_id in _ref) {
                    state = _ref[state_id];
                    select.append('<option value="' + state_id + '">' + state.name + '</option>');
                }
            }
            if (select.children().size() === 0) {
                select.append('<option data-empty></option>');
            }
            return select.trigger('change');
        });
    };

    // register change handlers
    $(function() {
        if (lib.products) {
            $(document).delegate('.store_product_form [name^="modifiers"]:not(:radio)', 'change', lib.updateSku).delegate('.store_product_form [name^="modifiers"]:radio', 'click', lib.updateSku);
            $('.store_product_form input:first').each(lib.updateSku);
        }
        if (lib.countries) {
            $("select[name=billing_country]").bindStateSelect("select[name=billing_region], select[name=billing_state]");
            $("select[name=shipping_country]").bindStateSelect("select[name=shipping_region], select[name=shipping_state]");
            return $("select.store_country_select").bindStateSelect("select.store_state_select");
        }
    });
}).call(jQuery);
