<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 03/09/14
 * Time: 10:33
 */
require_once(dirname(dirname(__FILE__)) . '/includes/messages.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/api.php');
class FmHelpers {

    public static function api_connection_exists() {
        $ret = false;
        if(FmConfig::getBool('username') AND FmConfig::getBool('apikey')) {
            $ret = true;
        }
        return $ret;
    }

    public static function all_settings_exist() {
        $ret = false;
        if(FmConfig::getBool('language') AND FmConfig::getBool('currency')) {
            $ret = true;
        }
        return $ret;
    }

    ## wrappers around FyndiqAPI
    # uses stored connection credentials for authentication
    public static function call_api($method, $path, $data=array()) {
        $username = FmConfig::get('username');
        $api_token = FmConfig::get('apikey');

        return FmHelpers::call_api_raw($username, $api_token, $method, $path, $data);
    }

    # add descriptive error messages for common errors, and re throw same exception
    public static function call_api_raw($username, $api_token, $method, $path, $data=array()) {
        $module = "FyndiqMechantMagento".FmConfig::getVersion();

        try {
            return FyndiqAPI::call($module, $username, $api_token, $method, $path, $data);

        } catch (FyndiqAPIConnectionFailed $e) {
            throw new FyndiqAPIConnectionFailed(FmMessages::get('api-network-error').': '.$e->getMessage());

        } catch (FyndiqAPIAuthorizationFailed $e) {
            throw new FyndiqAPIAuthorizationFailed(FmMessages::get('api-incorrect-credentials'));

        } catch (FyndiqAPITooManyRequests $e) {
            throw new FyndiqAPITooManyRequests(FmMessages::get('api-too-many-requests'));
        }
    }

    public static function db_escape($value) {

    }

    public static function get_shop_url($context) {

    }
}
