# clsAPI
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

## Client
This is the client that queries the server above. 
```php
<?php
require_once("clsAPI.php");

//Create an APIClient Object and define the Server-URL and the User-Agent.
$API = new APIClient("http://example.tld/server.php", "APIClient");

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
