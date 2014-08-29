<?php

class FmMessages {
    private static $messages = array(
        'not-authenticated-warning'=>   'You have not connected to your Fyndiq merchant account yet.',
        'uninstall-confirm'=>           'Are you sure you want to uninstall this module?',
        'empty-username-token'=>        'Please specify a Username and API token.',
        'disconnect-confirm'=>          'Are you sure you want to disconnect from your Fyndiq merchant account?',
        'account-disconnected'=>        'You have disconnected from your Fyndiq merchant account.',

        'api-network-error'=>           'Network error, cannot connect to Fyndiq API',
        'api-incorrect-credentials'=>   'Incorrect Username or API token. Please double check your provided values.',
        'api-too-many-requests'=>       'You have sent too many requests to the Fyndiq API.',

        'unhandled-error-title'=>       'Unhandled error',
        'unhandled-error-message'=>     'An unhandled error occurred. If this persists, please contact Fyndiq integration support.',

        'products-bad-params-title'=>   'Incorrect product data',

        'products-exported-title'=>     'Products exported!',
        'products-exported-message'=>   'The products you selected have been exported to Fyndiq.',

        'orders-imported-title'=>       'Orders imported!',
        'orders-imported-message'=>    'The orders from Fyndiq have been imported to Prestashop.',

        'products-not-selected-title'=>     'No products selected',
        'products-not-selected-message'=>   'You have to select at least one product to export.'
    );

    public static function get($name) {
        return self::$messages[$name];
    }

    public static function get_all() {
        return self::$messages;
    }
}
