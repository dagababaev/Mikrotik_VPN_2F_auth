<?php

// ------------------------------------------------------------------------------
//  © Copyright (с) 2020
//  Author: Dmitri Agababaev, d.agababaev@duncat.net
//
//  Copyright by authors for used RouterOS PHP API class in the source code files
//
//  Redistributions and use of source code, with or without modification, are
//  permitted that retain the above copyright notice
//
//  License: MIT
// ------------------------------------------------------------------------------

define('TELEGRAM_BOT_TOKEN', '1100052127:YER5vFiH0krj-BWN16optAycNH7hn8gne0s');
define('SYNOLOGY_WEBHOOK_URL', 'https://yourwebsite.com:5001/webapi/entry.cgi?api=SYNO.Chat.External&method=incoming&version=2&token=');
define('LOG_FILEPATH', 'mt2Fvpn.log'); // Log file path
define('HOST', 'https://yourwebsite.com/'); // Full address that this script awalible


require_once('routeros_api.class.php');
// https://github.com/BenMenking/routeros-api

// -------------------------
// Base settings
// -------------------------
$firewall = true; // Firewall - allow access to send SMS only for router from array $ruid_data. Use? true | false
$uselog = true; // Use log? true | false

// Routers data array used as vpn-servers
$ruid_data = array(
    // password in md5, global ip-address, mikrotik login, password, SMS-gateway-key will be use to send sms
    '#ROUTERLOGIN#' => array('mdpass' => '#ROUTER_MD5_PASSWORD#',
                          'ip' => 'XXX.XXX.X.X',
                          'login' => '#ROSAPI_LOGIN#',
                          'password' => '#PASSWORD#',
                          'smsgw' => array(
                                           // If you want send SMS randomly from any SMS-gateways, add one here (low modem load)
                                           0 => 'SMS_gw1')
                          )
    );

// Routers data array will be used to send autherization sms
$SMS_gateway = array(
    // ip-address (global or local if used in one local network with server), login, password, USB-modem port, USB-modem channel
    'SMS_gw1' => array('ip' => 'XXX.XXX.X.X', 'login' => '#ROSAPI_LOGIN#', 'password' => '#PASSWORD#', 'port' => '#USB_PORT#', 'channel' => '#USB_CHANNEL#')
    );


// ----------------
// Input data check
// ----------------
if (!$_REQUEST) die(header('HTTP/1.0 406 Not Acceptable')); // if request free - reset
if (!$_REQUEST['ruid']) die(header('HTTP/1.0 406 Not Acceptable')); // if ruid not isset – reset
if (!array_key_exists($_REQUEST['ruid'], $ruid_data)) die(header('HTTP/1.0 406 Not Acceptable')); // if router does not exist – reset
if ($_REQUEST['auth']) autorize(); // if auth request allow without password
if (!ruid_auth()) die(header('HTTP/1.0 401 Unauthorized')); // check ruid password
if (@$_REQUEST['action'] == 'down') { // if vpn-connection closed
  writelog('CONNECTION CLOSED');
  die(header('HTTP/1.0 200 ОК'));
}
if (isset($_REQUEST['phone']) || isset($_REQUEST['synology']) || isset($_REQUEST['telegram'])) send_authcode(); // if phone number isset – sending SMS

// -----------------------------------
// Check for router (ruid) is in array
// -----------------------------------
function ruid_auth() {
  global $ruid_data;
  if (!$_REQUEST['pass']) return false; // if password not set - reset
  // check password md5-hash
  if (md5($_REQUEST['pass']) == $ruid_data[$_REQUEST['ruid']]['mdpass']) return true;
  return false;
}

// -------------------------------------------
// Send autherization sms via ros api function
// -------------------------------------------
function send_authcode() {
  global $firewall;
  // If firewall == true, allow access only to routers has record in array $ruid_data
  if ($firewall) firewall();

  // Generate auth-code and add to REQUEST array
  $_REQUEST['authcode'] = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ123456789'), 0, 5);
  // Create message that will be sent to user
  $message = 'To authorize user '.$_REQUEST['username'].' connection open '.HOST.'?ruid='.$_REQUEST['ruid'].'&auth='.$_REQUEST['authcode'];

  if ($_REQUEST['telegram']) send_telegram($message); // via telegram
  if ($_REQUEST['syno_token']) send_synoChat($message); // via synology chat
  if ($_REQUEST['phone']) send_SMS($message); // via SMS to phone

  // логируем запросы | save log
  writelog('SEND AUTH CODE');
  die($_REQUEST['authcode']);
}

// -------------
// Over Telegram
// -------------

function send_telegram($message) {
  $url = 'https://api.telegram.org/bot'.TELEGRAM_BOT_TOKEN.'/sendMessage';
  // creating message
  $options = array('http' =>
      array(
          'method'  => 'POST',
          'header'  => 'Content-Type: application/json',
          'content' => '{"chat_id":"'.$_REQUEST['telegram'].'", "text": "'.$message.'"}'
      )
  );
  $content  = stream_context_create($options);
  $result = file_get_contents($url, false, $content);
}

// ------------------
// Over Synology chat
// ------------------
// info - https://www.synology.com/knowledgebase/DSM/tutorial/Collaboration/How_to_configure_webhooks_and_slash_commands_in_Chat_Integration
function send_synoChat($message) {
  $url = SYNOLOGY_WEBHOOK_URL.$_REQUEST['syno_token'];
  // creating message
  $options = array('http' =>
      array(
          'method'  => 'POST',
          'header'  => 'Content-Type: application/x-www-form-urlencoded',
          'content' => 'payload='.urlencode('{"text": "'.$message.'"}')
      )
  );
  $content  = stream_context_create($options);
  $result = file_get_contents($url, false, $content);
}


// -----------------
// SMS via usb-modem
// -----------------

function send_SMS($message) {
  global $ruid_data;
  global $SMS_gateway;
  // Get random SMS-gateway
  $sms_gw = $ruid_data[$_REQUEST['ruid']]['smsgw'][array_rand($ruid_data[$_REQUEST['ruid']]['smsgw'], 1)]; // gateway data

  if ($sms_gw == "PAY") UsePAYsmsc($message); // If use paid sms center gateway
  $API = new RouterosAPI(); // connect class
  // if connected successfully - sending message
  if ($API->connect($SMS_gateway[$sms_gw]['ip'], $SMS_gateway[$sms_gw]['login'], $SMS_gateway[$sms_gw]['password'])) {
      // SMS send command
      $ARRAY = $API->comm("/tool/sms/send", array(
      "port"=>$SMS_gateway[$sms_gw]['port'],
      "channel"=>$SMS_gateway[$sms_gw]['channel'],
      "phone-number"=>$_REQUEST['phone'],
      "message"=>$message,));
      // Checking if send failed and error message return, will make usb power-reset to restart modem
      if($ARRAY['!trap']) {
        $API->comm("/system/routerboard/usb/power-reset");
        // Save log | Add error to array and save all array to log
        $_REQUEST['ERROR'] = $ARRAY['!trap'][0]['message'];
        writelog('MODEM ERROR');
        die('Stop with error: '.$ARRAY['!trap'][0]['message'].' Making power reset of usb-port');}
  }

  $API->disconnect();
}

// --------------------------------------------------------------------
// Autherization using ros api function – delete list from address-list
// --------------------------------------------------------------------
function autorize() {
  global $ruid_data;
  // connect class
  $API = new RouterosAPI();
  if ($API->connect($ruid_data[$_REQUEST['ruid']]['ip'], $ruid_data[$_REQUEST['ruid']]['login'], $ruid_data[$_REQUEST['ruid']]['password'])) {
    // if connected successfully - sending command
    $API->write('/ip/firewall/address-list/print', false);
    $API->write('?comment='.$_REQUEST['auth'], false);
    $API->write('=.proplist=.id');
    // get response
    $ARRAYS = $API->read();
    // If no record in firewall address_list – reset
    if (!$ARRAYS[0]) die(header('HTTP/1.0 406 Not Acceptable'));
    // delete firewall address-list
    $API->write('/ip/firewall/address-list/remove', false);
    $API->write('=.id=' . $ARRAYS[0]['.id']);
    $READ = $API->read();
  }
  $API->disconnect();
  // save log
  writelog('AUTHERIZATION');

  // Show success page to user
  die('
      <!DOCTYPE html>
      <html lang="ru">
      <meta http-equiv="Content-Type" content="charset=utf-8" />
      <body style="font-family: Verdana, Arial, Helvetica, sans-serif; background-color: #282c34; color: #fff; height: 100vh; display: flex;">
        <div style="margin: auto; max-width: 50%;">
          <p style="font-size: 24pt; font-weight: bold; margin: 0 0 10px;">
            VPN-соединение установлено, можете продолжить работу<br />
          </p>
          <p style="font-size: 12pt; color: #aaa;">
            В случае недоступности сервисов обратитесь к вашему системному администратору<br />
          </p>
          <p style="font-size: 24pt; font-weight: bold; margin: 100px 0 10px;">
            VPN connection is established, you can continue to work
          </p>
          <p style="font-size: 12pt; color: #aaa;">
            If any services unavalible you must contact with your system administrator<br />
          </p>
        </div>
      </body>
      </html>
    ');
}

// -----------------------------------------------------------------------------------------------
// Send sms via paid sms center gateway (smsgw value in $ruid_data should be installed 0 => «pay»)
// -----------------------------------------------------------------------------------------------
function UsePAYsmsc($message) {
    // for example i use smsc.ru
    $smsc_login = '#SMSCLOGIN#';
    $smsc_pass = '#SMSCPASSWORD#';
    $smsc_sendername = '#SMSCSENDERNAME#'; // if need
    // SEND SMS
    $sms_send = file_get_contents('https://smsc.ru/sys/send.php?login='.$smsc_login.'&psw='.$smsc_pass.'&phones='.$_REQUEST['phone'].'&mes='.$message.'&sender='.$smsc_sendername.'&flash=0');
    if (strpos($sms_send, 'OK') !== false) {
      die($_REQUEST['authcode']);
    }
      die($_REQUEST['Send SMS error']);
};

// -------------------------------------------------------------------------
// Firewall - allow access to send SMS only for router from array $ruid_data
// -------------------------------------------------------------------------
function firewall() {
  global $uselog;
  global $log_path;
  global $ruid_data;

  $result = false;
  // serch ip in array $ruid_data
  foreach ($ruid_data as $value) {
    $result = (in_array($_SERVER['REMOTE_ADDR'], $value) === true) ? true : false;
    if($result) break; // if found record – break
  }
  // $uselog == true | save log
  if (!$result) {
    if ($uselog) {
      $fp = fopen(LOG_FILEPATH, 'a');
      fputs($fp, "\nTime = " . date("Y-m-d H:i:s")."\n");
      fputs($fp, 'FIREWALL BLOCKED ACCESS'."\n");
      fputs($fp, $_SERVER['REMOTE_ADDR']."\n");
      fclose($fp);
    }
    die(header('HTTP/1.0 403 Forbidden'));
  }
}

// -----------------
// Save log function
// -----------------
function writelog($type) {
  global $uselog;
  global $log_path;
  // $uselog == false – break
  if (!$uselog) return;
  // remove ruid password from array before saving log
  unset($_REQUEST['pass']);
  // save log
  $fp = fopen(LOG_FILEPATH, 'a');
  fputs($fp, "\nTime = " . date("Y-m-d H:i:s")."\n");
  fputs($fp, $type."\n");
  fputs($fp, print_r($_REQUEST, true));
  fclose($fp);
}

?>
