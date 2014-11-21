"use strict";
var $j = jQuery.noConflict();
var FmCtrl = {
    call_service: function(action, args, callback) {
        $j.ajax({
            type: 'POST',
            url: module_path + '?isAjax=true',
            data: {'action': action, 'args': args, 'form_key': window.FORM_KEY},
            dataType: 'json'
        }).always(function(data) {
            var status = 'error';
            var result = null;
            if ($j.isPlainObject(data) && ('fm-service-status' in data)) {
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
                $j('.fm-category-tree-container').html(tpl['category-tree']({
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
        $j('.fm-category-tree li').removeClass('active');

        FmCtrl.call_service('get_products', {'category': category_id}, function(status, products) {
            if (status == 'success') {
                $j('.fm-product-list-container').html(tpl['product-list']({
                    'module_path': module_path,
                    'products': products
                }));

                // set active class on selected category
                $j('.fm-category-tree li[data-category_id='+category_id+']').addClass('active');

                // http://stackoverflow.com/questions/5943994/jquery-slidedown-snap-back-issue
                // set correct height on combinations to fix jquery slideDown jump issue
                $j('.fm-product-list .combinations').each(function(k, v) {
                    $j(v).css('height', $j(v).height());
                    $j(v).hide();
                });
            }

            if (callback) {
                callback();
            }
        });
    },
    load_exported_products: function(callback) {
        // unset active class on previously selected category
        $j('.fm-category-tree li').removeClass('active');

        FmCtrl.call_service('get_exported_products', {}, function(status, products) {
            if (status == 'success') {
                $j('.fm-exported-product-list-container').html(tpl['product-list']({
                    'module_path': module_path,
                    'products': products
                }));

                // http://stackoverflow.com/questions/5943994/jquery-slidedown-snap-back-issue
                // set correct height on combinations to fix jquery slideDown jump issue
                $j('.fm-exported-product-list .combinations').each(function(k, v) {
                    $j(v).css('height', $j(v).height());
                    $j(v).hide();
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
                var category = $j('.fm-category-tree li.active').attr('data-category_id');
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

    products_delete: function(products, callback) {

        FmCtrl.call_service('delete_exported_products', {'products': products}, function(status, data) {
            if (status == 'success') {
                FmGui.show_message('success', messages['products-deleted-title'],
                    messages['products-deleted-message']);

                // reload category to ensure that everything is reset properly
                if(callback) {
                    callback();
                }
            } else {
                if (callback) {
                    callback();
                }
            }
        });
    },

    bind_event_handlers: function() {

        // import orders submit button
        $j(document).on('click', '#fm-import-orders', function(e) {
            e.preventDefault();
            FmGui.show_load_screen();
            FmCtrl.import_orders(function() {
                FmGui.hide_load_screen();
            });
        });

        // when clicking category in tree, load its products
        $j(document).on('click', '.fm-category-tree a', function(e) {
            e.preventDefault();
            var category_id = $j(this).parent().attr('data-category_id');
            FmGui.show_load_screen(function(){
                FmCtrl.load_products(category_id, function() {
                    FmGui.hide_load_screen();
                });
            });
        });

        // when clicking select all products checkbox, set checked on all product's checkboxes
        $j(document).on('click', '#select-all', function(e) {
            if ($j(this).is(':checked')) {
                $j(".fm-product-list tr .select input").each(function () {
                    $j(this).prop("checked", true);
                });

            } else {
                $j(".fm-product-list tr .select input").each(function () {
                    $j(this).prop("checked", false);
                });
            }
        });

        // when clicking the export products submit buttons, export products
        $j(document).on('click', '.fm-product-list-controls button[name=export-products]', function(e) {
            e.preventDefault();

            var products = [];

            // find all products
            $j('.fm-product-list > tr').each(function(k, v) {

                // check if product is selected
                var active = $j(this).find('.select input').prop('checked');
                if (active) {

                    // find all combinations
                    /*var combinations = [];
                    $j(this).find('.combinations > li').each(function(k, v) {

                        // check if combination is selected, and store it
                        var active = $j(this).find('> .select input').prop('checked');
                        if (active) {
                            combinations.push({
                                'id': $j(this).data('id'),
                                'price': $j(this).data('price'),
                                'quantity': $j(this).data('quantity')
                            });
                        }
                    });*/

                    // store product id and combinations
                    var price = $j(this).find("td.prices > div.price > input").val();
                    var fyndiq_precentage = $j(this).find("td.prices > div.fyndiq_price > input").val();
                    var fyndiq_quantity = $j(this).find("td.quantities > div.fyndiq > span").text();
                    products.push({
                        'product': {
                            'id': $j(this).data('id'),
                            'name': $j(this).data('name'),
                            'image': $j(this).data('image'),
                            'description': $j(this).data('description'),
                            'price': $j(this).data('price'),
                            'fyndiq_precentage':fyndiq_precentage,
                            'fyndiq_quantity':fyndiq_quantity,
                            'quantity': $j(this).data('quantity')
                        }
                        //'combinations': combinations
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
                    /*for (var j = 0; j < product['combinations'].length; j++) {
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
                    }*/
                    // if product needs a warning, store relevant data
                        product_warnings.push({
                            'product': product
                        });
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
                    FmGui.show_modal(products,content, function(products_to_export, type) {
                        if (type == 'accept') {

                            // export the products
                            export_products(products_to_export);
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

        //Deleting selected products from export table
        $j(document).on('click', '.fm-product-list-controls button[name=delete-products]', function(e) {
            e.preventDefault();

            FmGui.show_load_screen(function() {
                var products = [];

                // find all products
                $j('.fm-product-list > tr').each(function(k, v) {

                    // check if product is selected
                    var active = $j(this).find('.select input').prop('checked');
                    if (active) {
                        // store product id and combinations
                        var price = $j(this).find("td.prices > div.price > input").val();
                        var fyndiq_precentage = $j(this).find("td.prices > div.fyndiq_price > input").val();
                        var fyndiq_quantity = $j(this).find("td.quantities > div.fyndiq > span").text();
                        products.push({
                            'product': {
                                'id': $j(this).data('id')
                            }
                        });
                    }
                });

                // if no products selected, show info message
                if (products.length == 0) {
                    FmGui.show_message('info', messages['products-not-selected-title'],
                        messages['products-not-selected-message']);

                } else {
                    FmCtrl.products_delete(products,function() {
                        FmCtrl.load_exported_products(function() {
                            FmGui.hide_load_screen();
                        });

                    });

                }
            });

        });
    }
};
