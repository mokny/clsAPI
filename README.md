# clsAPI - PHP Server and Client
A basic API Server & Client class that handles requests via POST / JSON data exchange.

## Server
This is an example of a basic API Server that will respond with a JSON Array.
```php
<?php
require_once("clsAPI.php");

//Initiate new API Object
$API = new APIServer();

//Optionally verify the UserAgent of the client. Non matching User-Agent will get 403 Forbidden
$API->VerifyUserAgent("APIClient");

//React to the method thats requested by the client
switch ($API->Method()) {
	case "SomeMethod":
		$API->Push("SomeVar", "Some Content"); //Add a simple variable to the response
		$API->Push("SomeArray", array("a","b")); //Add an array to the response
		$API->Push("ReceivedName", $API->request['name']); //Add the name from the request to the response (see client example)
		$API->Push("ReceivedEmail", $API->request['email']); //Add the email from the request to the response (see client example)

		$API->Respond(true); //Send the response to the client. true indicates that the request was successful.
		break;

	default:
		$API->Respond(false, "Unknown Method (" . $API->Method() . ")"); //If no method matches, add false and define an error message.
		break;
}


?>
```
### API Key Verification
API Keys can be stored in two ways. Either as file on the server or as a MySQL row. API Key verification should be done directly after instancing the class or (if applicable) after the User-Agent verification.

#### Verification via File
If you chose this option, a directory called ./apikeys will be automatically created. Make sure that your script has the appropriate writing permissions.
```php
$API->VerifyAPIKey("FILE");
```
#### Creating an API-Key via File
In this request a new API Key will be created. It has a limit of 100 requests per hour. Set 100 to 0 if you want to grant unlimited requests.
```php
$apikey = $API->CreateAPIKey("FILE",100);
```
#### Verification via MySQL
First initialize the MySQL Connection/Link. You can pass an existin connection to the class, or initiate a new connection by adding your credentials. If it dows not exist, a new table will be created called apikeys
```php
//Existing connection
$mySqlConnection = mysqli_connect("server", "username", "password", "database");
$API->MYSQLConnection($mySqlConnection);

//New connection
$API->MYSQLConnection(false, "database", "username", "password", "server");
```
Verification of the API-Key
```php
$API->VerifyAPIKey("MYSQL");
```
Creating an API-Key
In this request a new API Key will be created. It has a limit of 100 requests per hour. Set 100 to 0 if you want to grant unlimited requests.
```php
$apikey = $API->CreateAPIKey("MYSQL",100);
```


## Client
This is the client that queries the server above. 
```php
<?php
require_once("clsAPI.php");

//Create an APIClient Object and define the Server-URL and the User-Agent. The API Key is optional and must only be used if the server requires that authentication
$API = new APIClient("http://example.tld/server.php", "APIClient", "MyTopSecretAPIKey");

//Add a variables to your request
$API->Push("name", "John Doe");
$API->Push("email", "johndoe@example.tld");

//Send the request to the server and define the method, so that the server knows what to do with your data
$API->Request("SomeMethod");

if (!$API->Error()) {
    echo $API->Time() . "<br />"; //Get the timestamp from the server
    var_dump($API->response); //Dump the response from the server

} else {
    echo "API-Error: " . $API->Error(); //If there was an error, print the error message
}
?>
```
### Debugging the server response
This will print the entire server response to the screen
```php
echo $API->DebugOut();
```

### Getting information about API Throtteling
Depending of the server configuration, API-Users can be throttled to a limited amount of requests per hour. If you need these information, you can get the values here:
```php
    echo $API->GetThrottleLimitPerHour() . "<br>";
    echo $API->GetThrottleRequestsRemaining() . "<br>";
    echo $API->GetThrottleNextReset() . "<br>";
```
This is only applicable if the server requires an API-Key.

## Compatible clients
* This API Client
* APIRequest (Swift/iOS/XCode) https://github.com/mokny/APIRequest
