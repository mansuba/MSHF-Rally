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
/*
Exp:resso Store module for ExpressionEngine
Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
*/


(function() {
    var $, StoreMultiSelect,
    __bind = function(fn, me){ return function(){ return fn.apply(me, arguments); }; };

    $ = window.jQuery;

    StoreMultiSelect = (function() {
        function StoreMultiSelect(el) {
            this.refresh = __bind(this.refresh, this);
            this.deselectItem = __bind(this.deselectItem, this);
            this.selectItem = __bind(this.selectItem, this);
            this.generateItem = __bind(this.generateItem, this);
            this.generateLists = __bind(this.generateLists, this);
            this.select = el;
            this.select.hide().after(this.generateLists());
            this.refresh();
        }

        StoreMultiSelect.prototype.generateLists = function() {
            var _this = this;
            this.items = [];
            $.each(this.select.find('option'), function(index, option) {
                return _this.items.push(_this.generateItem($(option)));
            });
            this.container = $('<div class="store-multiselect-picker"><div class="store-multiselect-left"><div class="store-multiselect-filter"><input type="text"/></div><ul/></div><div class="store-multiselect-right"><ul/></div></div>');
            this.filter = this.container.find('input').on('keyup', this.refresh);
            this.list1 = this.container.find('.store-multiselect-left ul').on('click', 'li', this.selectItem);
            this.list2 = this.container.find('.store-multiselect-right ul').on('click', 'li', this.deselectItem);
            return this.container;
        };

        StoreMultiSelect.prototype.generateItem = function(option) {
            return $('<li/>').attr('data-id', option.val()).text(option.text());
        };

        StoreMultiSelect.prototype.selectItem = function(ev) {
            var id;
            id = $(ev.currentTarget).data('id');
            this.select.find("option[value='" + id + "']").attr('selected', true);
            return this.refresh();
        };

        StoreMultiSelect.prototype.deselectItem = function(ev) {
            var id;
            id = $(ev.currentTarget).data('id');
            this.select.find("option[value='" + id + "']").attr('selected', false);
            return this.refresh();
        };

        StoreMultiSelect.prototype.refresh = function() {
            var query,
            _this = this;
            query = this.filter.val().toLowerCase();
            return $.each(this.items, function(index, item) {
                var id;
                id = item.data('id');
                if ((_this.select.find("option[value='" + id + "']")).attr('selected')) {
                    return _this.list2.append(item);
                } else if (query === '' || item.text().toLowerCase().indexOf(query) !== -1) {
                    return _this.list1.append(item);
                } else {
                    return item.detach();
                }
            });
        };

        return StoreMultiSelect;

    })();

    $.fn.storeMultiSelect = function() {
        var _this = this;
        $.each(this, function(index, el) {
            return new StoreMultiSelect($(el));
        });
        return this;
    };

    $(function() {
        return $('select.store-multiselect').storeMultiSelect();
    });

}).call(this);
(function($) {
    $.fn.serializeObject = function() {
        var json, patterns, push_counters,
        _this = this;
        json = {};
        push_counters = {};
        patterns = {
            validate: /^[a-zA-Z][a-zA-Z0-9_]*(?:\[(?:\d*|[a-zA-Z0-9_]+)\])*$/,
            key: /[a-zA-Z0-9_]+|(?=\[\])/g,
            push: /^$/,
            fixed: /^\d+$/,
            named: /^[a-zA-Z0-9_]+$/
        };
        this.build = function(base, key, value) {
            base[key] = value;
            return base;
        };
        this.push_counter = function(key) {
            if (push_counters[key] === void 0) {
                push_counters[key] = 0;
            }
            return push_counters[key]++;
        };
        $.each($(this).serializeArray(), function(i, elem) {
            var k, keys, merge, re, reverse_key;
            if (!patterns.validate.test(elem.name)) {
                return;
            }
            keys = elem.name.match(patterns.key);
            merge = elem.value;
            reverse_key = elem.name;
            while ((k = keys.pop()) !== void 0) {
                if (patterns.push.test(k)) {
                    re = new RegExp("\\[" + k + "\\]$");
                    reverse_key = reverse_key.replace(re, '');
                    merge = _this.build([], _this.push_counter(reverse_key), merge);
                } else if (patterns.fixed.test(k)) {
                    merge = _this.build([], k, merge);
                } else if (patterns.named.test(k)) {
                    merge = _this.build({}, k, merge);
                }
            }
            json = $.extend(true, json, merge);
        });
        return json;
    };
})(jQuery);
/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */





(function() {
    var $, lib;
    $ = window.jQuery;
    lib = window.ExpressoStore != null ? window.ExpressoStore : window.ExpressoStore = {};

    $(function() {
        // compile templates with underscore.js, using {{mustache}} syntax
        var compileTemplate;
        compileTemplate = function(html) {
            return _.template(html, null, {
                escape: /\{\{(.+?)\}\}/g
            });
        };

        // datepickers
        if ($.datepicker != null) {
            (function() {
                var date, dateAmPm, dateHours, dateMins, dateTimeElems, timeStr;
                $('#mainContent input.store_date').datepicker({
                    dateFormat: $.datepicker.W3C
                });
                dateTimeElems = $('#mainContent input.store_datetime');
                if (dateTimeElems.size()) {
                    date = new Date();
                    dateHours = date.getHours();
                    dateMins = date.getMinutes();
                    dateAmPm = " AM";
                    if (dateMins < 10) {
                        dateMins = "0" + dateMins;
                    }
                    if (dateHours > 11) {
                        dateHours = dateHours - 12;
                        dateAmPm = " PM";
                    }
                    timeStr = " '" + dateHours + ":" + dateMins + dateAmPm + "'";
                    return dateTimeElems.datepicker({
                        constrainInput: false,
                        dateFormat: $.datepicker.W3C + timeStr
                    });
                }
            })();
        }

        // confirmation dialogs
        $('input[data-store-confirm]').click(function() {
            return window.confirm($(this).attr('data-store-confirm'));
        });

        // sortable tables
        $('.store_table_sortable').sortable({
            items: '> tbody > tr',
            handle: '.store_sortable_handle',
            update: function() {
                var data, form;
                form = $(this).closest('form');
                data = 'sortable_ajax=1&' + form.serialize();
                return $.post(form.attr('action'), data, function(response) {
                    return $.ee_notice(response.message, {
                        type: response.type
                    });
                });
            }
        });
        // report tables
        $('.store_report_table th a').click(function(e) {
            e.stopPropagation();
        });
        $('.store_report_table th').click(function() {
            var link = $(this).find('a')[0];
            if (link) link.click();
        });

        // dashboards
        if ($('#store_dashboard_graph').size()) {
            google.load('visualization', '1.0', {
                packages: ['corechart'],
                callback: function() {
                    var chart;
                    chart = new google.visualization.AreaChart(document.getElementById('store_dashboard_graph'));
                    return chart.draw(new google.visualization.DataTable(lib.dashboardGraph), {
                        fontSize: 11,
                        backgroundColor: 'transparent',
                        chartArea: {
                            top: 0,
                            width: '100%',
                            height: '85%'
                        },
                        legend: {
                            position: 'none'
                        },
                        hAxis: {
                            slantedText: false,
                            showTextEvery: 7,
                            textStyle: {
                                color: $('.pageContents').css('color')
                            }
                        },
                        vAxis: {
                            textPosition: 'in',
                            gridlines: {
                                count: 3
                            },
                            textStyle: {
                                color: $('.pageContents').css('color')
                            }
                        },
                        lineWidth: 4,
                        pointSize: 6
                    });
                }
            });
        }

        // datatables
        $('#store_datatable_search').on('interact', function() {
            $('#store_datatable table').table('add_filter', $(this));
        });

        // orders
        $('#store_status_edit').click(function(e) {
            e.preventDefault();
            $('#store_status_edit').hide();
            $('#store_status_form').slideDown();
        });
        $('#store_status_cancel').click(function(e) {
            e.preventDefault();
            $('#store_status_edit').show();
            $('#store_status_form').slideUp();
        });

        // reports
        $('#store_report_list .store_date_range_select').change(function() {
            if ($(this).val() === 'custom_range') {
                $(this).siblings('.custom_date_range').show();
            } else {
                $(this).siblings('.custom_date_range').hide();
            }
        }).change();

        // promotions: discounts
        if ($('#discount_type').size()) {
            (function() {
                var bulkFields, itemsFields;
                itemsFields = $('#discount_purchase_qty,#discount_purchase_total').closest('tr');
                bulkFields = $('#discount_step_qty,#discount_discount_qty').closest('tr');
                return $('#discount_type').change(function() {
                    if ($(this).val() === 'items') {
                        itemsFields.show();
                        return bulkFields.hide();
                    } else {
                        itemsFields.hide();
                        return bulkFields.show();
                    }
                }).change();
            })();
        }

        // settings: statuses
        if ($('#status_color').size()) {
            $('#status_color').keyup(function() {
                return $(this).css('color', $(this).val());
            });
            $('#mainContent .store_colorswatch').click(function() {
                $('#status_color').val($(this).attr('data-color')).keyup();
                return false;
            });
        }

        // toggle field panes
        $(".store_hide_field").click(function() {
            var img;
            img = $(this).children("img");
            if (img.attr("src").indexOf("field_collapse") > 0) {
                img.attr("src", img.attr("src").replace("field_collapse", "field_expand"));
                return $(this).next(".store_field_pane").slideDown();
            } else {
                img.attr("src", img.attr("src").replace("field_expand", "field_collapse"));
                return $(this).next(".store_field_pane").slideUp();
            }
        });

        // publish field
        if ($("#store_product_field").size() > 0) {
            return (function() {
                var modifiersTable, product_modifier_template, reloadStockTable, sortableOptions, updateSortOrder;
                modifiersTable = $("#store_product_modifiers_table");

                // drag & drop reordering
                updateSortOrder = function() {
                    return $("input.store_modifiers_sort").each(function(index) {
                        return $(this).val(index);
                    });
                };

                // sortableOptions are reused when adding new modifiers
                sortableOptions = {
                    handle: '.store_option_handle',
                    placeholder: 'store_ft_placeholder',
                    forceHelperSize: true,
                    forcePlaceholderSize: true,
                    update: function() {
                        updateSortOrder();
                        return reloadStockTable();
                    }
                };

                // add sortable for existing modifiers and options
                modifiersTable.sortable($.extend({}, sortableOptions, {
                    items: '> tbody', // must be here to support dynamically added modifiers
                    handle: '.store_modifier_handle' // must be different to item handle to avoid clashes
                }));
                modifiersTable.find('.store_product_options_table tbody').sortable(sortableOptions);

                // store product modifier template and handle add modifier events
                product_modifier_template = compileTemplate($('#store_product_modifier_template').html());
                $("#store_product_modifiers_add").click(function() {
                    var mod_key, new_row, _ref, _ref1;
                    mod_key = ((_ref = $('#publishForm').serializeObject().store_product_field) != null ? (_ref1 = _ref.modifiers) != null ? _ref1.length : void 0 : void 0) || 0;
                    new_row = $(product_modifier_template({
                        mod_key: mod_key
                    })).hide();
                    $("#store_product_modifier_empty").hide();
                    modifiersTable.append(new_row);
                    new_row.fadeIn().css("display", "table-row-group");
                    updateSortOrder();
                    new_row.find('.store_product_options_table tbody').sortable(sortableOptions);
                    new_row.find("input:text:first").focus();
                    return false;
                });

                // handle add option events
                modifiersTable.on("click", "a.store_product_option_add", function() {
                    var mod_key, new_row, opt_key, _ref;
                    mod_key = parseInt($(this).attr("data-mod-key"), 10);
                    opt_key = ((_ref = $('#publishForm').serializeObject().store_product_field.modifiers[mod_key].options) != null ? _ref.length : void 0) || 0;
                    new_row = $(product_modifier_template({
                        mod_key: mod_key
                    })).find('tr.store_product_option_row').hide();
                    new_row.find('[name]').attr('name', function(index, value) {
                        return value.replace(/\[options\]\[\d+\]/, '[options][' + opt_key + ']');
                    });
                    $(this).closest("td").find(".store_product_options_table tbody").append(new_row);
                    new_row.fadeIn().css("display", "table-row");
                    updateSortOrder();
                    new_row.find("input:text:first").focus();
                    return false;
                });

                reloadStockTable = function(existing_stock) {
                    var existing_row, existing_row_key, matchExistingStockOptions, mod_key, modifier, modifiers, new_row, new_stock_options, new_tr, opt_key, option, previous_row, previous_stock_options, product, stock, stock_key, stock_opt, stock_opt_key, stock_option_template, stock_options, stock_row, stock_table, stock_template, valid_options, _i, _j, _k, _l, _len, _len1, _len2, _len3, _len4, _len5, _m, _n, _o, _p, _ref, _ref1, _ref2, _ref3, _ref4, _ref5, _ref6, _results;
                    product = $('#publishForm').serializeObject().store_product_field;

                    // collect modifier options which will contribute to stock matrix
                    modifiers = [];
                    _ref1 = (_ref = product.modifiers) != null ? _ref.slice(0) : void 0;
                    for (mod_key in _ref1) {
                        modifier = _ref1[mod_key];
                        valid_options = [];
                        if (modifier.mod_type === 'var') {
                            _ref2 = modifier.options;
                            // options with no names are ignored
                            for (opt_key in _ref2) {
                                option = _ref2[opt_key];
                                if (!option.opt_name) {
                                    continue;
                                }
                                option.product_mod_id = mod_key;
                                option.mod_name = modifier.mod_name;
                                option.product_opt_id = opt_key;
                                valid_options.push(option);
                            }
                            // sort options according to opt_order
                            valid_options.sort(function(a, b) {
                                return a.opt_order - b.opt_order;
                            });
                        }
                        // only use modifiers with valid options
                        if (valid_options.length > 0) {
                            modifier.options = valid_options;
                            modifiers.push(modifier);
                        }
                    }

                    // sort modifiers according to mod_order
                    modifiers.sort(function(a, b) {
                        return a.mod_order - b.mod_order;
                    });

                    // use cartesian product of modifiers to calculate new stock options
                    new_stock_options = [[]];
                    for (_i = 0, _len = modifiers.length; _i < _len; _i++) {
                        modifier = modifiers[_i];
                        // for each modifier iteration, new_stock_options will grow by an order of magnitude
                        previous_stock_options = new_stock_options;
                        new_stock_options = [];
                        for (_j = 0, _len1 = previous_stock_options.length; _j < _len1; _j++) {
                            previous_row = previous_stock_options[_j];
                            _ref3 = modifier.options;
                            for (_k = 0, _len2 = _ref3.length; _k < _len2; _k++) {
                                option = _ref3[_k];
                                // clone new row and add new dimension
                                new_row = previous_row.slice(0);
                                new_row.push(option);
                                new_stock_options.push(new_row);
                            }
                        }
                    }

                    // test whether all values of common properties are equal
                    matchExistingStockOptions = function(old_stock_options, new_stock_options) {
                        var new_opt, old_opt, _l, _len3, _len4, _m, _ref4, _ref5;
                        _ref4 = old_stock_options != null ? old_stock_options : [];
                        for (_l = 0, _len3 = _ref4.length; _l < _len3; _l++) {
                            old_opt = _ref4[_l];
                            _ref5 = new_stock_options != null ? new_stock_options : [];
                            for (_m = 0, _len4 = _ref5.length; _m < _len4; _m++) {
                                new_opt = _ref5[_m];
                                if (old_opt.product_mod_id === new_opt.product_mod_id && old_opt.product_opt_id !== new_opt.product_opt_id) {
                                    return false;
                                }
                            }
                        }
                        return true;
                    };
                    existing_stock = ((_ref4 = existing_stock != null ? existing_stock : product.stock) != null ? _ref4 : []).slice(0);
                    stock = [];
                    for (_l = 0, _len3 = new_stock_options.length; _l < _len3; _l++) {
                        stock_options = new_stock_options[_l];
                        // try to match some existing data for this row
                        new_row = {};
                        for (existing_row_key = _m = 0, _len4 = existing_stock.length; _m < _len4; existing_row_key = ++_m) {
                            existing_row = existing_stock[existing_row_key];
                            if (matchExistingStockOptions(existing_row.stock_options, stock_options)) {
                                new_row = $.extend({}, existing_row);
                                // don't match this row again
                                existing_stock.splice(existing_row_key, 1);
                                break;
                            }
                        }
                        new_row.stock_options = stock_options;
                        stock.push(new_row);
                    }

                    // add table headings for any modifiers with valid options
                    $('#store_product_stock thead .store_stock_opt_header').remove();
                    _ref5 = stock[0].stock_options;
                    for (_n = _ref5.length - 1; _n >= 0; _n += -1) {
                        stock_opt = _ref5[_n];
                        $('#store_product_stock thead tr').prepend('<th class="store_stock_opt_header">' + stock_opt.mod_name + '</th>');
                    }

                    // empty stock table and add new stock rows
                    stock_template = compileTemplate($('#store_stock_row_template').html());
                    stock_option_template = compileTemplate($('#store_stock_row_th_template').html());
                    stock_table = $('#store_product_stock tbody').empty();
                    _results = [];
                    for (stock_key = _o = 0, _len5 = stock.length; _o < _len5; stock_key = ++_o) {
                        stock_row = stock[stock_key];
                        new_tr = stock_template({
                            stock_key: stock_key,
                            stock: stock_row
                        });
                        new_tr = $(new_tr);
                        new_tr.find('.store_track_stock :checkbox').prop('checked', !!parseInt(stock_row.track_stock, 10));
                        _ref6 = stock_row.stock_options;
                        for (stock_opt_key = _p = _ref6.length - 1; _p >= 0; stock_opt_key = _p += -1) {
                            stock_opt = _ref6[stock_opt_key];
                            new_tr.prepend(stock_option_template({
                                stock_key: stock_key,
                                stock_opt_key: stock_opt_key,
                                stock_opt: stock_opt
                            }));
                        }
                        _results.push(stock_table.append(new_tr));
                    }
                    return _results;
                };
                modifiersTable.on("change", ".store_select_mod_type", function() {
                    $(this).closest("tr").find(".store_product_options_wrap").toggle($(this).val() === "var" || $(this).val() === "var_single_sku");
                    return reloadStockTable();
                });
                modifiersTable.on("change", ".store_input_mod_name, .store_input_opt_name", function() {
                    return reloadStockTable();
                });
                modifiersTable.on("click", "a.store_product_modifier_remove", function() {
                    $(this).closest("tbody").fadeOut(function() {
                        $(this).remove();
                        reloadStockTable();
                        if (modifiersTable.find("tbody.store_product_modifier").size() === 0) {
                            return $("#store_product_modifier_empty").show();
                        }
                    });
                    return false;
                });
                modifiersTable.on("click", "a.store_product_option_remove", function() {
                    $(this).closest("tr").fadeOut(function() {
                        $(this).remove();
                        return reloadStockTable();
                    });
                    return false;
                });
                $("#store_product_field").on("click", "td.store_ft_text", function(ev) {
                    // if input element was clicked it will already be focused
                    var inputElement;
                    if (ev.target === this) {
                        inputElement = $(this).find("input:text:enabled").first();
                        return inputElement.focus().val(inputElement.val()); // prevents highlighting text
                    }
                });
                $("#store_product_field").on("keydown", "td.store_ft_text", function(ev) {
                    if (ev.which === 13) {
                        return false;
                    }
                });
                $("#store_product_field").on("focusin", "td.store_ft_text", function() {
                    return $(this).addClass("store_ft_focus");
                });
                $("#store_product_field").on("focusout", "td.store_ft_text", function() {
                    return $(this).removeClass("store_ft_focus");
                });

                // toggle stock level enable/disable based on stock tracking option
                $("#store_product_stock .store_track_stock input:checkbox").live("change", function() {
                    var stock_level_elem;
                    stock_level_elem = $(this).closest("td").find("input:text");
                    stock_level_elem.attr("disabled", !this.checked).toggleClass("disabled", !this.checked);
                    if (this.checked) {
                        return stock_level_elem.focus();
                    }
                });
                $("#store_product_stock .checkall_stock_publish").live("change", function() {
                    return $(this).closest("table").find(".store_track_stock input:checkbox").attr("checked", this.checked).trigger("change");
                });

                // go!
                updateSortOrder();
                return reloadStockTable(lib.productStock);
            })();
        }
    });
}).call(this);
