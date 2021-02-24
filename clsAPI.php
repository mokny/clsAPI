<?php

/*
API Server
*/

class APIServer {
    private $defaults = array();
    private $response = array();
    private $sqlconnection = false;

    public $request = array();

    function __construct() {
        $this->request = $_POST;
        $this->defaults["THROTTLE_LIMIT_PER_HOUR"] = 0;
        $this->defaults["THROTTLE_REQUESTS_REMAINING"] = 0;
        $this->defaults["THROTTLE_NEXT_RESET"] = 0;
    }

    public function MYSQLConnection($sqlconnection = false, $database = "", $username = "", $password="", $server = "") {
        if (!$sqlconnection) {
            $this->sqlconnection = mysqli_connect($server, $username, $password, $database);
            if (!$this->sqlconnection) {
                $this->response = array();
                $this->Respond(false, "DATABASE ERROR (CONNECTION) " . mysqli_connect_errno());
            }
        } else {
            $this->sqlconnection = $sqlconnection;
        }
    }

    public function VerifyUserAgent($useragent) {
        if ($_SERVER["HTTP_USER_AGENT"] != $useragent) {
            header("HTTP/1.0 403 Forbidden");
            die();
        }    
    }

    public function CreateAPIKey($apikeyverificationmethod = "NONE", $requests_per_hour = 0) {
        $apikey = false;
        if ($apikeyverificationmethod == "FILE") {
            $this->InitializeFileSystem();
            $apikey = $this->GenerateAPIKey();
            file_put_contents("./apikeys/" . $apikey . ".key", "0;0;".$requests_per_hour.";0;".time());
        } elseif ($apikeyverificationmethod == "MYSQL") {
            $this->InitializeDatabase();
            $apikey = $this->GenerateAPIKey();
            mysqli_query($this->sqlconnection, 'INSERT INTO apikeys (apikey, requests_per_hour, timestamp_last_reset, timestamp_created) VALUES ("'.$apikey.'",'.(int)$requests_per_hour.', '.time().', '.time().')');
        }
        
        return $apikey;
    }

    public function VerifyAPIKey($apikeyverificationmethod = "NONE") {
        if ($apikeyverificationmethod == "FILE") {
            if (file_exists("./apikeys/".$this->FileSecureString($this->request['APIKEY']).".key")) {
                $keydata = file_get_contents("./apikeys/".$this->FileSecureString($this->request['APIKEY']).".key");

                $tokens = explode(";", $keydata);

                $requests_per_hour_done = (int)$tokens[0];
                $requests_total = (int)$tokens[1];
                $requests_per_hour = (int)$tokens[2];
                $last_reset_time = (int)$tokens[3];
                $created = (int)$tokens[4];

                if (time() - $last_reset_time > 3600) {
                    $requests_per_hour_done = 0;
                    $last_reset_time = time();
                }

                $requests_total++;
                $requests_per_hour_done++;

                file_put_contents("./apikeys/".$this->FileSecureString($this->request['APIKEY']).".key", $requests_per_hour_done . ";" . $requests_total . ";" . $requests_per_hour . ";" . $last_reset_time . ";" . $created);
                
                $this->defaults["THROTTLE_REQUESTS_REMAINING"] = $requests_per_hour - $requests_per_hour_done >= 0 ? $requests_per_hour - $requests_per_hour_done : 0;
                $this->defaults["THROTTLE_LIMIT_PER_HOUR"] = $requests_per_hour;
                $this->defaults["THROTTLE_NEXT_RESET"] = $last_reset_time + 3600;

                if ($requests_per_hour > 0) {
                    if ($requests_per_hour_done > $requests_per_hour) {
                        $this->response = array();
                        $this->Respond(false, "EXCESSIVE REQUESTS - THROTTLED - LIMIT IS " . $requests_per_hour . " REQUESTS PER HOUR");
                    }
                }
            } else {
                $this->response = array();
                $this->Respond(false, "UNKNOWN API KEY");       
            }
        } elseif ($apikeyverificationmethod == "MYSQL") {
            $this->InitializeDatabase();
            $result = mysqli_query($this->sqlconnection, 'SELECT * FROM apikeys WHERE apikey="'.mysqli_real_escape_string($this->sqlconnection, $this->request['APIKEY']).'" LIMIT 1');
            if (!$result) {
                $this->response = array();
                $this->Respond(false, "DATABASE ERROR " . mysql_error());
            } else {
                $keyrow = mysqli_fetch_assoc($result);
                if (!$keyrow) {
                    $this->response = array();
                    $this->Respond(false, "UNKNOWN API KEY");           
                } else {
                    if (time() - $keyrow['timestamp_last_reset'] > 3600) {
                        $keyrow['requests_last_hour'] = 0;
                        $keyrow['timestamp_last_reset'] = time();
                        mysqli_query($this->sqlconnection, 'UPDATE apikeys SET requests_total=requests_total+1, requests_last_hour = 0, timestamp_last_reset=' . time() . ' WHERE id=' . (int)$keyrow['id']);
                    } else {
                        mysqli_query($this->sqlconnection, 'UPDATE apikeys SET requests_total=requests_total+1, requests_last_hour = requests_last_hour + 1 WHERE id=' . (int)$keyrow['id']);
                    }
                    
                    $keyrow['requests_last_hour']++;

                    $this->defaults["THROTTLE_REQUESTS_REMAINING"] = $keyrow['requests_per_hour'] - $keyrow['requests_last_hour'] >= 0 ? $keyrow['requests_per_hour'] - $keyrow['requests_last_hour'] : 0;
                    $this->defaults["THROTTLE_LIMIT_PER_HOUR"] = $keyrow['requests_per_hour'];
                    $this->defaults["THROTTLE_NEXT_RESET"] = $keyrow['timestamp_last_reset'] + 3600;
    
                    if ($keyrow['requests_per_hour'] > 0) {
                        if ($keyrow['requests_last_hour'] >= $keyrow['requests_per_hour']) {
                            $this->response = array();
                            $this->Respond(false, "EXCESSIVE REQUESTS - THROTTLED - LIMIT IS " . $keyrow['requests_per_hour'] . " REQUESTS PER HOUR");
                        }
                    }
                }
            }
        }
    }
    private function InitializeDatabase() {
        $resultexists = mysqli_query($this->sqlconnection, 'SELECT * FROM apikeys');

        if (!$resultexists) {
            $createresult = mysqli_query($this->sqlconnection, '                
            CREATE TABLE IF NOT EXISTS `apikeys` (
              `id` int(11) NOT NULL auto_increment,
              `apikey` varchar(99) NOT NULL,
              `requests_per_hour` int(11) NOT NULL,
              `requests_last_hour` int(11) NOT NULL,
              `requests_total` int(11) NOT NULL,
              `timestamp_last_reset` int(11) NOT NULL,
              `timestamp_created` int(11) NOT NULL,
              PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;                
            ');
        }

    }
    
    private function InitializeFileSystem() {
        if (!is_dir("./apikeys")) {
            mkdir("./apikeys",0755);

            //Make sure there is no directory listing possible for the public. You also should set this option in your webserver configuration file
            file_put_contents("./apikeys/index.html","-"); 
            file_put_contents("./apikeys/index.htm","-"); 
            file_put_contents("./apikeys/index.php","-"); 
        }
    }

    private function FileSecureString($str) {
        return str_replace(array("/",".","\\"),"",$str);
    }

    private function GenerateAPIKey($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString.md5(time().uniqid());
    }

    public function Method() {
        return $this->request['METHOD'];
    }

    public function Push($key, $data) {
        $this->response[$key] = $data;
    }

    public function Remove($key) {
        unset($this->response[$key]);
    }

    public function Respond($request_success, $error_msg = "") {
        $this->defaults["RESULT"] = $request_success ? "SUCCESS" : "ERROR";
        $this->defaults["ERROR"] = $error_msg;
        $this->defaults["TIMESTAMP"] = time();
        $this->defaults["DATA"] = $this->response;

        die(json_encode($this->defaults));
    }
}

/*
* API Client
*/

class APIClient {
    private $apiurl = null;
    private $user_agent = "APIClient";
    private $serverresponse = null;
    private $rawresponse = false;
    private $result = false;
    private $timestamp = 0;
    private $error = false;
    private $request = array();
    private $apikey = false;
    private $requests_per_hour = 0;
    private $requests_remaining = 0;
    private $requests_nextreset = 0;

    public $response = array();

    function __construct($url, $user_agent = null, $apikey = false) {
        $this->apiurl = $url;
        $this->user_agent = $user_agent;
        $this->apikey = $apikey;
    }

    function Push($key, $data) {
        $this->request[$key] = $data;
    }

    function Request($method, $timeout = 60) {  
        $data = $this->request;
        $data['METHOD'] = $method;
        $data['APIKEY'] = $this->apikey == false ? '' : $this->apikey;     

        unset($this->request);

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n"
                                ."User-Agent: ".$this->user_agent."\r\n",
                'method'  => 'POST',
                'timeout' => $timeout,
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);
        $json = file_get_contents($this->apiurl, false, $context);
        if ($json === FALSE) {  

        } else {
            $this->rawresponse = $json;
            $this->serverresponse = json_decode($json, true);
            $this->result = $this->serverresponse['RESULT'];
            $this->timestamp = $this->serverresponse['TIMESTAMP'];
            $this->response = $this->serverresponse['DATA'];
            $this->requests_per_hour = $this->serverresponse['THROTTLE_LIMIT_PER_HOUR'];
            $this->requests_remaining = $this->serverresponse['THROTTLE_REQUESTS_REMAINING'];
            $this->requests_nextreset = $this->serverresponse['THROTTLE_NEXT_RESET'];
            if ($this->result != 'SUCCESS') {
                $this->error = $this->serverresponse['ERROR'];
            }
        }
    }

    function Error() {
        return $this->error;
    }

    function Time() {
        return $this->timestamp;
    }

    function GetThrottleRequestsRemaining() {
        return $this->requests_remaining;
    }

    function GetThrottleLimitPerHour() {
        return $this->requests_per_hour;
    }

    function GetThrottleNextReset() {
        return $this->requests_nextreset;
    }

    function DebugOut() {
        return print_r($this->rawresponse, true);
    }
}

?>
