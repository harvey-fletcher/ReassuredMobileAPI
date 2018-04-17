<?php

    //First of all, we need to include all API settings
    include_once('api_settings.php');

    //Include common functions
    include_once('common_functions.php');

    //Take input from the correct header, decode it
    $_POST = json_decode(trim(urldecode(file_get_contents('php://input')), "=data"), true);

file_put_contents(__DIR__.'/data.log', json_encode($_POST));

    //Now we need to check that the user has provided all the necessary credentials to access this service
    if( !isset($_POST['email']) || !isset($_POST['password']) ){
        echo '[{"status":"403","info":"You must provide a username and password"}]';
    } else {
        //Auth using the function in common_functions.php
        auth($_POST['email'], $_POST['password']);
        $user = $GLOBALS['USER'];
    }

    //This will send a notification to all registered devices, and request their locations
    if($_POST['action'] == "RequestAll"){
        //Build the request notification
        $Notification = array(
                "data" => array(
                        "notification_type" => "locationrequest",
                        "information" => "The server has requested the location of this device",
                    ),
            );

        //Send the notification using the common_functions.php
        sendFCM(array(1), $Notification);
    }

    //This code block finds all users that are within a five mile radius of the requesting user's current location.
    if($_POST['action'] == "FindNearMe"){
        if(!isset($_POST['latitude']) || !isset($_POST['longitude'])){
            stdout(array(array("status" => 400, "info" => "You must supply latitude and longitude values")));
        }

        //This query will find all users within a five mile radius of the supplied location
        $query = "SELECT u.id, u.firstname, u.lastname,	u.last_known_lat, u.last_known_long, ( ACOS( COS( RADIANS( ".$_POST['latitude']." ) ) * COS ( RADIANS (u.last_known_lat) ) * COS ( RADIANS (u.last_known_long) - RADIANS( ".$_POST['longitude']." ) ) + SIN ( RADIANS( ".$_POST['latitude']." ) ) * SIN ( RADIANS( u.last_known_lat ) )	) * 3959 ) AS distance FROM users u WHERE u.id!=". $user['id'] ." AND display_location=1 ORDER BY distance ASC LIMIT 100";
        $result = mysqli_query($conn, $query);

        //Initialise the data array so that we can add items into it.
        $data = array();

        //For each user within five miles, add them to the dataset
        while($user_details = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            if($user_details['distance'] < 5){
                unset($user_details['distance']);
                unset($user_details['last_known_lat']);
                unset($user_details['last_known_long']);
                array_push($data, $user_details);
            }
        }

    //Return a result
//    stdout(array(array("results" => $data, "user_id" => $user['id'])));
        echo '[{"results":"'. addslashes(json_encode($data)) .'","user_id":"'.$user['id'].'"}]';
        die();
    }

    if($_POST['action'] == "SendLocation"){
        if(!isset($_POST['latitude']) || !isset($_POST['longitude'])){
            stdout(array(array("status" => "400", "info" => "You need to supply a latitude and longitude")));
        }

        //Update the user's location on the DB
        $query = "UPDATE users SET last_known_lat='" . $_POST['latitude'] . "', last_known_long='". $_POST['longitude'] ."', display_location=". $_POST['show'] ." WHERE id=" . $user['id'];
file_put_contents(__DIR__ . '/data.log', $query);
        $result = mysqli_query($conn, $query);

file_put_contents(__DIR__ . '/data.log', mysqli_error($conn));

        done();
    }

	//Sending out one of these will cause a new local conversation to be initialised on the requesting device and the receiving device.
	if($_POST['action'] == "SendNewJourneyRequest"){
		//Build the name of the requesting user
		$name = $user['firstname'] . " " . $user['lastname'];

		//Get the tokens from the database (raw)
		$results = mysqli_query($conn, "SELECT application_token FROM application_tokens WHERE user_id IN (" . $user['id'] .",". $_POST['to_user'] . ")");
		
		//An array for the tokens
		$tokens = array();

		//Get each individual tokens
		while($row = mysqli_fetch_array($results, MYSQLI_ASSOC)){
			array_push($tokens, $row['application_token']);
		}

		//Convert the array to a JSONArray
                $tokens = json_encode($tokens);

		$UDetails = mysqli_fetch_array(mysqli_query($conn, "SELECT firstname, lastname FROM users WHERE id=". $user['id']), MYSQLI_ASSOC);
		$uname = $UDetails['firstname'] . ' ' . $UDetails['lastname'];

                //Build the notification we are going to send
                $CURLdata = '{"data":{"notification_type":"message","from_user_name":"'. $uname .'","from_user_id":' . $user['id'] . ',"message_body":"Lift share conversation started!","sent_time":"'. date('H:i') .'"},"registration_ids":'. $tokens  .'}';
	
		//Send the curl request
		sendCURL($notifications_key, $CURLdata);
	}
	
	//This function will send the CURLdata via FCM;
        function sendCURL($notifications_key, $CURLdata){
                //Build the curl request command WITH the data in it
                $command = "curl -X POST --Header 'Authorization: key=". $notifications_key  ."' --Header 'Content-Type: application/json' -d '" . $CURLdata . "' 'http://fcm.googleapis.com/fcm/send'";

                //Execute the curl request $command and store it as an array
                $output = json_decode(shell_exec($command));
	
                echo '[{"status":"200","info":"notifications sent"}]';
        }

	function done(){
		echo '[{"status":"200","info":"Request successful."}]';
		die();
	};

?>
