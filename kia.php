<?php

class Kia
{
    protected static $_cookieDirectory = '/tmp/';

    public static function getStatus($email, $password, $pin)
    {

        unlink(self::$_cookieDirectory.'kia_cookie.txt');

        //Init session
        $url = 'https://prd.eu-ccapi.kia.com:8080/api/v1/user/oauth2/authorize?response_type=code&state=test&client_id=fdc85c00-0a2f-4c64-bcb4-2cfb1500730a&redirect_uri=https://prd.eu-ccapi.kia.com:8080/api/v1/user/oauth2/redirect';
        self::execute($url);

        //language (not usefull but mandatory)
        $url = 'https://prd.eu-ccapi.kia.com:8080/api/v1/user/language';
        self::execute($url, 'POST', '{"lang":"en"}');

        //login
        $url = 'https://prd.eu-ccapi.kia.com:8080/api/v1/user/signin';
        $result = self::execute($url, 'POST', '{"email": "'.$email.'", "password": "'.$password.'"}');
        $code = str_replace('&state=test', '', str_replace('https://prd.eu-ccapi.kia.com:8080/api/v1/user/oauth2/redirect?code=', '', $result['redirectUrl']));

        //notification response
        $url = 'https://prd.eu-ccapi.kia.com:8080/api/v1/spa/notifications/register';
        $result = self::execute($url, 'POST', '{"pushRegId": "199360397125", "pushType": "GCM", "uuid": "fsjkqlhfjkdfsqhlkfhdkjhqsfldjhflkdshlfdhsqdf"}', ['ccsp-service-id: fdc85c00-0a2f-4c64-bcb4-2cfb1500730a', 'Content-Type: application/json;charset=UTF-8', 'Host: prd.eu-ccapi.kia.com:8080', 'Connection: Keep-Alive', 'Accept-Encoding: gzip', 'User-Agent: okhttp/3.10.0']);
        $deviceId = $result['resMsg']['deviceId'];

        //token
        $url = 'https://prd.eu-ccapi.kia.com:8080/api/v1/user/oauth2/token';
        $tokens = self::execute($url, 'POST', 'grant_type=authorization_code&redirect_uri=https://prd.eu-ccapi.kia.com:8080/api/v1/user/oauth2/redirect&code='.$code, ['Authorization: Basic ZmRjODVjMDAtMGEyZi00YzY0LWJjYjQtMmNmYjE1MDA3MzBhOnNlY3JldA==',
          'Content-Type: application/x-www-form-urlencoded',
          'Host: prd.eu-ccapi.kia.com:8080',
          'Connection: Keep-Alive',
          'Accept-Encoding: gzip',
          'User-Agent: okhttp/3.10.0',
          'grant_type: authorization_code']);

        //vehicles
        //todo : store vehicle ID to prevent this request next time
        $url = 'https://prd.eu-ccapi.kia.com:8080/api/v1/spa/vehicles';
        $vehicles = self::execute($url, 'GET', '', ['Authorization: '.$tokens['access_token'], 'ccsp-device-id: '.$deviceId]);

        //pin
        $url = 'https://prd.eu-ccapi.kia.com:8080/api/v1/user/pin';
        $pinDetails = self::execute($url, 'PUT', '{"deviceId": "'.$deviceId.'", "pin": "'.$pin.'"}', ['Authorization: Bearer '.$tokens['access_token'], 'Content-Type: application/json']);

        //refresh (to update vehicle status, if we dont do hit, next API call returns last known status)
        $url = 'https://prd.eu-ccapi.kia.com:8080/api/v2/spa/vehicles/'.$vehicles['resMsg']['vehicles'][0]['vehicleId'].'/status';
        self::execute($url, 'GET', '', ['Authorization: Bearer '.$pinDetails['controlToken'], 'ccsp-device-id: '.$deviceId, 'Content-Type: application/json']);

        //get details
        $url = 'https://prd.eu-ccapi.kia.com:8080/api/v2/spa/vehicles/'.$vehicles['resMsg']['vehicles'][0]['vehicleId'].'/status/latest';
        $vehicleDetails = self::execute($url, 'GET', '', ['Authorization: Bearer '.$pinDetails['controlToken'], 'ccsp-device-id: '.$deviceId, 'Content-Type: application/json']);


        $data = [
            'battery_charge' => $vehicleDetails['resMsg']['vehicleStatusInfo']['vehicleStatus']['evStatus']['batteryCharge'],
            'battery_status' => $vehicleDetails['resMsg']['vehicleStatusInfo']['vehicleStatus']['evStatus']['batteryStatus'],
            'battery_plugin' => $vehicleDetails['resMsg']['vehicleStatusInfo']['vehicleStatus']['evStatus']['batteryPlugin'],
            'range' => $vehicleDetails['resMsg']['vehicleStatusInfo']['vehicleStatus']['evStatus']['drvDistance'][0]['rangeByFuel']['totalAvailableRange']['value'],
            'charge_delay' => self::convertMinutesToHuman($vehicleDetails['resMsg']['vehicleStatusInfo']['vehicleStatus']['evStatus']['remainTime2']['etc2']['value']),
        ];

        return $data;
    }

    public static function execute($url, $method = 'GET', $body = '', $headers = [])
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_COOKIEJAR => self::$_cookieDirectory.'kia_cookie.txt',
            CURLOPT_COOKIEFILE => self::$_cookieDirectory.'kia_cookie.txt'
        ));

        $result = curl_exec($curl);
        $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $resultDecoded = json_decode($result, 1);
        if ($resultDecoded)
            return $resultDecoded;
        else
            return $result;

    }

    public static function convertMinutesToHuman($minutes)
    {
        $hours = (int)($minutes / 60);
        $minutes = $minutes - ($hours * 60);
        return $hours.'heures, '.$minutes.' min';
    }

}
