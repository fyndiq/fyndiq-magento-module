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
                    'You can get the notes <a href="' + urlpath0 + 'fyndiq/files/deliverynote.pdf" target="_blank">here</a>');
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
    // get child category of a category
    get_childcategories: function(category, callback) {
        FmCtrl.call_service('get_childcategory', {'category': category}, function (status, categories) {
            if (status == 'success') {
                var category = $j('.fm-category-tree li.active');
                $j('.fm-category-tree.childtree').each(function() {
                    if(! $j(this).find('li.active').length) {
                        $j(this).remove();
                    }
                })

                category.after(tpl['category-child-tree']({
                    'categories': categories
                }));
            }
            if (callback) {
                callback(status);
            }
        });
    },

    update_product: function (product, percentage, callback) {
        FmCtrl.call_service('update_product', {'product': product, 'percentage': percentage}, function (status) {
            if (callback) {
                callback(status);
            }
        });
    },

    products_delete: function (products, callback) {

        FmCtrl.call_service('delete_exported_products', {'products': products}, function (status, data) {
            if (status == 'success') {
                FmGui.show_message('success', messages['products-deleted-title'],
                    messages['products-deleted-message']);
            }
            if (callback) {
                callback();
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
                    FmCtrl.get_childcategories(category_id, function() {
                        $j('#categoryname').text($j('.fm-category-tree li.active a').text());
                        FmGui.hide_load_screen();
                    });
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
        //Taking care of the price changes when changing percentage
        var savetimeout;
        $j(document).on('keyup', '.fm-product-list tr .prices .fyndiq_price .inputdiv .fyndiq_dicsount', function () {
            var discount = $j(this).val();
            var product = $j(this).parent().parent().parent().parent().attr('data-id');

            if (discount > 100) {
                discount = 100;
            }

            //Count and show new price based on new percentage
            var price = $j(this).parent().parent().parent().parent().attr('data-price');
            var field = $j(this).parent().parent().find('.price_preview_price');

            var counted = price - ((discount / 100) * price);
            if (isNaN(counted)) {
                counted = price;
            }
            field.text(counted.toFixed(2));

            //Showing state and call ajax to save new percentage after 1 second.
            clearTimeout(savetimeout);
            var ajaxdiv = $j(this).parent().parent().find('#ajaxFired');
            ajaxdiv.html('Typing...').show();
            savetimeout = setTimeout(function () {
                FmCtrl.update_product(product, discount, function (status) {
                    if (status == "success") {
                        ajaxdiv.html('Saved').delay(1000).fadeOut();
                    }
                    else {
                        ajaxdiv.html('Error').delay(1000).fadeOut();
                    }
                });
            }, 1000);
        });

        // when clicking select all products checkbox, set checked on all product's checkboxes
        $j(document).on('click', '#select-all', function (e) {
            if ($j(this).is(':checked')) {
                $j(".fm-product-list tr .select input").each(function () {
                    $j(this).prop("checked", true);
                });
                $j('.fm-product-list-controls #delete-products').removeClass('disabled');
                $j('.fm-product-list-controls #delete-products').addClass('red');

            } else {
                $j(".fm-product-list tr .select input").each(function () {
                    $j(this).prop("checked", false);
                });
                $j('.fm-product-list-controls #delete-products').removeClass('red');
                $j('.fm-product-list-controls #delete-products').addClass('disabled');
            }
        });

        $j(document).on('click','.fm-product-list > tr', function() {
             var red = false;
             $j('.fm-product-list > tr').each(function (k, v) {
                 var active = $j(this).find('.select input').prop('checked');
                 if (active) {
                     red = true;
                 }
             });
             if(red) {
                 $j('.fm-product-list-controls #delete-products').removeClass('disabled');
                 $j('.fm-product-list-controls #delete-products').addClass('red');
             }
             else {
                 $j('.fm-product-list-controls #delete-products').removeClass('red');
                 $j('.fm-product-list-controls #delete-products').addClass('disabled');
             }
        });

        // when clicking the export products submit buttons, export products
        $j(document).on('click', '.fm-product-list-controls #export-products', function (e) {
            e.preventDefault();

            var products = [];

            // find all products
            $j('.fm-product-list > tr').each(function (k, v) {

                // check if product is selected
                var active = $j(this).find('.select input').prop('checked');
                if (active) {

                    // store product id and combinations
                    var price = $j(this).find("td.prices > div.price > input").val();
                    var fyndiq_percentage = $j(this).find(".fyndiq_price .inputdiv .fyndiq_dicsount").val();
                    products.push({
                        'product': {
                            'id': $j(this).data('id'),
                            'fyndiq_precentage': fyndiq_percentage
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
        $j(document).on('click', '.fm-product-list-controls #delete-products', function (e) {
            e.preventDefault();
            if($j(this).hasClass( "disabled" )) {
                return;
            }
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

        $j(document).on('click', 'div.pages > ol > li > a', function (e) {
            e.preventDefault();

            FmGui.show_load_screen(function () {
                var page = $j(e.target).attr('data-page');
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
        $j(document).on('click', '#getdeliverynote', function (e) {
            var orders = [];
            e.preventDefault();
            $j('.fm-orders-list > tr').each(function (k, v) {
                // check if product is selected
                var active = $j(this).find('.select input').prop('checked');
                if (active) {
                    orders.push($j(this).data('fyndiqid'));
                }
            });
            if(orders.length > 0) {
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
            }
        });
    }
};