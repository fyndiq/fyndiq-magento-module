"use strict";
var $j = jQuery.noConflict();
var FmGui = {
    messages_z_index_counter: 1,

    show_load_screen: function(callback) {
        var overlay = tpl['loading-overlay']({
            'module_path': module_path
        });
        $j(overlay).hide().prependTo($j('.fm-container'));
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
            'module_path': module_path,
            'type': type,
            'title': title,
            'message': message
        }));

        overlay.hide()
            .css({'z-index': 999+FmGui.messages_z_index_counter++})
            .prependTo($j('.fm-container'));

        var attached_overlay = $j('.fm-message-overlay');
        attached_overlay.slideDown(300);

        attached_overlay.find('.close').bind('click', function(){
            $j(this).parent().slideUp(200, function() {
                $j(this).remove();
            });
        });

        setTimeout(function() {
            attached_overlay.find('.close').click();
        }, 12000);
    },

    show_modal: function(products, content, callback) {
        var overlay = $j(tpl['modal-overlay']({}));

        // attach the overlay to the general container
        overlay.hide().prependTo($j('.fm-container'));
        var attached_overlay = $j('.fm-modal-overlay');

        // insert the content
        attached_overlay.find('.content').html(content);

        // scroll to the top of the page
        $j('html,body').animate({
            'scrollTop': 0
        });

        // fade in the overlay
        attached_overlay.fadeIn(300, function() {

            // when it's visible, set the container height to 200 longer than the content,
            // to ensure that long content does not get hidden
            var new_height = (attached_overlay.find('.content').height()+200);
            if ($j('.fm-container').height() < new_height) {
                $j('.fm-container').css({'height': new_height+'px'});
            }
        });


        // attach close button event handler
        attached_overlay.find('.controls button').bind('click', function(e) {
            e.preventDefault();
            attached_overlay.remove();

            // set container height back to default auto height so it continues adapting to its content
            $j('.fm-container').css({'height': 'auto'});

            //get the updated variables for products
            attached_overlay.find("li").each(function(index) {
                var name = $j(this).find('.data .title input').val();
                var price = $j(this).find('.final-price input').val();

                products[index]["product"]["name"] = name;
                products[index]["product"]["price"] = price;
            });

            if (callback) {
                callback(products, $j(this).attr('data-modal-type'));
            }
        });
    }
};
