"use strict";
var $j = jQuery.noConflict();
var FmGui = {
    messages_z_index_counter: 1,

    show_load_screen: function(callback) {
        var overlay = tpl['loading-overlay']({
            'shared_path': shared_path
        });
        $j(overlay).hide().prependTo($j('body'));
        var attached_overlay = $j('.fm-loading-overlay');

        var top = $j(document).scrollTop() + 100;
        attached_overlay.find('img').css({'marginTop': top+'px'});

        attached_overlay.fadeIn(300, function() {
            if (callback) {
                callback();
            }
        });
    },

    hide_load_screen: function(callback) {
        setTimeout(function() {
            $j('.fm-loading-overlay').fadeOut(300, function() {
                $j('.fm-loading-overlay').remove();
                if (callback) {
                    callback();
                }
            });
        }, 200);
    },

    show_message: function(type, title, message) {
        var overlay = $j(tpl['message-overlay']({
            'shared_path': shared_path,
            'type': type,
            'title': title,
            'message': message
        }));

        overlay.hide()
            .css({'z-index': 999+FmGui.messages_z_index_counter++})
            .prependTo($j('body'));

        var attached_overlay = $j('.fm-message-overlay');
        attached_overlay.slideDown(300);

        attached_overlay.find('.close').bind('click', function(){
            $j(this).parent().slideUp(200, function() {
                $j(this).remove();
            });
        });

        setTimeout(function() {
            attached_overlay.find('.close').click();
        }, 6000);
    }
};
