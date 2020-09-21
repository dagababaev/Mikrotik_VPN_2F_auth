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

class MikroTik_2FAuth
{
    protected $ROSAPI;
    protected $http_header;
    protected $host;

    protected $savelog = false;
    protected $log_array = [];
    protected $log_filepath;
    protected $ruid_array = [];
    protected $SMS_gateways;

    protected $username;
    protected $Telegram_botToken;
    protected $Telegram_chatid;
    protected $Synology_WebhookURL;
    protected $Synology_chatToken;
    protected $phone;
    protected $connection_type;

    protected $ruid;
    public $code;

    /*
     * $param = [
     *   'ruid' => @$_REQUEST['ruid'],
     *   'pass' => @$_REQUEST['pass'],
     *   'username' => @$_REQUEST['username'],
     *   'connection_type' => @$_REQUEST['connection_type'],
     *   'code' => @$_REQUEST['code'],
     *   'Telegram_botToken' => TELEGRAM_BOT_TOKEN,
     *   'Synology_WebhookURL' => SYNOLOGY_WEBHOOK_URL,
     *   'Telegram_chatid' => @$_REQUEST['telegram'],
     *   'Synology_chatToken' => @$_REQUEST['synology'],
     *   'phone' => @$_REQUEST['phone'],
     *   'savelog' => true, # true / false
     *   'log_filepath' => 'log/MikroTik_2FAuth.log'
     *   ];
     */
    public function __construct ($ruid_array, $param, $SMS_gateways = null)
    {
        $this->ROSAPI = new RouterosAPI();
        if (!$this->ROSAPI)
            http_response::set(200, ["success" => false, "description" => "RouterOS API Object required!"]);

        if (!$_REQUEST) http_response::set(404, ["success" => false, "description" => "Request is empty"]);
        $this->ruid_array = $ruid_array;

        // check ruid
        if (!isset($param['ruid']))
            http_response::set(400, ["success" => false, "description" => "Router id required"]);
        if (!array_key_exists($param['ruid'], $this->ruid_array))
            http_response::set(404, ["success" => false, "description" => "Router can't found"]);
        $this->ruid = $param['ruid'];

        $this->connection_type = $param['connection_type'];

        // check ruid password
        if ($this->connection_type == 'open') {
            if (!isset($param['pass']) || md5($param['pass']) != $this->ruid_array[$this->ruid]['mdpass'])
                http_response::set(403, ["success" => false]);
        }

        $this->code = @$param['code'];

        $this->username = $param['username'];
        $this->Telegram_botToken   = @$param['Telegram_botToken'];
        $this->Synology_WebhookURL = @$param['Synology_WebhookURL'];
        $this->Telegram_chatid     = @$param['Telegram_chatid'];
        $this->Synology_chatToken  = @$param['Synology_chatToken'];
        $this->phone               = @$param['phone'];

        $this->savelog = @$param['savelog'];
        $this->log_filepath = @$param['log_filepath'];

        $this->SMS_gateways = $SMS_gateways ? $SMS_gateways: false;

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
        $this->host = $protocol.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
    }

    public function start()
    {
        static $result;
        switch ($this->connection_type) {
            case 'open':
                $this->firewall();
                $result = $this->sendCode();
                break;
            case 'auth':
                if($this->doAuth()) $result = $this->renderTemplate('success.php');
                break;
            case 'close':
                break;
            default: http_response::set(404, ["success" => false, "description" => "Unknown connection type"]);
        }

        $this->writelog($this->connection_type);
        return $result;
    }

    public function doAuth()
    {
        $ruid = $this->ruid_array[$this->ruid];
        $ROSAPI = $this->ROSAPI;

        if ($ROSAPI->connect($ruid['ip'], $ruid['login'], $ruid['password'])) {
            // if connected successfully - sending command
            $ROSAPI->write('/ip/firewall/address-list/print', false);
            $ROSAPI->write('?comment='.$this->code, false);
            $ROSAPI->write('=.proplist=.id');
            // get response
            $response = $ROSAPI->read();
            // If no record in firewall address_list – reset
            if (!$response[0]) {
                $ROSAPI->disconnect();
                return false;
            };
            // delete firewall address-list
            $ROSAPI->write('/ip/firewall/address-list/remove', false);
            $ROSAPI->write('=.id=' . $response[0]['.id']);
            $ROSAPI->read();
        } else {
            http_response::set(200, ["success" => false, "description" => "Can't connect to router {$this->ruid}"]);
        }
        $ROSAPI->disconnect();
        return true;
    }

    public function sendCode()
    {
        $this->code = $this->genCode();
        $message = "To authorize user {$this->username} connection open {$this->host}?ruid={$this->ruid}&code={$this->code}&connection_type=auth";
        static $result;

        if ($this->Telegram_chatid) {
            $result = $this->Telegram($message);
        } elseif ($this->Synology_chatToken) {
            $result = $this->Synology($message);
        } elseif ($this->phone) {
            $result = $this->SMS($message);
        } else {
            http_response::set(404, ["success" => false, "description" => "Unknown auth method"]);
        }
        if ($result === true) {
            return $this->code;
        } else {
            http_response::set(404, ["success" => false, "description" => $result]);
        }
        return false;
    }

    public function POST($url, $message, $type = null)
    {
        switch ($type) {
            case 'json': $header[] = 'Content-Type: application/json'; break;
            default: $header[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec ($ch);
        curl_close ($ch);

        return $output;
    }

    public function Telegram($message)
    {
        if(!$this->Telegram_botToken) http_response::set(404, ["success" => false, "description" => "Telegram_botToken required in param!"]);

        $url = 'https://api.telegram.org/bot'.$this->Telegram_botToken.'/sendMessage';
        $content = '{"chat_id":"'.$this->Telegram_chatid.'", "text": "'.$message.'"}';
        $result = json_decode($this->POST($url, $content, 'json'));
        if ($result->ok !== true) http_response::set(200, ["success" => false, "description" => $result]);
        return true;
    }

    public function Synology($message)
    {
        if(!$this->Synology_WebhookURL) http_response::set(404, ["success" => false, "description" => "Synology_WebhookURL required in param!"]);

        $url = $this->Synology_WebhookURL.urlencode($this->Synology_chatToken);
        $content = 'payload='.urlencode('{"text": "'.$message.'"}');
        $result = json_decode($this->POST($url, $content));
        if ($result->success !== true) http_response::set(200, ["success" => false, "description" => $result]);
        return true;
    }

    public function SMS($message) {

        $ruid = $this->ruid_array[$this->ruid];
        $modem = $ruid['smsgw'][array_rand($ruid['smsgw'], 1)];

        $gateway = $this->SMS_gateways[$modem];
        $ROSAPI = $this->ROSAPI;

        // if connected successfully - sending message
        if ($ROSAPI->connect($gateway['ip'], $gateway['login'], $gateway['password'])) {
            // SMS send command
            $ARRAY = $ROSAPI->comm("/tool/sms/send", array(
                "port"=>$gateway['port'],
                "channel"=>$gateway['channel'],
                "phone-number"=>$this->phone,
                "message"=>$message,));
            // Checking if send failed and error message return, will make usb power-reset to restart modem
            if(isset($ARRAY['!trap'])) {
                $ROSAPI->comm("/system/routerboard/usb/power-reset");
                $error = json_encode(["success" => "false", "message" => $ARRAY['!trap'][0]['message']]);
                $ROSAPI->disconnect();
                return $error;
        }
        $ROSAPI->disconnect();
        } else {
            $error = json_encode(["success" => "false", "message" => "Can't connect to {$modem}"]);
            return $error;
        }
        return true;
    }

    public function genCode() {
        $str = 'abcdefghijklmnopqrstuvwzyz';
        $str1 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $str2 = '0123456789';
        $str3 = '()/\_-+';

        $shuffled = str_shuffle($str);
        $shuffled1 = str_shuffle($str1);
        $shuffled2 = str_shuffle($str2);
        $shuffled3 = str_shuffle($str3);

        $total = $shuffled.$shuffled1.$shuffled2.$shuffled3;
        $shuffled_final = str_shuffle($total);
        $result= substr($shuffled_final, 0, 5);

        return $result;
    }

    public function firewall() {
        foreach ($this->ruid_array as $value) {
            $result = (in_array($_SERVER['REMOTE_ADDR'], $value) === true) ? true : false;
            if($result) return true; // if found record – break
        }
        $this->writelog('FIREWALL BLOCKED IP: '.$_SERVER['REMOTE_ADDR']);
        http_response::set(403, ["success" => false, "description" => "Firewall: not allow access for IP {$_SERVER['REMOTE_ADDR']}"]);
        return false;
    }

    private function writelog($msg) {
        if (!$this->savelog) return false;
        $fp = "\nTime = " . date("Y-m-d H:i:s")."\n";
        $fp .= $msg."\n";
        $fp .= print_r($_REQUEST, true);
        if(file_put_contents($this->log_filepath, $fp, FILE_APPEND | LOCK_EX))  {
            return true;
        } else {
            return false;
        }
    }

    private function renderTemplate($page, $item = null) {

        $content = $page;
        if (file_exists('templates/'.$page)) $content = file_get_contents('templates/'.$page);
        foreach ($item as $key => $value) {
            $content = str_replace('<?=$item[\''.$key.'\'];?>', $value, $content);
        }
        return $content;
    }
}
