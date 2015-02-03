"use strict";
var $j = jQuery.noConflict();
var FmCtrl = {
    call_service: function (action, args, callback) {
        $j.ajax({
            type: 'POST',
            url: module_path + '?isAjax=true',
            data: {'action': action, 'args': args, 'form_key': window.FORM_KEY},
            dataType: 'json'
        }).always(function (data) {
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

    load_categories: function (callback) {
        FmCtrl.call_service('get_categories', {}, function (status, categories) {
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

    load_products: function (category_id, currentpage, callback) {
        // unset active class on previously selected category
        $j('.fm-category-tree li').removeClass('active');

        FmCtrl.call_service('get_products', {'category': category_id, 'page': currentpage}, function (status, products) {
            if (status == 'success') {
                $j('.fm-product-list-container').html("");
                $j('.fm-product-list-container').html(tpl['product-list']({
                    'module_path': module_path,
                    'products': products.products,
                    'pagination': products.pagination
                }));

                // set active class on selected category
                $j('.fm-category-tree li[data-category_id=' + category_id + ']').addClass('active');

                // http://stackoverflow.com/questions/5943994/jquery-slidedown-snap-back-issue
                // set correct height on combinations to fix jquery slideDown jump issue
                $j('.fm-product-list .combinations').each(function (k, v) {
                    $j(v).css('height', $j(v).height());
                    $j(v).hide();
                });
            }

            if (callback) {
                callback();
            }
        });
    },

    load_orders: function (currentpage, callback) {
        FmCtrl.call_service('load_orders', {'page': currentpage}, function (status, orders) {
            if (status == 'success') {
                $j('.fm-order-list-container').html("");
                $j('.fm-order-list-container').html(tpl['orders-list']({
                    'module_path': module_path,
                    'orders': orders.orders,
                    'pagination': orders.pagination
                }));
            }

            if (callback) {
                callback();
            }
        });
    },

    import_orders: function (callback) {
        FmCtrl.call_service('import_orders', {}, function (status, orders) {
            if (status == 'success') {
                FmGui.show_message('success', messages['orders-imported-title'],
                    messages['orders-imported-message']);
            }
            if (callback) {
                callback();
            }
        });
    },

    get_delivery_notes: function (orders, callback) {
        FmCtrl.call_service('get_delivery_notes', {"orders": orders}, function (status) {
            if (status == 'success') {
                FmGui.show_message('success', messages['delivery-note-imported-title'],
                    'You can get the notes <a href="' + module_path + '/fyndiq/files/deliverynote.pdf">here</a>');
            }
            if (callback) {
                callback(status);
            }
        });
    },

    export_products: function (products, callback) {
        FmCtrl.call_service('export_products', {'products': products}, function (status, data) {
            if (status == 'success') {
                FmGui.show_message('success', messages['products-exported-title'],
                    messages['products-exported-message']);

                // reload category to ensure that everything is reset properly
                var category = $j('.fm-category-tree li.active').attr('data-category_id');
                var page = $j('div.pages > ol > li.current').html();
                if (page == 'undefined') {
                    page = 1;
                }
                FmCtrl.load_products(category, page, function () {
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

    products_delete: function (products, callback) {

        FmCtrl.call_service('delete_exported_products', {'products': products}, function (status, data) {
            if (status == 'success') {
                FmGui.show_message('success', messages['products-deleted-title'],
                    messages['products-deleted-message']);

                // reload category to ensure that everything is reset properly
                if (callback) {
                    callback();
                }
            } else {
                if (callback) {
                    callback();
                }
            }
        });
    },

    disconnect_account: function (callback) {
        FmCtrl.call_service('disconnect_account', {}, function (status) {
            if (callback) {
                callback();
            }
        });
    },

    bind_event_handlers: function () {

        // when clicking category in tree, load its products
        $j(document).on('click', '.fm-category-tree a', function (e) {
            e.preventDefault();
            var category_id = $j(this).parent().attr('data-category_id');
            FmGui.show_load_screen(function () {
                FmCtrl.load_products(category_id, 1, function () {
                    FmGui.hide_load_screen();
                });
            });
        });

        $j(document).on('click', 'div.pages > ol > li > a', function (e) {
            e.preventDefault();

            var category = $j('.fm-category-tree li.active').attr('data-category_id');
            FmGui.show_load_screen(function () {
                var page = $j(e.target).attr('data-page');
                FmCtrl.load_products(category, page, function () {
                    FmGui.hide_load_screen();
                });
            });
        });

        $j(document).on('keyup', '.fm-product-list tr .prices .fyndiq_price .fyndiq_dicsount', function () {
            console.log("keyup");
            var discount = $j(this).val();
            var price = $j(this).parent().parent().parent().attr('data-price');
            var field = $j(this).parent().children('.price_preview');
            var counted = price - ((discount / 100) * price);
            if(isNaN(counted)) {
                counted = price;
            }
            if(discount > 100) {
                counted = price - ((100 / 100) * price);
            }
            field.text("Expected Price: " + counted.toFixed(2));
        });

        // when clicking select all products checkbox, set checked on all product's checkboxes
        $j(document).on('click', '#select-all', function (e) {
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
        $j(document).on('click', '.fm-product-list-controls button[name=export-products]', function (e) {
            e.preventDefault();

            var products = [];

            // find all products
            $j('.fm-product-list > tr').each(function (k, v) {

                // check if product is selected
                var active = $j(this).find('.select input').prop('checked');
                if (active) {

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
                            'fyndiq_precentage': fyndiq_precentage,
                            'fyndiq_quantity': fyndiq_quantity,
                            'quantity': $j(this).data('quantity')
                        }
                    });
                }
            });

            // if no products selected, show info message
            if (products.length == 0) {
                FmGui.show_message('info', messages['products-not-selected-title'],
                    messages['products-not-selected-message']);

            } else {

                var export_products = function (products) {
                    FmGui.show_load_screen(function () {
                        FmCtrl.export_products(products, function () {
                            FmGui.hide_load_screen();
                        });
                    });
                };

                export_products(products);

            }
        });

        //Deleting selected products from export table
        $j(document).on('click', '.fm-product-list-controls button[name=delete-products]', function (e) {
            e.preventDefault();

            FmGui.show_load_screen(function () {
                var products = [];

                // find all products
                $j('.fm-product-list > tr').each(function (k, v) {

                    // check if product is selected
                    var active = $j(this).find('.select input').prop('checked');
                    if (active) {
                        // store product id
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
                    FmGui.hide_load_screen();

                } else {
                    // delete selected products
                    FmCtrl.products_delete(products, function () {
                        // reload category to ensure that everything is reset properly
                        var category = $j('.fm-category-tree li.active').attr('data-category_id');
                        var page = $j('div.pages > ol > li.current').html();
                        if (page == 'undefined') {
                            page = 1;
                        }
                        FmCtrl.load_products(category, page, function () {
                            FmGui.hide_load_screen();
                        });

                    });

                }
            });

        });
    },
    bind_order_event_handlers: function () {
        // import orders submit button
        $j(document).on('click', '#fm-import-orders', function (e) {
            e.preventDefault();
            FmGui.show_load_screen();
            FmCtrl.import_orders(function () {
                var page = $j('div.pages > ol > li.current').html();
                FmCtrl.load_orders(page, function () {
                    FmGui.hide_load_screen();
                });
            });
        });

        // when clicking select all orders checkbox, set checked on all order's checkboxes
        $j(document).on('click', '#select-all', function (e) {
            if ($j(this).is(':checked')) {
                $j(".fm-orders-list tr .select input").each(function () {
                    $j(this).prop("checked", true);
                });

            } else {
                $j(".fm-orders-list tr .select input").each(function () {
                    $j(this).prop("checked", false);
                });
            }
        });
        $j(document).on('click', '#getdeliverynote', function () {
            var orders = [];

            $j('.fm-orders-list > tr').each(function (k, v) {
                // check if product is selected
                var active = $j(this).find('.select input').prop('checked');
                if (active) {
                    orders.push($j(this).data('fyndiqid'));
                }
            });

            FmGui.show_load_screen(function () {
                FmCtrl.get_delivery_notes(orders, function (status) {
                    FmGui.hide_load_screen();
                    if (status == 'success') {
                        var wins = window.open(urlpath0 + "fyndiq/files/deliverynote.pdf", '_blank');
                        if (wins) {
                            //Browser has allowed it to be opened
                            wins.focus();
                        }
                    }
                });
            });
        });
    }
};