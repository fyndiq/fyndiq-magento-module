/* global $, FmGui, module_path, shared_path, messages, tpl, urlpath0 */

var FmCtrl = {

    $categoryName: null,

    call_service: function (action, args, callback) {
        'use strict';
        $j.ajax({
            type: 'POST',
            url: module_path + '?isAjax=true',
            data: {'action': action, 'args': args, 'form_key': window.FORM_KEY},
            dataType: 'json'
        }).always(function (data) {
            var status = 'error';
            var result = null;
            if ($j.isPlainObject(data) && ('fm-service-status' in data)) {
                if (data['fm-service-status'] === 'error') {
                    FmGui.show_message('error', data.title, data.message);
                }
                if (data['fm-service-status'] === 'success') {
                    status = 'success';
                    result = data.data;
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

    load_categories: function (category_id, $container, callback) {
        'use strict';
        FmCtrl.call_service('get_categories', {category_id: category_id}, function (status, categories) {
            if (status === 'success') {
                $j(tpl['category-tree']({
                    categories: categories
                })).appendTo($container);
            }

            if ($j.isFunction(callback)) {
                callback();
            }
        });
    },

    load_products: function (category_id, page, callback) {
        'use strict';
        // unset active class on previously selected category
        $j('.fm-category-tree li').removeClass('active');

        FmCtrl.call_service('get_products', {category: category_id, page: page}, function (status, products) {
            if (status === 'success') {
                $j('.fm-product-list-container').html(tpl['product-list']({
                    module_path: module_path,
                    shared_path: shared_path,
                    products: products.products,
                    pagination: products.pagination
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

    update_product: function (product, percentage, callback) {
        'use strict';
        FmCtrl.call_service('update_product', {product: product, percentage: percentage}, function (status) {
            if (callback) {
                callback(status);
            }
        });
    },

    load_orders: function (page, callback) {
        'use strict';
        FmCtrl.call_service('load_orders', {page: page}, function (status, orders) {
            if (status === 'success') {
                $j('.fm-order-list-container').html(tpl['orders-list']({
                    module_path: module_path,
                    shared_path: shared_path,
                    orders: orders.orders,
                    pagination: orders.pagination
                }));
            }

            if (callback) {
                callback();
            }
        });
    },

    import_orders: function (callback) {
        'use strict';
        FmCtrl.call_service('import_orders', {}, function (status, date) {
            if (status === 'success') {
                FmGui.show_message('success', messages['orders-imported-title'],
                    messages['orders-imported-message']);
            }
            if (callback) {
                callback(date);
            }
        });
    },

    export_products: function (products, callback) {
        'use strict';
        FmCtrl.call_service('export_products', {products: products}, function (status, data) {
            if (status === 'success') {
                FmGui.show_message('success', messages['products-exported-title'],
                    messages['products-exported-message']);

                // reload category to ensure that everything is reset properly
                var category = $j('.fm-category-tree li.active').attr('data-category_id');
                FmCtrl.load_products(category, function () {
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
        'use strict';
        FmCtrl.call_service('delete_exported_products', {products: products}, function (status) {
            if (status === 'success') {
                FmGui.show_message('success', messages['products-deleted-title'],
                    messages['products-deleted-message']);
            }
            if (callback) {
                callback();
            }
        });
    },

    updateCategoryName: function (name) {
        'use strict';
        if (FmCtrl.$categoryName === null) {
            FmCtrl.$categoryName = $j('#categoryname');
        }
        FmCtrl.$categoryName.html(name);
    },

    bind_event_handlers: function () {
        'use strict';
        // import orders submit button
        $j(document).on('submit', '.fm-form.orders', function (e) {
            e.preventDefault();
            FmGui.show_load_screen();
            FmCtrl.import_orders(function () {
                FmGui.hide_load_screen();
            });
        });

        // When clicking category in tree, load its products
        $j(document).on('click', '.fm-category-tree a', function (e) {
            var $li = $j(this).parent();
            var categoryName = $j(this).text();
            e.preventDefault();
            var category_id = parseInt($li.attr('data-category_id'), 10);
            FmGui.show_load_screen(function () {
                if (!$li.data('expanded')) {
                    FmCtrl.load_categories(category_id, $li, function () {
                        $li.data('expanded', true);
                        FmCtrl.load_products(category_id, function () {
                            FmCtrl.updateCategoryName(categoryName);
                            FmGui.hide_load_screen();
                        });
                    });
                } else {
                    FmCtrl.load_products(category_id, function () {
                        FmCtrl.updateCategoryName(categoryName);
                        FmGui.hide_load_screen();
                    });
                }
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

        // when clicking select all products checkbox, set checked on all product's checkboxes
        $j(document).on('click', '#select-all', function () {
            if ($j(this).is(':checked')) {
                $j('.fm-product-list tr .select input').each(function () {
                    $j(this).prop('checked', true);
                    $j('.fm-delete-products').removeClass('disabled').addClass('red');
                });

            } else {
                $j('.fm-product-list tr .select input').each(function () {
                    $j(this).prop('checked', false);
                    $j('.fm-delete-products').removeClass('red').addClass('disabled');
                });
            }
        });

        // When clicking select on one product, check if any other is select and make delete button red.
        $j(document).on('click', '.fm-product-list > tr', function () {
            var red = false;
            $j('.fm-product-list .select input').each(function () {
                var active = $j(this).prop('checked');
                if (active) {
                    red = true;
                }
            });
            if (red) {
                $j('.fm-delete-products').removeClass('disabled').addClass('red');
            }
            else {
                $j('.fm-delete-products').removeClass('red').addClass('disabled');
            }
        });

        var savetimeout;
        $j(document).on('keyup', '.fyndiq_dicsount', function () {
            var discount = parseFloat($j(this).val());
            var $product = $j(this).closest('.product');
            var product_id = $product.attr('data-id');

            if (discount > 100) {
                discount = 100;
            }
            else if (discount < 0) {
                discount = 0;
            }

            var price = $product.attr('data-price');
            var field = $j(this).closest('.prices').find('.price_preview_price');

            var counted = price - ((discount / 100) * price);
            if (isNaN(counted)) {
                counted = price;
            }

            field.text(counted.toFixed(2));

            clearTimeout(savetimeout);
            var ajaxdiv = $j(this).parent().parent().find('#ajaxFired');
            ajaxdiv.html('Typing...').show();
            savetimeout = setTimeout(function () {
                FmCtrl.update_product(product_id, discount, function (status) {
                    if (status === 'success') {
                        ajaxdiv.html('Saved').delay(1000).fadeOut();
                    }
                    else {
                        ajaxdiv.html('Error').delay(1000).fadeOut();
                    }
                });
            }, 1000);
        });

        // when clicking the export products submit buttons, export products
        $j(document).on('click', '.fm-export-products', function (e) {
            e.preventDefault();

            var products = [];

            // find all products
            $j('.fm-product-list > tr').each(function () {

                // check if product is selected
                var active = $j(this).find('.select input').prop('checked');
                if (active) {


                    // store product id and combinations
                    var price = $j(this).find('td.prices > div.price > input').val();
                    var fyndiq_percentage = $j(this).find('.fyndiq_dicsount').val();
                    products.push({
                        product: {
                            id: $j(this).data('id'),
                            fyndiq_percentage: fyndiq_percentage
                        }
                    });
                }
            });

            // if no products selected, show info message
            if (products.length === 0) {
                FmGui.show_message('info', messages['products-not-selected-title'],
                    messages['products-not-selected-message']);

            } else {

                // helper function that does the actual product export
                var export_products = function (products) {
                    FmGui.show_load_screen(function () {
                        FmCtrl.export_products(products, function () {
                            FmGui.hide_load_screen();
                        });
                    });
                };

                // export the products
                export_products(products);
            }
        });

        //Deleting selected products from export table
        $j(document).on('click', '.fm-delete-products', function (e) {
            e.preventDefault();
            if ($j(this).hasClass('disabled')) {
                return;
            }
            FmGui.show_load_screen(function () {
                var products = [];

                // find all products
                $j('.fm-product-list .select input:checked').each(function () {
                    products.push({
                        product: {
                            id: $j(this).parent().parent().data('id')
                        }
                    });
                });

                // if no products selected, show info message
                if (products.length === 0) {
                    FmGui.show_message('info', messages['products-not-selected-title'],
                        messages['products-not-selected-message']);
                    FmGui.hide_load_screen();

                } else {
                    // delete selected products
                    FmCtrl.products_delete(products, function () {
                        // reload category to ensure that everything is reset properly
                        var category = $j('.fm-category-tree li.active').attr('data-category_id');
                        var page = parseInt($j('div.pages > ol > li.current').html(), 10) || 1;
                        FmCtrl.load_products(category, page, function () {
                            FmGui.hide_load_screen();
                        });

                    });

                }
            });

        });
    },
    bind_order_event_handlers: function () {
        'use strict';
        // import orders submit button
        $j(document).on('click', '#fm-import-orders', function (e) {
            e.preventDefault();
            FmGui.show_load_screen();
            FmCtrl.import_orders(function (time) {
                $j('#fm-order-import-date').html(
                    tpl['order-import-date-content']({
                        module_path: module_path,
                        shared_path: shared_path,
                        import_time: time
                    }));
                var page = parseInt($j('div.pages > ol > li.current').html(), 10) || 1;
                FmCtrl.load_orders(page, function () {
                    FmGui.hide_load_screen();
                });
            });
        });

        // when clicking select all orders checkbox, set checked on all order's checkboxes
        $j(document).on('click', '#select-all', function () {
            if ($j(this).is(':checked')) {
                $j('.fm-orders-list tr .select input').each(function () {
                    $j(this).prop('checked', true);
                });

            } else {
                $j('.fm-orders-list tr .select input').each(function () {
                    $j(this).prop('checked', false);
                });
            }
        });

        $j(document).on('click', '.getdeliverynote', function (e) {
            if ($j('.fm-orders-list > tr .select input:checked').length === 0) {
                e.preventDefault();
                FmGui.show_message('info', messages['orders-not-selected-title'],
                    messages['orders-not-selected-message']);
            }
        });
    }
};
