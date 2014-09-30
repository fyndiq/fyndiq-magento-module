<?php

class FyndiqAPIDataInvalid extends Exception{}

class FyndiqAPIConnectionFailed extends Exception{}

class FyndiqAPIPageNotFound extends Exception{}

class FyndiqAPIAuthorizationFailed extends Exception{}

class FyndiqAPITooManyRequests extends Exception{}

class FyndiqAPIServerError extends Exception{}

class FyndiqAPIBadRequest extends Exception{}

class FyndiqAPIUnsupportedStatus extends Exception{}

class FyndiqAPI
{

    public static $error_messages;

    public static function call($user_agent, $username, $token, $method, $path, $data)
    {

        self::$error_messages = array();

        $request_body = json_encode($data);

        // if json encode failed
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new FyndiqAPIDataInvalid('Error in request data');
        }

        $curl_opts = array(
            CURLOPT_USERAGENT => $user_agent,
            CURLOPT_URL => 'http://fyndiq.se:8080/api/v2/' . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array('Content-type: application/json'),
            CURLOPT_POSTFIELDS => $request_body,
            CURLOPT_VERBOSE => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $username . ':' . $token,
            #CURLOPT_SSLVERSION => 3,
            #CURLOPT_SSL_VERIFYPEER => true,
            #CURLOPT_SSL_VERIFYHOST => 2,

            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_RETURNTRANSFER => 1,
        );

        // make the call
        $ch = curl_init();
        curl_setopt_array($ch, $curl_opts);
        $response['data'] = curl_exec($ch);

        // if call failed
        if ($response['data'] === false) {
            throw new FyndiqAPIConnectionFailed('Curl error: ' . curl_error($ch));
        }

        // extract different parts of the response
        $response['http_status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response['header_size'] = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $response['header'] = substr($response['data'], 0, $response['header_size']);
        $response['body'] = substr($response['data'], $response['header_size']);

        curl_close($ch);

        if ($response['http_status'] == 404) {
            throw new FyndiqAPIPageNotFound('Not Found: ' . $path);
        }

        if ($response['http_status'] == 401) {
            throw new FyndiqAPIAuthorizationFailed('Unauthorized');
        }

        if ($response['http_status'] == 429) {
            throw new FyndiqAPITooManyRequests('Too Many Requests');
        }

        if ($response['http_status'] == 500) {
            throw new FyndiqAPIServerError('Server Error');
        }

        // pd($response['body']);

        // try to json decode repsonse data
        $result = json_decode($response['body']);

        // switch (json_last_error()) {
        //     case JSON_ERROR_NONE:
        //         echo ' - No errors';
        //     break;
        //     case JSON_ERROR_DEPTH:
        //         echo ' - Maximum stack depth exceeded';
        //     break;
        //     case JSON_ERROR_STATE_MISMATCH:
        //         echo ' - Underflow or the modes mismatch';
        //     break;
        //     case JSON_ERROR_CTRL_CHAR:
        //         echo ' - Unexpected control character found';
        //     break;
        //     case JSON_ERROR_SYNTAX:
        //         echo ' - Syntax error, malformed JSON';
        //         pd($response['body']);
        //     break;
        //     case JSON_ERROR_UTF8:
        //         echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
        //     break;
        //     default:
        //         echo ' - Unknown error';
        //     break;
        // }

        // if json_decode failed
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new FyndiqAPIDataInvalid('Error in response data');
        }

        // 400 may contain error messages intended for the user
        if ($response['http_status'] == 400) {
            $message = '';

            // if there are any error messages, save them to class static member
            if (property_exists($result, 'error_messages')) {
                $error_messages = $result->error_messages;

                // if it contains several messages as an array
                if (is_array($error_messages)) {

                    foreach ($result->error_messages as $error_message) {
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

        if (!in_array($response['http_status'], $success_http_statuses)) {
            throw new FyndiqAPIUnsupportedStatus('Unsupported HTTP status: ' . $response['http_status']);
        }

        return array(
            'status' => $response['http_status'],
            'data' => $result
        );
    }
}
