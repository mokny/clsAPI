<?php

/*
API Server
*/

class APIServer {
    private $defaults = array();
    private $response = array();

    public $request = array();

    function __construct() {
        $this->request = $_POST;
    }

    function VerifyUserAgent($useragent) {
        if ($_SERVER["HTTP_USER_AGENT"] != $useragent) {
            header("HTTP/1.0 403 Forbidden");
            die();
        }    
    }

    function Method() {
        return $this->request['METHOD'];
    }

    function Push($key, $data) {
        $this->response[$key] = $data;
    }

    function Remove($key) {
        unset($this->response[$key]);
    }

    function Respond($request_success, $error_msg = "") {
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
    private $result = false;
    private $timestamp = 0;
    private $error = false;
    private $request = array();

    public $response = array();

    function __construct($url, $user_agent = null) {
        $this->apiurl = $url;
        $this->user_agent = $user_agent;
    }

    function Push($key, $data) {
        $this->request[$key] = $data;
    }

    function Request($method, $timeout = 60) {  
        $data = $this->request;
        $data['METHOD'] = $method;     

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
            $this->serverresponse = json_decode($json, true);
            $this->result = $this->serverresponse['RESULT'];
            $this->timestamp = $this->serverresponse['TIMESTAMP'];
            $this->response = $this->serverresponse['DATA'];
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
}

?>
