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


require_once('routeros_api.class.php');
// https://github.com/BenMenking/routeros-api


// -------------------------
// БАЗОВЫЕ НАСТРОЙКИ СКРИПТА
// BASE SETTINGS
// -------------------------

$uselog = true; // // Используем лог? | Use log? true | false
$log_path = 'mt2Fvpn.log'; // Путь к файлу лога | Log file path
$host = 'https://yourwebsite.com/'; // Адрес по которому доступен данный скрипт | Full address that this script awalible

// МАССИВ ДАННЫХ ВСЕХ РОУТЕРОВ/VPN-шлюзов | ROUTERS DATA ARRAY USED AS VPN-SERVERS
$ruid_data = array(
    // пароль в md5, глобальный ip-адрес, логин входа на роутер, пароль, SMS-шлюз через который происходит отправка SMS
    // password in md5, global ip-address, mikrotik login, password, SMS-gateway-key will be use to send sms
    '#ROUTERLOGIN#' => array('mdpass' => '#ROUTER_MD5_PASSWORD#',
                          'ip' => 'XXX.XXX.X.X',
                          'login' => '#ROSAPI_LOGIN#',
                          'password' => '#PASSWORD#',
                          'smsgw' => array(
                                           // Если необходимо отправлять SMS рандомно с нескольких шлюзов (снижение нагрузки на модем)
                                           // If you want send SMS randomly from any SMS-gateways, add one here (low modem load)
                                           0 => 'SMS_gw1')
                          )
    );

// МАССИВ ДАННЫХ РОУТЕРОВ ИСПОЛЬЗУЕМЫХ В КАЧЕСТВЕ SMS-ШЛЮЗОВ | ROUTERS DATA ARRAY WILL BE USED TO SEND AUTHERIZATION SMS
$SMS_gateway = array(
    // ip-адрес шлюза (глобальный или локальный если в одной сети с сервером), логин, пароль, порт USB-модема, канал USB-модема
    // ip-address (global or local if used in one local network with server), login, password, USB-modem port, USB-modem channel
    'SMS_gw1' => array('ip' => 'XXX.XXX.X.X', 'login' => '#ROSAPI_LOGIN#', 'password' => '#PASSWORD#', 'port' => '#USB_PORT#', 'channel' => '#USB_CHANNEL#')
    );


// -------------------------
// ВХОДНЫЕ ПРОВЕРКИ ЗАПРОСОВ
// INPUT DATA CHECK
// -------------------------
if (!$_REQUEST) die('Request error'); // если запроса нет – сброс | if request free - reset
if (!$_REQUEST['ruid']) die('Request error'); // если не указан ruid - сбросс | if ruid not isset – reset
if (!array_key_exists($_REQUEST['ruid'], $ruid_data)) die('Request error'); // если роутер не существует – сброс | if router does not exist – reset
if ($_REQUEST['auth']) autorize(); // если запрос на авторизацию, то пускаем без пароля и проверяем авторизацию | if auth request allow without password
if (!ruid_auth()) die('Request error'); // проверяем пароль роутера для отправки SMS | check ruid password
if ($_REQUEST['tel']) send_authcode(); // если задан номер телефона, отправляем SMS | if phone number isset – sending SMS

// ПРОВЕРКА НА НАЛИЧИЕ РОУТЕРА В СПИСКЕ РАЗРЕШЕННЫХ и пароля авторизации
// CHECK FOR ROUTER (ruid) IS IN ALLOWED LIST
function ruid_auth() {
  global $ruid_data;
  if (!$_REQUEST['pass']) return false; // если пароль не задан – сброс | if password not set - reset
  // проверяем md5-хэш пароля | check password md5-hash
  if (md5($_REQUEST['pass']) == $ruid_data[$_REQUEST['ruid']]['mdpass']) return true;
  return false;
}

// ---------------------------------------------------------
// ФУНКЦИЯ ОТПРАВКИ ССЫЛКИ С КОДОМ АВТОРИЗАЦИИ ЧЕРЕЗ ROS API
// SEND AUTHERIZATION SMS VIA ROS API FUNCTION
// ---------------------------------------------------------
function send_authcode() {
  global $ruid_data;
  global $host;

  // Выбираем шлюз рандомно | Get random SMS-gateway
  $sms_gw = $ruid_data[$_REQUEST['ruid']]['smsgw'][array_rand($ruid_data[$_REQUEST['ruid']]['smsgw'], 1)]; // данные sms-шлюза
  // генерируем код авторизации и добавляем его в массив | Generate auth-code and add to REQUEST array
  $_REQUEST['authcode'] = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ123456789'), 0, 5);
  // Формируем сообщение отправляемое пользователю – только eng или транслит
  // Create message that will be sent to user
  $message = rawurlencode('To authorize user '.$_REQUEST['tel'].' connection open '.$host.'?ruid='.$_REQUEST['ruid'].'&auth='.$_REQUEST['authcode']);

  // ЕСЛИ ИСПОЛЬЗУЕМ ПЛАТНЫЙ SMS ШЛЮЗ
  // IF USE PAID SMS CENTER GATEWAY
  if ($sms_gw == "PAY") UsePAYsmsc($message);
    
  // подключаем класс | connect class
  $API = new RouterosAPI();
  // если подключились отправляем SMS | if connected successfully - sending message
  if ($API->connect($SMS_gateway[$sms_gw]['ip'], $SMS_gateway[$sms_gw]['login'], $SMS_gateway[$sms_gw]['password'])) {
      // Команда отправки SMS | SMS send command
      $ARRAY = $API->comm("/tool/sms/send", array(
      "port"=>$SMS_gateway[$sms_gw]['port'],
      "channel"=>$SMS_gateway[$sms_gw]['channel'],
      "phone-number"=>$_REQUEST['tel'],
      "message"=>"To autorize user ".$_REQUEST['tel']." connection open – ".$host."?ruid=".$_REQUEST['ruid']."&auth=".$_REQUEST['authcode'],));
      // если отправка не удалась и получили ошибку модема, то выполняем сброс питания usb для перезагрузки модема
      // Checking if send failed and error message return, will make usb power-reset to restart modem
      if($ARRAY['!trap']) {
        $API->comm("/system/routerboard/usb/power-reset");
        // логируем запросы | save log
        // добавляем в массив сообщение об ошибке | Add error to array and save all array to log
        $_REQUEST['ERROR'] = $ARRAY['!trap'][0]['message'];
        writelog('MODEM ERROR');
        die('Stop with error: '.$ARRAY['!trap'][0]['message'].' Making power reset of usb-port');}
  }

  $API->disconnect();
  // логируем запросы | save log
  writelog('SEND AUTH CODE');
  die($_REQUEST['authcode']);
}


// --------------------------------------------------------------------
// ФУНКЦИЯ АВТОРИЗАЦИИ ЧЕРЕЗ ROS API – УДАЛЕНИЕ ИЗ ADDRESS-LIST
// AUTHERIZATION USING ROS API FUNCTION – DELETE LIST FROM ADDRESS-LIST
// --------------------------------------------------------------------
function autorize() {
  global $ruid_data;
  // подключаем класс | connect class
  $API = new RouterosAPI();
  if ($API->connect($ruid_data[$_REQUEST['ruid']]['ip'], $ruid_data[$_REQUEST['ruid']]['login'], $ruid_data[$_REQUEST['ruid']]['password'])) {
    // если подключились отправляем команду | if connected successfully - sending command
    $API->write('/ip/firewall/address-list/print', false);
    $API->write('?comment='.$_REQUEST['auth'], false);
    $API->write('=.proplist=.id');
    // получаем ответ | get response
    $ARRAYS = $API->read();
    // ЕСЛИ ЗАПИСЬ НЕ СУЩЕСТВУЕТ В АДРЕС-ЛИСТЕ - СБРОС
    // IF NO RECORD IN FIREWALL ADDRESS_LIST – RESET
    if (!$ARRAYS[0]) die('Request error');
    // удаляем запись | delete firewall address-list
    $API->write('/ip/firewall/address-list/remove', false);
    $API->write('=.id=' . $ARRAYS[0]['.id']);
    $READ = $API->read();
  }
  $API->disconnect();
  // логируем запросы | save log
  writelog('AUTHERIZATION');

  // ИНФОРМИРУЕМ ПОЛЬЗОВАТЕЛЯ ОБ УСПЕШНОЙ АВТОРИЗАЦИИ
  // SHOW SUCCESS PAGE TO USER
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

// ОТПРАВКА SMS ЧЕРЕЗ ПЛАТНЫЙ СЕРВИС ОТПРАВКИ СООБЩЕНИЙ (ЗНАЧЕНИЕ smsgw В $ruid_data ДОЛЖНО БЫТЬ УСТАНОВЛЕНО 0 => "PAY")
// SEND SMS VIA PAID SMS CENTER GATEWAY (smsgw VALUE IN $ruid_data SHOULD BE INSTALLED 0 => "PAY")
function UsePAYsmsc($message) {
    // для примера использован smsc.ru | for example i use smsc.ru
    $smsc_login = '#SMSCLOGIN#';
    $smsc_pass = '#SMSCPASSWORD#';
    $smsc_sendername = '#SMSCSENDERNAME#'; //  если используется | if need
    // Отправляем SMS | SEND SMS
    $sms_send = file_get_contents('https://smsc.ru/sys/send.php?login='.$smsc_login.'&psw='.$smsc_pass.'&phones='.$_REQUEST['tel'].'&mes='.$message.'&sender='.$smsc_sendername.'&flash=0');
    if (strpos($sms_send, 'OK') !== false) {
      die($_REQUEST['authcode']);
    }
      die($_REQUEST['Send SMS error']);
};

// ------------------------
// ФУНКЦИЯ СОХРАНЕНИЯ ЛОГОВ
// SAVE LOG FUNCTION
// ------------------------
function writelog($type) {
  global $uselog;
  global $log_path;
  // если $uselog == false – прерываем
  if (!$uselog) return;
  // удаляем пароль из массива перед сохранением лога | remove ruid password from array before saving log
  unset($_REQUEST['pass']);
  // сохраняем лог | save log
  $fp = fopen($log_path, 'a');
  fputs($fp, "\n\nDate = " . date("Y-m-d H:i:s")."\n");
  fputs($fp, $type."\n");
  fputs($fp, print_r($_REQUEST, true));
  fclose($fp);
}

?>
