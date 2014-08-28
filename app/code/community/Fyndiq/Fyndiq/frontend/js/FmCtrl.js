"use strict";

var FmCtrl = {
    call_service: function(action, args, callback) {
        $.ajax({
            type: 'POST',
            url: module_path+'backoffice/service.php',
            data: {'action': action, 'args': args},
            dataType: 'json'
        }).always(function(data) {
            var status = 'error';
            var result = null;
            if ($.isPlainObject(data) && ('fm-service-status' in data)) {
                if (data['fm-service-status'] == 'error') {
                    FmGui.show_message('error', data['title'], data['message']);
                }
                if (data['fm-service-status'] == 'success') {
                    status = 'success';
                    result = data['data'];
                }
            } else {
                FmGui.show_message('error', messages['unhandled-error-title'],
                    messages['unhandled-error-message']);
            }
            if (callback) {
                callback(status, result);
            }
        });
    },

    load_categories: function(callback) {
        FmCtrl.call_service('get_categories', {}, function(status, categories) {
            if (status == 'success') {
                $('.fm-category-tree-container').html(tpl['category-tree']({
                    'categories': categories
                }));
            }

            if (callback) {
                callback();
            }
        });
    },

    load_products: function(category_id, callback) {
        // unset active class on previously selected category
        $('.fm-category-tree li').removeClass('active');

        FmCtrl.call_service('get_products', {'category': category_id}, function(status, products) {
            if (status == 'success') {
                $('.fm-product-list-container').html(tpl['product-list']({
                    'module_path': module_path,
                    'products': products
                }));

                // set active class on selected category
                $('.fm-category-tree li[data-category_id='+category_id+']').addClass('active');

                // http://stackoverflow.com/questions/5943994/jquery-slidedown-snap-back-issue
                // set correct height on combinations to fix jquery slideDown jump issue
                $('.fm-product-list .combinations').each(function(k, v) {
                    $(v).css('height', $(v).height());
                    $(v).hide();
                });
            }

            if (callback) {
                callback();
            }
        });
    },

    import_orders: function(callback) {
        FmCtrl.call_service('import_orders', {}, function() {
            if (callback) {
                callback();
            }
        });
    },

    export_products: function(products, callback) {
        FmCtrl.call_service('export_products', {'products': products}, function(status, data) {
            if (status == 'success') {
                FmGui.show_message('success', messages['products-exported-title'],
                    messages['products-exported-message']);

                // reload category to ensure that everything is reset properly
                var category = $('.fm-category-tree li.active').attr('data-category_id');
                FmCtrl.load_products(category, function() {
                    if (callback) {
                        callback();
                    }
                });
            } else {
                if (callback) {
                    callback();
                }
            }
        });
    },

    bind_event_handlers: function() {

        // import orders submit button
        $(document).on('submit', '.fm-form.orders', function(e) {
            e.preventDefault();
            FmGui.show_load_screen();
            FmCtrl.import_orders(function() {
                FmGui.hide_load_screen();
            });
        });

        // when clicking category in tree, load its products
        $(document).on('click', '.fm-category-tree a', function(e) {
            e.preventDefault();
            var category_id = $(this).parent().attr('data-category_id');
            FmGui.show_load_screen(function(){
                FmCtrl.load_products(category_id, function() {
                    FmGui.hide_load_screen();
                });
            });
        });

        // when clicking product's expand icon, show its combinations
        $(document).on('click', '.fm-product-list .product .expand a', function(e) {
            e.preventDefault();
            $(this).parents('li').find('.combinations').slideToggle(250);
        });

        // when clicking product's checkbox, toggle checked on all its combination's checkboxes
        $(document).on('change', '.fm-product-list .product .select input', function(e) {
            var combination_checkboxes = $(this).parents('li').find('.combinations .select input');
            combination_checkboxes.prop('checked', $(this).prop('checked'));
        });

        // when clicking a combination's checkbox, set checked on its parent product's checkbox
        $(document).on('change', '.fm-product-list .combinations .select input', function(e) {
            $(this).parents('li').find('.product .select input').prop('checked', true);
        });

        // when clicking select all products checkbox, set checked on all product's checkboxes
        $(document).on('click', '.fm-product-list-controls .select button', function(e) {
            e.preventDefault();
            if ($(this).attr('name') == 'select-all') {
                $('.fm-product-list .product .select input').prop('checked', true).change();
            }
            if ($(this).attr('name') == 'deselect-all') {
                $('.fm-product-list .product .select input').prop('checked', false).change();
            }
        });

        // when clicking the export products submit buttons, export products
        $(document).on('click', '.fm-product-list-controls button[name=export-products]', function(e) {
            e.preventDefault();

            var products = [];

            // find all products
            $('.fm-product-list > li').each(function(k, v) {

                // check if product is selected
                var active = $(this).find('.product .select input').prop('checked');
                if (active) {

                    // find all combinations
                    var combinations = [];
                    $(this).find('.combinations > li').each(function(k, v) {

                        // check if combination is selected, and store it
                        var active = $(this).find('> .select input').prop('checked');
                        if (active) {
                            combinations.push({
                                'id': $(this).data('id'),
                                'price': $(this).data('price'),
                                'quantity': $(this).data('quantity'),
                            });
                        }
                    });

                    // store product id and combinations
                    products.push({
                        'product': {
                            'id': $(this).data('id'),
                            'name': $(this).data('name'),
                            'image': $(this).data('image'),
                            'price': $(this).data('price'),
                            'quantity': $(this).data('quantity')
                        },
                        'combinations': combinations
                    });
                }
            });

            // if no products selected, show info message
            if (products.length == 0) {
                FmGui.show_message('info', messages['products-not-selected-title'],
                    messages['products-not-selected-message']);

            } else {

                // check all products for warnings
                var product_warnings = [];
                for (var i = 0; i < products.length; i++) {
                    var product = products[i];

                    var product_warning = false;
                    var lowest_price = false;
                    var highest_price = false;

                    // check each combination for warnings
                    for (var j = 0; j < product['combinations'].length; j++) {
                        var combination = product['combinations'][j];

                        // if combination price differs from product price, show warning for this product
                        if (combination['price'] != product['price']) {
                            product_warning = true;

                            // also record the highest and lowest price
                            if (combination['price'] < lowest_price || lowest_price === false) {
                                lowest_price = combination['price'];
                            }
                            if (combination['price'] > highest_price || highest_price === false) {
                                highest_price = combination['price'];
                            }
                        }
                    }

                    // if product needs a warning, store relevant data
                    if (product_warning) {
                        product_warnings.push({
                            'product': product,
                            'highest_price': highest_price,
                            'lowest_price': lowest_price
                        });
                    }
                }

                // helper function that does the actual product export
                var export_products = function(products) {
                    FmGui.show_load_screen(function() {
                        FmCtrl.export_products(products, function() {
                            FmGui.hide_load_screen();
                        });
                    });
                };

                // if there were any product warnings
                if (product_warnings.length > 0) {

                    var content = tpl['accept-product-export']({
                        'module_path': module_path,
                        'product_warnings': product_warnings
                    });

                    // show modal describing the issue, and ask for acceptance
                    FmGui.show_modal(content, function(type) {
                        if (type == 'accept') {

                            // export the products
                            export_products(products);
                        } else {
                        }
                    });

                // if there were no product warnings
                } else {

                    // export the products
                    export_products(products);
                }
            }
        });
    }
};
