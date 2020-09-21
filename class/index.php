<?php
// ------------------------------------------------------------------------------
//  © Copyright (с) 2020
//  Author: Dmitri Agababaev, d.agababaev@duncat.net
//
//  Redistributions and use of source code, with or without modification, are
//  permitted that retain the above copyright notice
//
//  License: MIT
// ------------------------------------------------------------------------------

define('TELEGRAM_BOT_TOKEN', '1100052127:YER5vFiH0krj-BWN16optAycNH7hn8gne0s');
define('SYNOLOGY_WEBHOOK_URL', 'https://yourwebsite.com:5001/webapi/entry.cgi?api=SYNO.Chat.External&method=incoming&version=2&token=');
define('LOG_FILEPATH', 'log/MikroTik_2FAuth.log');

require_once('lib/http_response.class.php');
require_once('lib/routeros_api.class.php');
require_once('lib/MikroTik_2FAuth.class.php');

// Routers data array used as vpn-servers
$ruid_array = [
    '#ROUTERLOGIN#' => [
        'mdpass' => 'e5753db3df39fc52ec1490bbb5e83981',
        'ip' => '10.0.0.1',
        'login' => '#ROSAPI_LOGIN#',
        'password' => '#ROSAPI_PASS#',
        'smsgw' => ['SMS_gw1']
    ]
];
// Routers data array will be used to send auth sms
$SMS_gateways = [
    'SMS_gw1' => [
        'ip' => '10.0.0.1',
        'login' => '#ROSAPI_LOGIN#',
        'password' => '#ROSAPI_PASS#',
        'port' => '#USB_PORT#',
        'channel' => '#USB_CHANNEL#']
];

// All used parameters
$param = [
    'ruid' => @$_REQUEST['ruid'],
    'pass' => @$_REQUEST['pass'],
    'username' => @$_REQUEST['username'],
    'connection_type' => @$_REQUEST['connection_type'],
    'code' => @$_REQUEST['code'],
    'Telegram_botToken' => TELEGRAM_BOT_TOKEN,
    'Synology_WebhookURL' => SYNOLOGY_WEBHOOK_URL,
    'Telegram_chatid' => @$_REQUEST['telegram'],
    'Synology_chatToken' => @$_REQUEST['synology'],
    'phone' => @$_REQUEST['phone'],
    'savelog' => true, # true / false
    'log_filepath' => LOG_FILEPATH
];

$mtauth = new MikroTik_2FAuth($ruid_array, $param, $SMS_gateways);
print($mtauth->start());

?>
