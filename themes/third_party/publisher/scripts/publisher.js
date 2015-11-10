Publisher = {
    disable_relationships: false,
    disable_matrix: false
};

Publisher.ajax_edit = function()
{
    // @todo - detect the hash in the URI, #, and if its present, auto expand the row for the ID

    $('.ajax-edit').click(function(e)
    {
        var $ele = $(this);
        var type = $ele.data('type');
        var id = $ele.data('id');
        var group_id = $ele.data('group-id');
        var $row = $ele.closest('tr');
        var $color = $row.attr('class');

        e.preventDefault();

        // Only if the row has not been added yet.
        if ($row.next('tr.ajax-row').length > 0)
        {
            $row.next('tr.ajax-row').remove();
            return;
        }

        switch (type) {
            case 'category':
                var url = EE.publisher.ajax_get_category;
                var data = {cat_id: id, group_id: group_id};
                var msg = 'Category Saved!';
            break;
            case 'phrase':
                var url = EE.publisher.ajax_get_phrase;
                var data = {phrase_id: id};
                var msg = 'Phrase Saved!';
            break;
            default:
                // Stop all the things!
                return;
            break;
        }

        $row.after('<tr class="ajax-row '+ $color +'"><td colspan="3" class="ajax-row-td"><div class="indicator"><img src="'+ EE.PATH_CP_GBL_IMG +'loadingAnimation.gif" /></div></td></tr>');
        var $ajax_row = $row.next('tr.ajax-row');

        $ajax_row.addClass('inactive').hover(function(){
            $(this).removeClass('inactive');
        }, function(){
            $(this).addClass('inactive');
        });

        // Mmmm, nested Ajax events!
        $.ajax({
            type: "GET",
            url: url,
            data: data,
            success: function (data, status, xhr)
            {
                $ajax_row.find('td.ajax-row-td').html(data);

                Publisher.load_pill_assets($row);

                var $form = $ajax_row.find('form.ajax-form');
                var $save_url = $form.attr('action');

                $form.submit(function(e){
                    e.preventDefault();

                    var $form = $(this);
                    var $data = $form.serialize();

                    $.ajax({
                        type: "POST",
                        url: $save_url,
                        data: $data,
                        success: function (data, status, xhr)
                        {
                            Publisher.notice(data, msg);

                            $.ajax({
                                type: "GET",
                                url: EE.publisher.ajax_get_translation_status,
                                data: {type: type, id: id},
                                success: function (data, status, xhr)
                                {
                                    $row.find('.translation-status-'+ id).html(data);
                                }
                            });
                        }
                    });

                });
            }
        });
    });
}

Publisher.bind_filemanager = function(field_id)
{
    if(EE.publisher.is_assets_installed == "y")
    {
        // Assets code is initialized in Publisher_cp->load_file_manager()
        // JS events are handled below with Publisher.assets_choose_file()
        // and Publisher.assets_remove_file()
    }
    else
    {
        $('.file_field').each(function() {
            var container = $(this),
                trigger = container.find(".choose_file"),
                content_type = $(this).data('content-type'),
                directory = $(this).data('directory'),
                settings = {
                    "content_type": content_type,
                    "directory": directory
                };
        
            $.ee_filebrowser.add_trigger(trigger, $(this).find("input").attr("name"), settings, function(file, field) {
                file_field_changed(file, field, container);
            });

            container.find(".remove_file").click(function() {
                container.find("input[type=hidden]").val("");
                container.find(".file_set").hide();
                container.find(".no_file").show();
                return false;
            });
        });

        /**
         * Changes the hidden inputs, thumbnail and file name when a file is selected
         * @private
         * @param {Object} file File object with information about the file upload
         * @param {Object} field jQuery object of the field
         */
        function file_field_changed(file, field, container) 
        {
            if (file.is_image == false) {
                container.find(".file_set").show().find(".filename").html("<img src=\""+EE.PATH_CP_GBL_IMG+"default.png\" alt=\""+EE.PATH_CP_GBL_IMG+"default.png\" /><br />"+file.file_name);
            } else {
                container.find(".file_set").show().find(".filename").html("<img src=\""+file.thumb+"\" /><br />"+file.file_name);
            }

            container.find("input[type=hidden]").val('{filedir_'+ file.upload_location_id + '}'+ file.file_name);
            container.find(".no_file").hide();
        }
    }
}

Publisher.assets_choose_file = function(button, event)
{
    var $container = button.closest(".file_field");
    var $file_set = $container.find('.file_set');
    var $input = $file_set.prev('input[type=hidden]');

    var kind = "any"; //field.data("content-type");
    var dirs = "all"; //field.data("directory");

    var upload_prefs = $.parseJSON(EE.publisher.upload_prefs);

    var sheet = new Assets.Sheet({
        filedirs: dirs,
        kinds: kind,
        onSelect: function(file) 
        { 
            var file = file[0];
            var file_parts = file.url.split('/');

            var file_name = file_parts.slice(-1)[0];
            var thumb_url = file.url.replace(file_name, '_thumbs/'+ file_name);

            for(dir_id in upload_prefs)
            {
                path = upload_prefs[dir_id];
                if(file.url.indexOf(path.url) !== -1)
                {
                    var directory = dir_id;
                    var file_path = file.url.replace(path.url, '{filedir_'+ dir_id +'}');
                    break;
                }
            }
                
            $file_set.removeClass("js_hide").find('.filename').append('<img src="'+ thumb_url +'" /><br />'+file_name);
            $file_set.next('.no_file').addClass('js_hide');

            $input.val(file_path);

            $file_set.find('.remove_file').click(function(){
                Publisher.assets_remove_file(button, event);
            });
        }
    });
    
    event.preventDefault();
    sheet.show();
}

Publisher.assets_remove_file = function(button, event)
{
    var $container = button.closest(".file_field");
    var $file_set = $container.find('.file_set');
    var $input = $file_set.prev('input[type=hidden]');

    $input.val('');
    $file_set.addClass("js_hide").find('.filename').html('');
    $file_set.next('.no_file').removeClass('js_hide');

    event.preventDefault();
}

Publisher.notice = function(type, message) 
{
    if (type == 'success')
    {
        $.ee_notice(message, {"type" : 'success'});
        window.setTimeout(function(){$.ee_notice.destroy()}, 4000);  
    }
    else
    {
        $.ee_notice('Error saving data.', {"type" : 'failure'});
    }
}

Publisher.resizable = function()
{
    $('.resizable').autosize();
}

Publisher.disable_fields = function(field)
{
    var $selector = field ? field : $(".playa, .publish_relationship");

    if (this.disable_matrix)
    {
        // Matrix field with rows?
        $(".matrix-tr-header a").remove();
        $("table.matrix").next(".matrix-add").remove();

        // Matrix field with no rows?
        $(".matrix-norows:visible").each(function(){
            var $norows = $(this);
            var $field = $norows.closest("table.matrix");
        });

        // Grid?
        $('.grid_button_add, .grid_button_delete, .grid_link_add').remove();
    }

    if (this.disable_relationships)
    {
        $selector.each(function(){
            Publisher.add_field_cover($(this));
        });

        $(".publish_rel select").each(function(){
            var $field = $(this);
            var value = $field.val();
            var name = $field.attr("name");

            // Stop if its already disabled
            if ($field.attr("disabled") == "disabled")
            {
                return;
            }

            $field.attr("disabled", "disabled");
            $field.after('<input type="hidden" name="'+ name +'" value="'+ value +'" />');
        });
    }
}

Publisher.add_field_cover = function($field)
{
    var $holder = $field.parent();

    var height = $field.height();
    var width = $field.width();
    var top = $holder.css("paddingTop");
    var left = $holder.css("paddingLeft");

    // If its already added, grab it instead of adding another div
    if ($field.prev('.disabled-relationship').length > 0)
    {
        var $cover = $field.prev('.disabled-relationship');
    }
    else
    {
        var $cover = $('<div class="disabled-relationship"></div>');
    }

    $holder.css("position", "relative");

    // Native Zero Wing
    if ($field.hasClass('publish_relationship'))
    {
        $field.css('position', 'relative');

        $cover.prependTo($field)
            .height(height + 3)
            .width(width);
    }
    // Playa and/or Matrix
    else
    {
        $cover.insertBefore($field)
            .height(height)
            .width(width);

        if ($field.hasClass("playa-dp")) {
            $cover.css({marginTop: top, marginLeft: left});
        }

        if ($field.hasClass("playa-ss") || $field.hasClass("matrix")) {
            $cover.width( width + 2 );
            $cover.height( height + 2 );
        }
    }
}

$(function(){

    Publisher.ajax_edit();

    // Disable the fields on page load
    Publisher.disable_fields();

    // On tab click, take care of fields not shown on the first tab
    $('.content_tab a').bind('click', function() {
        setTimeout(function(){
            Publisher.disable_fields();
        }, 20 );
    });

    if (window.Select2 === undefined) {
        return;
    }

    // @TODO this should be organized better...
    $(".publisher-search-select").select2({placeholder: 'Search'});
    $(".publisher-search-select").on('change', function(e){
        window.location = e.val;
        window.onhashchange = showRow();
    });

    showRow();

    function showRow()
    {
        var url = $.url();

        if (url.param('module') == 'publisher' && (url.param('method') == 'phrases' || url.param('method') == 'categories'))
        {
            var id = url.attr('fragment');
            var parts = id.split('-');
            var type = parts[0];
            id = parts[1];

            $('.ajax-edit[data-id='+ id +']').click();
        }
    }
});

$.fn.publisherToolbar = function() {

    var $form = $(this);
    var $publisher_bar = $(this).find('.publisher-bar');
    var $publisher_bar_inner = $publisher_bar.find('.publisher-bar-inner');

    // Are we editing an entry, phrase, or category?
    var type = $publisher_bar.data('type');
    type = type ? type : 'entry';

    // If its a category or phrase, resize the textareas.
    if (type != 'entry') Publisher.resizable();

    var type_id = $publisher_bar.data('type-id');

    var toolbar_container = type != 'entry' ? 'tr' : '??';

    var $save = $form.find('.publisher-save-status'); // select
    var $view = $form.find('.publisher-view-status'); // select
    var $lang = $form.find('.publisher-view-language'); // select
    var $submit = $form.find('#submit_button');

    if (type == 'entry')
    {
        var $submit_container = $publisher_bar_inner.find('.publisher-submit-container');
        $submit.addClass('save').prependTo($submit_container);
    }

    $publisher_bar.delegate('.deny-approval', 'click', function(e)
    {
        var $button = $(e.target);
        var type = $button.data('type');
        var type_id = $button.data('type-id');
        var $dialog = $('#publisher-dialog-deny-approval-'+ type +'-'+ type_id);

        $dialog.dialog({
            width: 600,
            resizable: false,
            modal: true,
            autoOpen: true,
            title: 'Deny Approval',
            position: ['center', 100],
            buttons: {
                Submit: function() 
                {
                    var $dialog = $(this);
                    var $form = $dialog.find('form');
                    var post_data = $form.serialize();

                    var $container = $form.find('.publisher-dialog-deny-approval');
                    var height = $container.height();

                    $form.html('<div class="publisher-deny-approval-section"><section></section></div>');
                    var $container_inner = $('.publisher-deny-approval-section');
                    var $container_section = $container_inner.find('section');
                    $container_inner.height(height);
                    $container_section.html('<img src="'+ EE.PATH_CP_GBL_IMG +'loadingAnimation.gif" style="margin-top: 10px" />');

                    $.ajax({
                        type: "POST",
                        dataType: "json",
                        url: EE.publisher.ajax_deny_approval,
                        data: post_data,
                        success: function (data, status, xhr) 
                        {
                            $container_section.html('Email sent.');

                            setTimeout({
                                run: function() {
                                    $dialog.dialog('close');
                                    $button.attr('disabled', 'disabled').addClass('disabled_field');
                                }
                            }.run, 2000);
                        },
                        error: function (xhr, status, error)
                        {
                            // console.log(xhr.responseText, status, error);
                        }
                    });
                },
                Cancel: function() 
                {
                    $(this).dialog('close');
                }
            }
        });

        e.preventDefault();
    });

    // Do we have a language selector present?
    var lang_selector = $lang.length > 0 ? true : false;

    // Set the Submit button value on load
    var publisher_save_status = $save.val();

    // If syncing drafts, change the Publish button to have more context
    if (EE.publisher.sync_drafts === true && EE.publisher.has_approval && EE.publisher.has_draft) 
    {
        if (EE.publisher.publisher_save_status == EE.publisher.PUBLISHER_STATUS_DRAFT) {
            $submit.val(EE.publisher.button_options[EE.publisher.PUBLISHER_STATUS_DRAFT]);
        } else {
            $submit.val(EE.publisher.button_options[EE.publisher.PUBLISHER_STATUS_OPEN]);
        }
    }
    else
    {
        $submit.val(EE.publisher.button_options[publisher_save_status]);          
    }

    // Set the Submit button on change
    $save.on('change', function()
    {
        publisher_save_status = $save.val();
        $submit.val(EE.publisher.button_options[publisher_save_status]);
    });

    $view.on('change', function(){
        change( $(this) );
    });

    $lang.on('change', function(){
        change( $(this) );
    });

    function change(obj)
    {
        var publisher_view_status = $view.val() || $save.val(); 
        var language = lang_selector ? $lang.val() : '';
        var href = window.location.href;

        var requested = obj.attr('name');
        var requested_val = obj.val();

        if (requested == 'publisher_view_status')
        {
            publisher_view_status = requested_val;
        }
        else if (requested == 'site_language')
        {
            language = requested_val; 
        }

        if (type != 'entry')
        {
            switch (type) {
                case 'category':
                    var url = EE.publisher.ajax_get_category;
                    var group_id = $('input.publisher-group-id').val();
                    var data = {cat_id: type_id, group_id: group_id, publisher_view_status: publisher_view_status};
                break;
                case 'phrase':
                    var url = EE.publisher.ajax_get_phrase;
                    var data = {phrase_id: type_id, publisher_view_status: publisher_view_status};
                break;
                default:
                    // Stop all the things!
                    return;
                break;
            }

            var $row = $form.closest(toolbar_container).find('.ajax-form-wrapper');

            $.ajax({
                type: "GET",
                url: url,
                data: data,
                success: function (data, status, xhr)
                {
                    $row.html(data).find('input, textarea').animateHighlight();

                    if (publisher_view_status == 'draft')
                    {
                        $form.closest(toolbar_container).find('.publisher-hidden').show();
                        $form.closest(toolbar_container).find('.publisher-indicator').hide();
                        $form.closest(toolbar_container).find('.publisher-bar').addClass('draft');
                    }
                    else
                    {
                        $form.closest(toolbar_container).find('.publisher-hidden').hide();
                        $form.closest(toolbar_container).find('.publisher-indicator').show();
                        $form.closest(toolbar_container).find('.publisher-bar').removeClass('draft');
                    }
                }
            });

            // Stop here, anything after is for entries on the publish page.
            return;
        }

        // Cleanup the URL, make sure we don't get duplicate key/value pairs and cause redirect loops
        if (lang_selector && href.indexOf('lang_id') != -1) 
        {
            href = href.replace(/&lang_id=(\d+)/, '&lang_id='+ language);
        } 
        else if (lang_selector)
        {
            // Make sure # is always at the end.
            if (href.indexOf('#') != -1)
            {
                href = href.replace(/\#/, '&lang_id='+ language +'#');
            }
            else
            {
                href = href +'&lang_id='+ language;
            }
        }

        if(href.indexOf('publisher_status') != -1) 
        {
            href = href.replace(/&publisher_status=(\w+)/, '&publisher_status='+ publisher_view_status);
        } 
        else 
        {
            // Make sure # is always at the end.
            if (href.indexOf('#') != -1)
            {
                href = href.replace(/\#/, '&publisher_status='+ publisher_view_status +'#');
            }
            else
            {
                href = href +'&publisher_status='+ publisher_view_status;
            }
        }

        if (publisher_view_status != "")
        {
            var height = $publisher_bar_inner.height();

            // Explicitly set the height to the current height before the image replaces the content
            $publisher_bar_inner.css({
                'height': height
            });

            setTimeout({
                run: function() {
                    $publisher_bar_inner.css({
                        'textAlign': 'center'
                    }).html('<img src="'+ EE.PATH_CP_GBL_IMG +'loadingAnimation.gif" style="margin-top: 10px" />'); 
                }
            }.run, 250);

            setTimeout({
                run: function() {
                    window.location = href; 
                }
            }.run, 500);
        }  
    }

    var $publisher_bar = $(".publisher-bar");

    var top = $publisher_bar.offset().top - parseFloat($publisher_bar.css("margin-top").replace(/auto/, 0));

    if ($publisher_bar.data('type') == 'entry')
    {
        $(window).scroll(function (event) 
        {
            publisher_bar_resize();
            
            var y = $(this).scrollTop();

            if (y >= top) 
            {
                $publisher_bar.addClass("fixed");
            
                // if($publisher_placeholder.length == 0)
                // {
                //     $publisher_bar.after("<div class=\"publisher-placeholder\"></div>");
                //     $publisher_placeholder.css("width", $(".publisher-bar").width()).css("height", $publisher_bar.height());
                // }
            } 
            else 
            {
                $publisher_bar.removeClass("fixed");
                $publisher_bar.css("width", "auto");
                $publisher_bar.css("height", "auto");
                // $publisher_placeholder.remove();
            }
        });
    }

    var publisher_bar_resize = function() {
        $publisher_bar.css("width", $(".publisher-bar").width());
        $publisher_bar.css("height", $(".publisher-bar").height());
    };
}


Publisher.show_diffs = function(data)
{
    for (var key in data)
    {
        if (data[key] != '' && data[key] != 'null')
        {
            var field_name = key == 'title' ? '#sub_hold_field_title' : '#sub_hold_'+ key.replace('_id', '');
            $(field_name).before('<div class="publisher-diff"><div class="publisher-diff-toggle"></div><div class="publisher-diff-content">'+ data[key] + '</div></div>');
        }
    }

    $('.publisher-diff-toggle').toggle(function(){
        $(this).next('.publisher-diff-content').slideDown();
    }, function(){
        $(this).next('.publisher-diff-content').slideUp();
    });
}

Publisher.has_approval = function(type)
{
    var $container = $('body');
    var width = $container.width();
    var height = $container.height();

    var publisher_dialog = $('#publisher-dialog-has-approval').dialog({
        width: 300,
        resizable: false,
        modal: true,
        autoOpen: false,
        title: 'Approval Exists',
        position: ['center', 100],
        buttons: {
            Ok: function() {
                publisher_dialog.dialog('close');
            },
        }
    });

    publisher_dialog.dialog('open');
}

Publisher.load_pill_assets = function(parent)
{
    if (Publisher.pill_is_installed)
    {
        // If we have a parent, then its an ajax generated bit o html
        if (parent)
        {
            var $view = parent.next('.ajax-row').find(".publisher-view-status");
            var $save = parent.next('.ajax-row').find(".publisher-save-status");
        }
        else
        {
            var $view = $(".publisher-view-status");
            var $save = $(".publisher-save-status");
        }

        var publisher_view_status = new ptPill($view);
        var publisher_save_status = new ptPill($save);
        
        $view.next(".pt-pill").addClass("publisher-pt-pill").find("li:first-child").remove();
        $save.next(".pt-pill").addClass("publisher-pt-pill").find("li:first-child").remove();
    }
}

Publisher.preview_templates = function()
{
    $selects = $('.preview_template_append');

    $selects.change(function(){
        var val = $(this).val();
        var $custom_field = $(this).next('input');

        if (val == 'custom') {
            $custom_field.show();
        } else {
            $custom_field.hide();
        }
    });

    $selects.change();
}

$.fn.animateHighlight = function(highlightColor, duration) {
    var highlightBg = highlightColor || "#FAF9D7";
    var animateMs = duration || 1500;
    var originalBg = this.css("backgroundColor");
    this.stop().css("background-color", highlightBg).animate({backgroundColor: originalBg}, animateMs);
};
