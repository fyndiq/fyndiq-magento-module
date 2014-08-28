"use strict";

var FmGui = {
    messages_z_index_counter: 1,

    show_load_screen: function(callback) {
        var overlay = tpl['loading-overlay']({
            'module_path': module_path
        });
        $(overlay).hide().prependTo($('.fm-container'));
        var attached_overlay = $('.fm-loading-overlay');

        var top = $(document).scrollTop() + 100;
        attached_overlay.find('img').css({'marginTop': top+'px'});

        attached_overlay.fadeIn(300, function() {
            if (callback) {
                callback();
            }
        });
    },

    hide_load_screen: function(callback) {
        setTimeout(function() {
            $('.fm-loading-overlay').fadeOut(300, function() {
                $('.fm-loading-overlay').remove();
                if (callback) {
                    callback();
                }
            });
        }, 200);
    },

    show_message: function(type, title, message) {
        var overlay = $(tpl['message-overlay']({
            'module_path': module_path,
            'type': type,
            'title': title,
            'message': message
        }));

        overlay.hide()
            .css({'z-index': 999+FmGui.messages_z_index_counter++})
            .prependTo($('.fm-container'));

        var attached_overlay = $('.fm-message-overlay');
        attached_overlay.slideDown(300);

        attached_overlay.find('.close').bind('click', function(){
            $(this).parent().slideUp(200, function() {
                $(this).remove();
            });
        });

        setTimeout(function() {
            attached_overlay.find('.close').click();
        }, 12000);
    },

    show_modal: function(content, callback) {
        var overlay = $(tpl['modal-overlay']({}));

        // attach the overlay to the general container
        overlay.hide().prependTo($('.fm-container'));
        var attached_overlay = $('.fm-modal-overlay');

        // insert the content
        attached_overlay.find('.content').html(content);

        // scroll to the top of the page
        $('html,body').animate({
            'scrollTop': 0,
        });

        // fade in the overlay
        attached_overlay.fadeIn(300, function() {

            // when it's visible, set the container height to 200 longer than the content,
            // to ensure that long content does not get hidden
            var new_height = (attached_overlay.find('.content').height()+200);
            if ($('.fm-container').height() < new_height) {
                $('.fm-container').css({'height': new_height+'px'});
            }
        });

        // attach close button event handler
        attached_overlay.find('.controls button').bind('click', function(e) {
            e.preventDefault();
            attached_overlay.remove();

            // set container height back to default auto height so it continues adapting to its content
            $('.fm-container').css({'height': 'auto'});

            if (callback) {
                callback($(this).attr('data-modal-type'));
            }
        });
    }
};
