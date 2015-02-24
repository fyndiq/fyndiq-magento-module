"use strict";
var $j = jQuery.noConflict();
var cl = function(v) {
    console.log(v)
};

// precompile handlebars partials
$j('script.handlebars-partial').each(function(k, v) {
    Handlebars.registerPartial($j(v).attr('id'), $j(v).html());
});

// precompile handlebars templates
var tpl = {};
$j('script.handlebars-template').each(function(k, v) {
    tpl[$j(v).attr('id').substring(3)] = Handlebars.compile($j(v).html());
});

$j(document).ready(function() {

    FmGui.show_load_screen(function(){
        FmCtrl.bind_event_handlers();

        // load all categories
        FmCtrl.load_categories(function() {

            // load products from second category
            var category_id = $j('.fm-category-tree a').eq(0).parent().attr('data-category_id');
            FmCtrl.load_products(category_id, 1, function() {
                $j('#categoryname').text($j('.fm-category-tree a').eq(0).text());
                FmGui.hide_load_screen();
            });
        });
    });
});
function confirmRemoval() {
    if(confirm(messages["disconnect-confirm"])) {
        FmCtrl.disconnect_account(function() {
            location.reload();
        });
    }
}