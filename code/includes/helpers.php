<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 03/09/14
 * Time: 10:33
 */

require_once(dirname(dirname(__FILE__)) . '/includes/messages.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/api/fyndiqAPI.php');

class FyndiqAPIDataInvalid extends Exception{}

class FyndiqAPIConnectionFailed extends Exception{}

class FyndiqAPIPageNotFound extends Exception{}

class FyndiqAPIAuthorizationFailed extends Exception{}

class FyndiqAPITooManyRequests extends Exception{}

class FyndiqAPIServerError extends Exception{}

class FyndiqAPIBadRequest extends Exception{}

class FyndiqAPIUnsupportedStatus extends Exception{}

class FmHelpers
{

    public static function api_connection_exists()
    {
        $ret = false;
        if (FmConfig::getBool('username') AND FmConfig::getBool('apikey')) {
            $ret = true;
        }

        return $ret;
    }

    public static function all_settings_exist()
    {
        $ret = false;
        if (FmConfig::getBool('language') AND FmConfig::getBool('currency')) {
            $ret = true;
        }

        return $ret;
    }

    /**
     * wrappers around FyndiqAPI
     * uses stored connection credentials for authentication
     *
     * @param $method
     * @param $path
     * @param array $data
     * @param string $filename
     * @return array
     */
    public static function call_api($method, $path, $data = array())
    {
        $username = FmConfig::get('username');
        $api_token = FmConfig::get('apikey');

        return FmHelpers::call_api_raw($username, $api_token, $method, $path, $data);
    }

    /**
     * add descriptive error messages for common errors, and re throw same exception
     *
     * @param $username
     * @param $api_token
     * @param $method
     * @param $path
     * @param array $data
     * @throws FyndiqAPIUnsupportedStatus
     * @throws FyndiqAPIPageNotFound
     * @throws FyndiqAPIServerError
     * @throws FyndiqAPIBadRequest
     * @throws FyndiqAPITooManyRequests
     * @throws FyndiqAPIAuthorizationFailed
     * @throws FyndiqAPIDataInvalid
     * @return array
     */
    public static function call_api_raw($username, $api_token, $method, $path, $data = array())
    {
        $module = "FyndiqMechantMagento" . FmConfig::getVersion();

        $response = FyndiqAPI::call($module, $username, $api_token, $method, $path, $data);


        if ($response['status'] == 404) {
            throw new FyndiqAPIPageNotFound('Not Found: ' . $path);
        }

        if ($response['status'] == 401) {
            throw new FyndiqAPIAuthorizationFailed('Unauthorized');
        }

        if ($response['status'] == 429) {
            throw new FyndiqAPITooManyRequests('Too Many Requests');
        }

        if ($response['status'] == 500) {
            throw new FyndiqAPIServerError('Server Error');
        }

        // if json_decode failed
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new FyndiqAPIDataInvalid('Error in response data');
        }

        // 400 may contain error messages intended for the user
        if ($response['status'] == 400) {
            $message = '';

            // if there are any error messages, save them to class static member
            if (property_exists($response["data"], 'error_messages')) {
                $error_messages = $response["data"]->error_messages;

                // if it contains several messages as an array
                if (is_array($error_messages)) {

                    foreach ($response["data"]->error_messages as $error_message) {
                        self::$error_messages[] = $error_message;
                    }

                    // if it contains just one message as a string
                } else {
                    self::$error_messages[] = $error_messages;
                }
            }

            throw new FyndiqAPIBadRequest('Bad Request');
        }

        $success_http_statuses = array('200', '201');

        if (!in_array($response['status'], $success_http_statuses)) {
            throw new FyndiqAPIUnsupportedStatus('Unsupported HTTP status: ' . $response['status']);
        }

        return $response;
    }
}